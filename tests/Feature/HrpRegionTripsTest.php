<?php

namespace Tests\Feature;

use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\Trip;
use App\Models\TripRegion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #37 — an approved HRP must see trips in its region. The old code matched
 * TripRegion.hrp_id (never populated), so the list was always empty. Fixed to
 * match on region_id.
 */
class HrpRegionTripsTest extends TestCase
{
    use RefreshDatabase;

    public function test_hrp_sees_trips_in_its_region(): void
    {
        $portal = config('app.portal_domain');

        $region = Region::create(['name' => 'Tirthan', 'slug' => 'tirthan', 'is_active' => true]);
        $otherRegion = Region::create(['name' => 'Spiti', 'slug' => 'spiti', 'is_active' => true]);

        $hrpUser = User::create([
            'full_name' => 'HRP', 'email' => 'hrp@r.test',
            'password' => 'password', 'user_role' => 'provider', 'status' => 'active',
        ]);
        ServiceProvider::create([
            'provider_type' => 'hrp', 'name' => 'Tirthan Partner', 'email' => 'hrp@r.test',
            'phone_1' => '9990001111', 'region_id' => $region->id, 'status' => 'approved',
            'user_id' => $hrpUser->id,
        ]);

        // A trip whose region matches the HRP's region.
        $trip = Trip::create([
            'trip_id' => Trip::generateTripId(), 'user_id' => $hrpUser->id,
            'trip_name' => 'Tirthan Trip', 'status' => 'confirmed', 'stage' => 'open',
            'adults' => 2, 'start_date' => '2026-06-01', 'end_date' => '2026-06-03',
        ]);
        TripRegion::create(['trip_id' => $trip->id, 'region_id' => $region->id]);

        // A trip in another region must NOT appear.
        $otherTrip = Trip::create([
            'trip_id' => Trip::generateTripId(), 'user_id' => $hrpUser->id,
            'trip_name' => 'Spiti Trip', 'status' => 'confirmed', 'stage' => 'open',
            'adults' => 2, 'start_date' => '2026-07-01', 'end_date' => '2026-07-03',
        ]);
        TripRegion::create(['trip_id' => $otherTrip->id, 'region_id' => $otherRegion->id]);

        $resp = $this->actingAs($hrpUser)
            ->post("http://{$portal}/ajax", ['get_sp_assigned_trips' => 1])
            ->assertStatus(200);

        $resp->assertJsonFragment(['trip_name' => 'Tirthan Trip']);
        $resp->assertJsonMissing(['trip_name' => 'Spiti Trip']);
    }
}
