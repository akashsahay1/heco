<?php

namespace Tests\Feature;

use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\SpPricing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A rate card belongs to a supplier.
 *
 * SpController already turned a host or a regional partner away from the page,
 * but the app talks to the ajax endpoint directly — so a regional partner, who
 * coordinates a region and sells nothing at all, could still keep one.
 */
class RateCardIsForSuppliersTest extends TestCase
{
    use RefreshDatabase;

    protected string $portal;
    protected Region $region;

    protected function setUp(): void
    {
        parent::setUp();
        $this->portal = config('app.portal_domain');
        $this->region = Region::create([
            'name' => 'Kumaon', 'slug' => 'kumaon', 'country' => 'India', 'is_active' => true,
        ]);
    }

    private function provider(string $email, array $types): ServiceProvider
    {
        $user = User::create([
            'full_name' => 'Member', 'email' => $email,
            'password' => 'password', 'user_role' => 'provider', 'status' => 'active',
        ]);

        return ServiceProvider::create([
            'user_id' => $user->id,
            'provider_type' => $types[0],
            'provider_types' => $types,
            'name' => 'Member ' . $email,
            'email' => $email,
            'phone_1' => '9000000000',
            'region_id' => $this->region->id,
            'status' => 'approved',
        ]);
    }

    private function readRates(ServiceProvider $sp)
    {
        return $this->actingAs($sp->user)
            ->post("http://{$this->portal}/ajax", ['get_sp_pricing' => 1]);
    }

    private function saveRate(ServiceProvider $sp)
    {
        return $this->actingAs($sp->user)
            ->post("http://{$this->portal}/ajax", [
                'save_sp_pricing' => 1,
                'service_type' => 'transport',
                'vehicle_type' => 'SUV',
                'description' => 'SUV shuttle',
                'price' => 25,
                'unit' => 'per km',
            ]);
    }

    public function test_a_supplier_keeps_a_rate_card(): void
    {
        $osp = $this->provider('osp@example.test', ['osp']);

        $this->readRates($osp)->assertOk();
        $this->saveRate($osp)->assertOk();
    }

    public function test_a_regional_partner_has_no_rate_card(): void
    {
        $hrp = $this->provider('hrp@example.test', ['hrp']);

        $this->readRates($hrp)->assertStatus(403);
        $this->saveRate($hrp)->assertStatus(403);
        $this->assertSame(0, SpPricing::count());
    }

    public function test_a_pure_host_has_no_rate_card_either(): void
    {
        $hlh = $this->provider('hlh@example.test', ['hlh']);

        $this->readRates($hlh)->assertStatus(403);
        $this->saveRate($hlh)->assertStatus(403);
    }

    /**
     * The combination the multi-role work exists for: a host who also supplies
     * keeps both, and must not be caught by the gate.
     */
    public function test_a_host_who_also_supplies_keeps_one(): void
    {
        $both = $this->provider('both@example.test', ['hlh', 'osp']);

        $this->readRates($both)->assertOk();
        $this->saveRate($both)->assertOk();
    }

    /**
     * The set is what counts, not the primary column — a member who signed up
     * as a host first and added services later is still a supplier.
     */
    public function test_the_primary_type_does_not_decide_it(): void
    {
        $sp = $this->provider('primary@example.test', ['hlh', 'osp']);
        $sp->update(['provider_type' => 'hlh']);

        $this->readRates($sp->fresh())->assertOk();
    }
}
