<?php

namespace Tests\Feature;

use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\Trip;
use App\Models\TripDay;
use App\Models\TripDayService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NEW-B — changeDayServiceProvider must reject assigning a day service to a
 * non-existent or unapproved provider.
 */
class DayServiceProviderGuardTest extends TestCase
{
    use RefreshDatabase;

    protected string $portal;
    protected User $admin;
    protected TripDayService $service;
    protected ServiceProvider $approved;
    protected ServiceProvider $pending;

    protected function setUp(): void
    {
        parent::setUp();
        $this->portal = config('app.portal_domain');
        $this->admin = User::create([
            'full_name' => 'Admin', 'email' => 'admin@dsp.test',
            'password' => 'password', 'user_role' => 'administrator', 'status' => 'active',
        ]);
        $region = Region::create(['name' => 'Valley', 'slug' => 'valley', 'is_active' => true]);
        $trip = Trip::create([
            'trip_id' => Trip::generateTripId(), 'user_id' => $this->admin->id,
            'trip_name' => 'T', 'status' => 'not_confirmed', 'stage' => 'open',
            'adults' => 2, 'start_date' => '2026-06-01', 'end_date' => '2026-06-03',
        ]);
        $day = TripDay::create(['trip_id' => $trip->id, 'day_number' => 1, 'sort_order' => 0, 'date' => '2026-06-01', 'added_by' => 'hct']);
        $this->service = TripDayService::create([
            'trip_day_id' => $day->id, 'service_type' => 'transport',
            'description' => 'Transfer', 'cost' => 0, 'is_included' => true,
        ]);
        $this->approved = ServiceProvider::create([
            'provider_type' => 'osp', 'name' => 'Cabs', 'email' => 'c@dsp.test',
            'phone_1' => '9990001111', 'region_id' => $region->id, 'status' => 'approved',
        ]);
        $this->pending = ServiceProvider::create([
            'provider_type' => 'osp', 'name' => 'Pending Cabs', 'email' => 'p@dsp.test',
            'phone_1' => '9990002222', 'region_id' => $region->id, 'status' => 'pending',
        ]);
    }

    private function assign(int $spId)
    {
        return $this->actingAs($this->admin)->post("http://{$this->portal}/ajax", [
            'change_day_service_provider' => 1,
            'service_id' => $this->service->id,
            'service_provider_id' => $spId,
        ]);
    }

    public function test_unapproved_provider_is_rejected(): void
    {
        $this->assign($this->pending->id)->assertStatus(422);
    }

    public function test_approved_provider_is_accepted(): void
    {
        $this->assign($this->approved->id)->assertStatus(200);
        $this->assertEquals($this->approved->id, $this->service->fresh()->service_provider_id);
    }
}
