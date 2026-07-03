<?php

namespace Tests\Feature;

use App\Models\Region;
use App\Models\Trip;
use App\Models\TripDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #28 — a traveller can download their OWN trip PDF from the portal, and must
 * not be able to download another traveller's (ownership enforced).
 */
class PortalTripPdfTest extends TestCase
{
    use RefreshDatabase;

    protected string $portal;
    protected User $owner;
    protected User $other;
    protected Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();
        $this->portal = config('app.portal_domain');
        Region::create(['name' => 'Valley', 'slug' => 'valley', 'is_active' => true]);

        $this->owner = User::create([
            'full_name' => 'Owner', 'email' => 'owner@pdf.test',
            'password' => 'password', 'user_role' => 'traveller', 'status' => 'active',
        ]);
        $this->other = User::create([
            'full_name' => 'Other', 'email' => 'other@pdf.test',
            'password' => 'password', 'user_role' => 'traveller', 'status' => 'active',
        ]);
        $this->trip = Trip::create([
            'trip_id' => Trip::generateTripId(), 'user_id' => $this->owner->id,
            'trip_name' => 'My Trip', 'status' => 'confirmed', 'stage' => 'open',
            'adults' => 2, 'start_date' => '2026-06-01', 'end_date' => '2026-06-02',
        ]);
        TripDay::create(['trip_id' => $this->trip->id, 'day_number' => 1, 'sort_order' => 0, 'date' => '2026-06-01', 'added_by' => 'traveller']);
    }

    public function test_owner_can_download_own_trip_pdf(): void
    {
        $this->actingAs($this->owner)
            ->get("http://{$this->portal}/pdf/trip/{$this->trip->id}")
            ->assertStatus(200)
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_other_traveller_cannot_download_trip_pdf(): void
    {
        $this->actingAs($this->other)
            ->get("http://{$this->portal}/pdf/trip/{$this->trip->id}")
            ->assertStatus(403);
    }
}
