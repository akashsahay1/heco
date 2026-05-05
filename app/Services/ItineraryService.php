<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\TripDay;
use App\Models\TripDayExperience;
use App\Models\TripDayService;
use App\Models\Experience;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ItineraryService
{
    public function parseAndCreateFromAi(Trip $trip, array $aiResponse): bool
    {
        try {
            DB::beginTransaction();

            // Clear existing days before creating new ones
            $trip->tripDays()->each(function ($day) {
                $day->experiences()->delete();
                $day->services()->delete();
                $day->delete();
            });

            $dayNumber = 0;
            if (isset($aiResponse['days']) && is_array($aiResponse['days'])) {
                foreach ($aiResponse['days'] as $index => $dayData) {
                    // Determine day type
                    $dayType = $dayData['day_type'] ?? 'activity';
                    $validDayTypes = ['arrival', 'activity', 'rest', 'travel', 'departure', 'free'];
                    if (!in_array($dayType, $validDayTypes)) $dayType = 'activity';

                    $hasExperiences = isset($dayData['experiences']) && is_array($dayData['experiences']) && !empty($dayData['experiences']);
                    $hasServices = isset($dayData['services']) && is_array($dayData['services']) && !empty($dayData['services']);

                    // Keep arrival/departure/rest/travel days even if empty
                    if (!$hasExperiences && !$hasServices && $dayType === 'activity') {
                        continue;
                    }

                    $dayNumber++;
                    $day = TripDay::create([
                        'trip_id' => $trip->id,
                        'day_number' => $dayNumber,
                        'date' => $trip->start_date ? $trip->start_date->addDays($dayNumber - 1) : null,
                        'title' => $dayData['title'] ?? 'Day ' . $dayNumber,
                        'description' => $dayData['description'] ?? ($dayData['notes'] ?? null),
                        'day_type' => $dayType,
                        'sort_order' => $dayNumber - 1,
                    ]);

                    if (isset($dayData['experiences']) && is_array($dayData['experiences'])) {
                        foreach ($dayData['experiences'] as $expIndex => $expData) {
                            $experience = null;
                            if (isset($expData['experience_id'])) {
                                $experience = Experience::find($expData['experience_id']);
                            } elseif (isset($expData['slug'])) {
                                $experience = Experience::where('slug', $expData['slug'])->first();
                            }

                            if ($experience) {
                                $costPerPerson = $expData['cost_per_person'] ?? $experience->base_cost_per_person;
                                $adults = $trip->adults ?: 1;

                                TripDayExperience::create([
                                    'trip_day_id' => $day->id,
                                    'experience_id' => $experience->id,
                                    'start_time' => $expData['start_time'] ?? null,
                                    'end_time' => $expData['end_time'] ?? null,
                                    'cost_per_person' => $costPerPerson,
                                    'total_cost' => $expData['total_cost'] ?? ($costPerPerson * $adults),
                                    'notes' => $expData['notes'] ?? null,
                                    'sort_order' => $expIndex,
                                ]);
                            }
                        }
                    }

                    if (isset($dayData['services']) && is_array($dayData['services'])) {
                        $validServiceTypes = ['accommodation', 'transport', 'guide', 'activity', 'meal', 'other'];
                        foreach ($dayData['services'] as $svcIndex => $svcData) {
                            $serviceType = $svcData['service_type'] ?? 'other';
                            if (!in_array($serviceType, $validServiceTypes)) {
                                $serviceType = 'other';
                            }
                            TripDayService::create([
                                'trip_day_id' => $day->id,
                                'service_provider_id' => $svcData['service_provider_id'] ?? null,
                                'service_type' => $serviceType,
                                'description' => $svcData['description'] ?? null,
                                'from_location' => $svcData['from_location'] ?? null,
                                'to_location' => $svcData['to_location'] ?? null,
                                'cost' => $svcData['cost'] ?? 0,
                                'is_included' => $svcData['is_included'] ?? true,
                                'notes' => $svcData['notes'] ?? null,
                                'sort_order' => $svcIndex,
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            // Auto-assign available SPs (non-fatal)
            try {
                $matched = (new SpMatchingService())->assignProvidersToTrip($trip);
                if ($matched > 0) {
                    Log::info("SpMatchingService: assigned {$matched} SPs for trip #{$trip->id}");
                }
            } catch (\Exception $e) {
                Log::warning("SP auto-assignment failed for trip #{$trip->id}: " . $e->getMessage());
            }

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ItineraryService parse error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Rebuild the trip's day-by-day timeline deterministically from its
     * selected experiences. Each experience contributes its full
     * duration_days so the resulting day count always matches what the
     * traveller has actually picked. AI-generated titles/notes (if any)
     * are layered on top via $aiDays — pass an empty array to skip AI
     * enrichment (fast path used when experiences are added/removed).
     */
    public function rebuildFromExperiences(Trip $trip, array $aiDays = []): bool
    {
        $trip->load([
            'selectedExperiences' => fn ($q) => $q->orderBy('sort_order'),
            'selectedExperiences.experience.region',
            'selectedExperiences.experience.days',
        ]);

        $expModels = $trip->selectedExperiences->pluck('experience')->filter()->values();

        if ($expModels->isEmpty()) {
            // No experiences — clear any existing days so the timeline reflects reality.
            $trip->tripDays()->each(function ($day) {
                $day->experiences()->delete();
                $day->services()->delete();
                $day->delete();
            });
            return true;
        }

        $parsed = ['days' => $this->buildDeterministicDays($trip, $expModels, $aiDays)];

        return $this->parseAndCreateFromAi($trip, $parsed);
    }

    /**
     * Compose the day-by-day array consumed by parseAndCreateFromAi.
     * Pulls per-day titles/descriptions/inclusions from the Experience
     * editor when available, falling back to phase-aware generic copy.
     * AI suggestions in $aiDays are merged on top (additive only).
     */
    public function buildDeterministicDays(Trip $trip, $expModels, array $aiDays = []): array
    {
        $accomComfort = $trip->accommodation_comfort;
        $vehicleComfort = $trip->vehicle_comfort;
        $guidePref = $trip->guide_preference;

        $inclusionToService = [
            'breakfast'      => ['type' => 'meal',          'desc' => 'Breakfast'],
            'lunch'          => ['type' => 'meal',          'desc' => 'Lunch'],
            'dinner'         => ['type' => 'meal',          'desc' => 'Dinner'],
            'snacks'         => ['type' => 'meal',          'desc' => 'Snacks'],
            'accommodation'  => ['type' => 'accommodation', 'desc' => 'Accommodation'],
            'guide'          => ['type' => 'guide',         'desc' => 'Guide'],
            'transport'      => ['type' => 'transport',     'desc' => 'Transport'],
        ];

        $expById = $expModels->keyBy('id');

        // Fan out each experience across its full duration so a 5-day plan
        // produces exactly 5 day entries.
        $dayMapping = [];
        foreach ($expModels as $exp) {
            $expDays = ($exp->duration_type === 'multi_day') ? ($exp->duration_days ?? 1) : 1;
            for ($d = 1; $d <= $expDays; $d++) {
                $dayMapping[] = [
                    'experience_id' => $exp->id,
                    'experience_name' => $exp->name,
                    'day_of_experience' => $d,
                    'total_experience_days' => $expDays,
                ];
            }
        }

        $days = [];
        foreach ($dayMapping as $idx => $dm) {
            $aiDay = $aiDays[$idx] ?? [];
            $exp = $expById->get($dm['experience_id']);
            $expName = $dm['experience_name'];
            $dayOfExp = $dm['day_of_experience'];
            $totalExpDays = $dm['total_experience_days'];
            $regionName = $exp?->region?->name;

            $expDay = $exp?->days?->firstWhere('day_number', $dayOfExp);

            $genericTitle = $totalExpDays > 1
                ? $expName . ' — Day ' . $dayOfExp . ' of ' . $totalExpDays
                : $expName;
            $editorTitle = $expDay?->title ? ($expName . ' — ' . $expDay->title) : null;

            if ($totalExpDays > 1) {
                if ($dayOfExp === 1) {
                    $phase = 'Begin your ' . $expName . ' journey';
                } elseif ($dayOfExp === $totalExpDays) {
                    $phase = 'Conclude your ' . $expName . ' journey';
                } else {
                    $phase = 'Continue your ' . $expName . ' journey';
                }
            } else {
                $phase = 'Spend the day exploring ' . $expName;
            }
            $genericDescription = $regionName ? ($phase . ' in ' . $regionName . '.') : ($phase . '.');
            if ($exp?->short_description) {
                $genericDescription .= ' ' . $exp->short_description;
            }
            $editorDescription = $expDay?->short_description ?: null;

            $startTime = $expDay?->start_time ?: '09:00';
            $endTime   = $expDay?->end_time   ?: '17:00';

            $services = [];
            $coveredTypes = [];
            $inclusions = is_array($expDay?->inclusions) ? $expDay->inclusions : [];
            foreach ($inclusions as $inc) {
                $key = strtolower(trim((string) $inc));
                if (!isset($inclusionToService[$key])) continue;
                $map = $inclusionToService[$key];
                $services[] = [
                    'service_type' => $map['type'],
                    'description'  => $map['desc'],
                    'is_included'  => true,
                    'cost'         => 0,
                ];
                $coveredTypes[$map['type']] = true;
            }

            if ($accomComfort && empty($coveredTypes['accommodation']) && empty($exp?->includes_accommodation)) {
                $services[] = [
                    'service_type' => 'accommodation',
                    'description'  => $accomComfort . ($regionName ? ' accommodation in ' . $regionName : ' accommodation'),
                    'is_included'  => true,
                    'cost'         => 0,
                ];
            }
            if ($vehicleComfort && $vehicleComfort !== 'Local Transport' && empty($coveredTypes['transport']) && empty($exp?->includes_transport)) {
                $services[] = [
                    'service_type' => 'transport',
                    'description'  => $vehicleComfort,
                    'is_included'  => true,
                    'cost'         => 0,
                ];
            }
            if ($guidePref && $guidePref !== 'No Guide' && empty($coveredTypes['guide']) && empty($exp?->includes_guide)) {
                $services[] = [
                    'service_type' => 'guide',
                    'description'  => $guidePref . ' guide for ' . $expName,
                    'is_included'  => true,
                    'cost'         => 0,
                ];
            }
            if (!empty($aiDay['services']) && is_array($aiDay['services'])) {
                foreach ($aiDay['services'] as $aiSvc) {
                    $services[] = $aiSvc;
                }
            }

            $days[] = [
                'title'       => $aiDay['title'] ?? $editorTitle ?? $genericTitle,
                'description' => $aiDay['description'] ?? $aiDay['notes'] ?? $editorDescription ?? $genericDescription,
                'notes'       => $aiDay['notes'] ?? null,
                'day_type'    => 'activity',
                'experiences' => [[
                    'experience_id' => $exp?->id,
                    'name'          => $expName,
                    'start_time'    => $aiDay['experiences'][0]['start_time'] ?? $startTime,
                    'end_time'      => $aiDay['experiences'][0]['end_time']   ?? $endTime,
                    'notes'         => $aiDay['experiences'][0]['notes']      ?? null,
                ]],
                'services' => $services,
            ];
        }

        return $days;
    }

    public function addExperienceToDay(TripDay $day, Experience $experience, array $data = []): TripDayExperience
    {
        $experience->loadMissing('days');
        $maxSort = $day->experiences()->max('sort_order') ?? -1;
        $costPerPerson = $data['cost_per_person'] ?? $experience->base_cost_per_person;
        $adults = $day->trip->adults ?: 1;

        return TripDayExperience::create([
            'trip_day_id' => $day->id,
            'experience_id' => $experience->id,
            'start_time' => $data['start_time'] ?? $experience->start_time,
            'end_time' => $data['end_time'] ?? $experience->end_time,
            'cost_per_person' => $costPerPerson,
            'total_cost' => $data['total_cost'] ?? ($costPerPerson * $adults),
            'notes' => $data['notes'] ?? null,
            'sort_order' => $maxSort + 1,
        ]);
    }

    public function removeExperienceFromDay(int $tripDayExperienceId): bool
    {
        return TripDayExperience::destroy($tripDayExperienceId) > 0;
    }

    /**
     * Ensure multi-day experiences span their full duration_days.
     * If the AI only assigned an experience to fewer days than its duration,
     * create the missing TripDays with the experience linked.
     */
    protected function fillMissingExperienceDays(Trip $trip, int &$dayNumber): void
    {
        $adults = $trip->adults ?: 1;

        // Count how many trip days each experience was assigned to
        $tripDays = $trip->tripDays()->with('experiences')->get();
        $experienceDayCounts = [];
        foreach ($tripDays as $day) {
            foreach ($day->experiences as $tde) {
                $expId = $tde->experience_id;
                $experienceDayCounts[$expId] = ($experienceDayCounts[$expId] ?? 0) + 1;
            }
        }

        // Check each experience and fill missing days
        foreach ($experienceDayCounts as $expId => $assignedCount) {
            $experience = Experience::with('days')->find($expId);
            if (!$experience || $experience->duration_type !== 'multi_day') continue;

            $targetDays = $experience->duration_days ?? 1;
            if ($assignedCount >= $targetDays) continue;

            $toAdd = $targetDays - $assignedCount;
            $costPerPerson = $experience->base_cost_per_person;

            for ($i = 0; $i < $toAdd; $i++) {
                $dayNumber++;
                // Try to get title from ExperienceDay for this day number
                $expDay = $experience->days->firstWhere('day_number', $assignedCount + $i + 1);

                $day = TripDay::create([
                    'trip_id' => $trip->id,
                    'day_number' => $dayNumber,
                    'date' => $trip->start_date ? $trip->start_date->addDays($dayNumber - 1) : null,
                    'title' => $expDay->title ?? ('Day ' . $dayNumber),
                    'description' => $expDay->short_description ?? null,
                    'sort_order' => $dayNumber - 1,
                ]);

                TripDayExperience::create([
                    'trip_day_id' => $day->id,
                    'experience_id' => $experience->id,
                    'start_time' => $expDay->start_time ?? null,
                    'end_time' => $expDay->end_time ?? null,
                    'cost_per_person' => $costPerPerson,
                    'total_cost' => $costPerPerson * $adults,
                    'sort_order' => 0,
                ]);
            }

            Log::info("ItineraryService: filled {$toAdd} missing days for experience #{$expId} in trip #{$trip->id}");
        }
    }
}
