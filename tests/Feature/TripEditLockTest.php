<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\TravellerPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #10 — a trip that is paid or closed must reject itinerary/price edits
 * (guardLockedTrip). Confirmed-but-unpaid, open trips stay editable.
 */
class TripEditLockTest extends TestCase
{
    use RefreshDatabase;

    protected string $portal;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->portal = config('app.portal_domain');
        $this->admin = User::create([
            'full_name' => 'Admin', 'email' => 'admin@lock.test',
            'password' => 'password', 'user_role' => 'hct_admin', 'status' => 'active',
        ]);
    }

    private function makeTrip(array $overrides = []): Trip
    {
        return Trip::create(array_merge([
            'trip_id' => Trip::generateTripId(), 'user_id' => $this->admin->id,
            'trip_name' => 'T', 'status' => 'not_confirmed', 'stage' => 'open',
            'adults' => 1, 'children' => 0, 'infants' => 0,
            'start_date' => '2026-06-01', 'end_date' => '2026-06-02',
        ], $overrides));
    }

    private function addDay(Trip $trip)
    {
        return $this->actingAs($this->admin)
            ->post("http://{$this->portal}/ajax", ['add_trip_day' => 1, 'trip_id' => $trip->id]);
    }

    public function test_paid_trip_edit_is_blocked(): void
    {
        $trip = $this->makeTrip();
        TravellerPayment::create([
            'trip_id' => $trip->id, 'user_id' => $this->admin->id, 'amount' => 1000,
            'payment_status' => 'paid', 'payment_date' => '2026-06-01',
            'mode' => 'cash', 'recorded_by' => $this->admin->id,
        ]);

        $this->addDay($trip)->assertStatus(423);
        $this->assertSame(0, $trip->tripDays()->count());
    }

    public function test_closed_stage_trip_edit_is_blocked(): void
    {
        $trip = $this->makeTrip(['stage' => 'closed']);
        $this->addDay($trip)->assertStatus(423);
    }

    public function test_open_unpaid_trip_edit_is_allowed(): void
    {
        $trip = $this->makeTrip();
        $this->addDay($trip)->assertStatus(200);
        $this->assertSame(1, $trip->tripDays()->count());
    }
}
