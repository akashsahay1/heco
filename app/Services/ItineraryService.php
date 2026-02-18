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

            if (isset($aiResponse['days']) && is_array($aiResponse['days'])) {
                foreach ($aiResponse['days'] as $index => $dayData) {
                    $day = TripDay::create([
                        'trip_id' => $trip->id,
                        'day_number' => $index + 1,
                        'date' => $trip->start_date ? $trip->start_date->addDays($index) : null,
                        'title' => $dayData['title'] ?? 'Day ' . ($index + 1),
                        'description' => $dayData['description'] ?? null,
                        'sort_order' => $index,
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
                        foreach ($dayData['services'] as $svcIndex => $svcData) {
                            TripDayService::create([
                                'trip_day_id' => $day->id,
                                'service_provider_id' => $svcData['service_provider_id'] ?? null,
                                'service_type' => $svcData['service_type'] ?? 'other',
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
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ItineraryService parse error: ' . $e->getMessage());
            return false;
        }
    }

    public function addExperienceToDay(TripDay $day, Experience $experience, array $data = []): TripDayExperience
    {
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
}
