<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\RegenerativeProject;

class ImpactCalculatorService
{
    public function calculateForTrip(Trip $trip): array
    {
        $rpAmount = $trip->margin_rp_amount;
        if ($rpAmount <= 0) {
            return ['total_contribution' => 0, 'projects' => []];
        }

        $regionIds = $trip->tripRegions()->pluck('region_id')->toArray();
        $projects = RegenerativeProject::where('is_active', true)
            ->where(function ($q) use ($regionIds) {
                $q->whereIn('region_id', $regionIds)
                    ->orWhere(function ($q2) use ($regionIds) {
                        foreach ($regionIds as $rid) {
                            $q2->orWhereJsonContains('fallback_for_regions', $rid);
                        }
                    });
            })
            ->get();

        if ($projects->isEmpty()) {
            return ['total_contribution' => $rpAmount, 'projects' => []];
        }

        $perProject = round($rpAmount / $projects->count(), 2);
        $projectImpacts = [];

        foreach ($projects as $project) {
            $impactUnits = 0;
            if ($project->cost_per_impact_unit > 0) {
                $impactUnits = round($perProject / $project->cost_per_impact_unit, 1);
            }

            $projectImpacts[] = [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'region' => $project->region->name ?? '',
                'action_type' => $project->action_type,
                'contribution' => $perProject,
                'impact_units' => $impactUnits,
                'impact_unit_label' => $project->impact_unit,
            ];
        }

        return [
            'total_contribution' => $rpAmount,
            'projects' => $projectImpacts,
        ];
    }
}
