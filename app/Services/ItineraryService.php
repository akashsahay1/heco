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
                    // Skip empty days (no experiences and no services)
                    $hasExperiences = isset($dayData['experiences']) && is_array($dayData['experiences']) && !empty($dayData['experiences']);
                    $hasServices = isset($dayData['services']) && is_array($dayData['services']) && !empty($dayData['services']);
                    if (!$hasExperiences && !$hasServices) {
                        continue;
                    }

                    $dayNumber++;
                    $day = TripDay::create([
                        'trip_id' => $trip->id,
                        'day_number' => $dayNumber,
                        'date' => $trip->start_date ? $trip->start_date->addDays($dayNumber - 1) : null,
                        'title' => $dayData['title'] ?? 'Day ' . $dayNumber,
                        'description' => $dayData['description'] ?? null,
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
                                $adults = $trip->adults ?? 2;

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

            // Ensure multi-day experiences span their full duration_days
            $this->fillMissingExperienceDays($trip, $dayNumber);

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

    public function addExperienceToDay(TripDay $day, Experience $experience, array $data = []): TripDayExperience
    {
        $experience->loadMissing('days');
        $maxSort = $day->experiences()->max('sort_order') ?? -1;
        $costPerPerson = $data['cost_per_person'] ?? $experience->base_cost_per_person;
        $adults = $day->trip->adults ?? 2;

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
        $adults = $trip->adults ?? 2;

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
