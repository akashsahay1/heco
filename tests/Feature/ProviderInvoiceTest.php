<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\SpPayment;
use App\Models\SpPricing;
use App\Models\Trip;
use App\Models\TripDay;
use App\Models\TripDayExperience;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #11/#13 — confirming a trip auto-creates a provider invoice (SpPayment) with
 * amount = rate × quantity, and createSpPayment auto-derives the amount from the
 * pinned pricing rather than trusting a hand-typed value.
 */
class ProviderInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected string $portal;
    protected User $traveller;
    protected User $admin;
    protected ServiceProvider $provider;
    protected Region $region;
    protected Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();
        $this->portal = config('app.portal_domain');

        $this->traveller = User::create([
            'full_name' => 'Trav', 'email' => 'trav@inv.test',
            'password' => 'password', 'user_role' => 'traveller', 'status' => 'active',
        ]);
        $this->admin = User::create([
            'full_name' => 'Admin', 'email' => 'admin@inv.test',
            'password' => 'password', 'user_role' => 'hct_admin', 'status' => 'active',
        ]);
        $this->region = Region::create(['name' => 'Valley', 'slug' => 'valley', 'is_active' => true]);
        $region = $this->region;
        $exp = Experience::create([
            'region_id' => $region->id, 'name' => 'Trek', 'slug' => 'trek', 'type' => 'nature',
            'short_description' => 'x', 'duration_type' => 'days', 'is_active' => true,
            'base_cost_per_person' => 5000, 'cost_accommodation' => 1000, 'cost_activities' => 1000,
        ]);
        $this->provider = ServiceProvider::create([
            'provider_type' => 'hlh', 'name' => 'Stay', 'email' => 's@inv.test',
            'phone_1' => '9990001111', 'region_id' => $region->id, 'status' => 'approved',
        ]);
        $pricing = SpPricing::create([
            'service_provider_id' => $this->provider->id, 'service_type' => 'accommodation',
            'category' => 'Cat C - Standard', 'room_category' => 'Double', 'unit' => 'night',
            'price' => 2500, 'total_rooms' => 5, 'default_occupancy' => 2, 'is_active' => true,
            'approval_status' => 'approved',
        ]);
        // 2 adults, 2 nights (01->03 Jun), occupancy 2 -> 1 room -> 2500 * 1 * 2 = 5000.
        $this->trip = Trip::create([
            'trip_id' => Trip::generateTripId(), 'user_id' => $this->traveller->id,
            'trip_name' => 'T', 'status' => 'not_confirmed', 'stage' => 'open',
            'adults' => 2, 'children' => 0, 'infants' => 0,
            'start_date' => '2026-06-01', 'end_date' => '2026-06-03',
            'accommodation_provider_id' => $this->provider->id,
            'accommodation_pricing_id' => $pricing->id,
        ]);
        $day = TripDay::create(['trip_id' => $this->trip->id, 'day_number' => 1, 'sort_order' => 0, 'date' => '2026-06-01', 'added_by' => 'traveller']);
        TripDayExperience::create(['trip_day_id' => $day->id, 'experience_id' => $exp->id, 'cost_per_person' => 1000, 'sort_order' => 0]);
    }

    public function test_confirm_creates_provider_invoice_with_rate_times_qty(): void
    {
        $this->actingAs($this->traveller)
            ->post("http://{$this->portal}/ajax", ['confirm_trip' => 1, 'trip_id' => $this->trip->id])
            ->assertStatus(200);

        $this->assertDatabaseHas('sp_payments', [
            'trip_id' => $this->trip->id,
            'service_provider_id' => $this->provider->id,
            'service_type' => 'accommodation',
            'amount_due' => 5000,
            'balance' => 5000,
        ]);
        // Dedup — invoicing again does not create a second row.
        $this->assertSame(1, SpPayment::where('trip_id', $this->trip->id)->count());
    }

    public function test_confirm_invoices_day_level_assigned_provider(): void
    {
        $transport = ServiceProvider::create([
            'provider_type' => 'osp', 'name' => 'Cabs', 'email' => 'cab@inv.test',
            'phone_1' => '9990003333', 'region_id' => $this->region->id, 'status' => 'approved',
        ]);
        $day = $this->trip->tripDays()->first();
        \App\Models\TripDayService::create([
            'trip_day_id' => $day->id, 'service_type' => 'transport',
            'service_provider_id' => $transport->id, 'cost' => 3000, 'is_included' => true,
        ]);

        $this->actingAs($this->traveller)
            ->post("http://{$this->portal}/ajax", ['confirm_trip' => 1, 'trip_id' => $this->trip->id])
            ->assertStatus(200);

        $this->assertDatabaseHas('sp_payments', [
            'trip_id' => $this->trip->id,
            'service_provider_id' => $transport->id,
            'service_type' => 'transport',
            'amount_due' => 3000,
        ]);
    }

    public function test_confirm_reserves_rooms_for_trip_level_pin(): void
    {
        // 2 nights (01, 02 Jun), 2 adults / occupancy 2 -> 1 room per night.
        $this->actingAs($this->traveller)
            ->post("http://{$this->portal}/ajax", ['confirm_trip' => 1, 'trip_id' => $this->trip->id])
            ->assertStatus(200);

        $this->assertDatabaseHas('sp_room_bookings', [
            'trip_id' => $this->trip->id, 'date' => '2026-06-01 00:00:00', 'quantity' => 1, 'status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('sp_room_bookings', [
            'trip_id' => $this->trip->id, 'date' => '2026-06-02 00:00:00', 'quantity' => 1, 'status' => 'confirmed',
        ]);
        $this->assertSame(2, \App\Models\SpRoomBooking::where('trip_id', $this->trip->id)->count());
    }

    public function test_create_sp_payment_auto_derives_amount(): void
    {
        // Manual amount_due (999) is ignored — the pinned rate × qty (5000) wins.
        $this->actingAs($this->admin)
            ->post("http://{$this->portal}/ajax", [
                'create_sp_payment' => 1,
                'trip_id' => $this->trip->id,
                'service_provider_id' => $this->provider->id,
                'service_type' => 'accommodation',
                'amount_due' => 999,
            ])
            ->assertStatus(200)
            ->assertJsonPath('amount_due', 5000);
    }
}
