<?php

namespace Tests\Feature;

use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A regional partner sells nothing — they coordinate the hosts and suppliers in
 * their region. The client asked for "a dashboard listing all HLHs and OSPs
 * within their region so they can oversee local development".
 *
 * What matters here is the boundary: their own region, approved providers only,
 * nobody else's region, and no sight of anything an HRP has no business seeing.
 */
class HrpRegionOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected string $portal;
    protected Region $tirthan;
    protected Region $spiti;
    protected ServiceProvider $hrp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->portal = config('app.portal_domain');

        $this->tirthan = Region::create([
            'name' => 'Tirthan Valley', 'slug' => 'tirthan-valley',
            'country' => 'India', 'is_active' => true,
        ]);
        $this->spiti = Region::create([
            'name' => 'Spiti Valley', 'slug' => 'spiti-valley',
            'country' => 'India', 'is_active' => true,
        ]);

        $this->hrp = $this->makeProvider(['hrp'], 'hrp@example.test', $this->tirthan);
    }

    private function makeProvider(
        array $types,
        string $email,
        Region $region,
        string $status = 'approved',
    ): ServiceProvider {
        $user = User::create([
            'full_name' => strtoupper($types[0]) . ' User',
            'email' => $email,
            'password' => 'password',
            'user_role' => $types[0],
            'status' => 'active',
        ]);

        return ServiceProvider::create([
            'user_id' => $user->id,
            'provider_type' => $types[0],
            'provider_types' => $types,
            'name' => ucfirst(explode('@', $email)[0]),
            'email' => $email,
            'phone_1' => '9000000000',
            'region_id' => $region->id,
            'status' => $status,
        ]);
    }

    private function ajax(array $payload)
    {
        return $this->post("http://{$this->portal}/ajax", $payload);
    }

    private function overview()
    {
        return $this->ajax(['get_hrp_region_providers' => 1]);
    }

    public function test_a_partner_sees_the_hosts_and_suppliers_in_their_region(): void
    {
        $this->makeProvider(['hlh'], 'host@example.test', $this->tirthan);
        $this->makeProvider(['osp'], 'taxi@example.test', $this->tirthan);

        $this->actingAs($this->hrp->user);
        $names = collect($this->overview()->assertOk()->json('providers'))->pluck('name');

        $this->assertEqualsCanonicalizing(['Host', 'Taxi'], $names->all());
    }

    /** Another partner's region is none of their business. */
    public function test_providers_from_another_region_are_excluded(): void
    {
        $this->makeProvider(['hlh'], 'host@example.test', $this->tirthan);
        $this->makeProvider(['hlh'], 'elsewhere@example.test', $this->spiti);

        $this->actingAs($this->hrp->user);
        $names = collect($this->overview()->assertOk()->json('providers'))->pluck('name');

        $this->assertSame(['Host'], $names->all());
    }

    /** A pending applicant has not been vetted by HCT yet. */
    public function test_unapproved_providers_are_excluded(): void
    {
        $this->makeProvider(['hlh'], 'approved@example.test', $this->tirthan);
        $this->makeProvider(['hlh'], 'pending@example.test', $this->tirthan, 'pending');

        $this->actingAs($this->hrp->user);
        $names = collect($this->overview()->assertOk()->json('providers'))->pluck('name');

        $this->assertSame(['Approved'], $names->all());
    }

    /** A provider who is both is listed once, carrying both labels. */
    public function test_a_dual_role_provider_appears_once_with_both_labels(): void
    {
        $this->makeProvider(['hlh', 'osp'], 'both@example.test', $this->tirthan);

        $this->actingAs($this->hrp->user);
        $providers = $this->overview()->assertOk()->json('providers');

        $this->assertCount(1, $providers);
        $this->assertEqualsCanonicalizing(['hlh', 'osp'], $providers[0]['types']);
        $this->assertCount(2, $providers[0]['type_labels']);
    }

    /** Another regional partner in the same region is a peer, not oversight. */
    public function test_other_regional_partners_are_not_listed(): void
    {
        $this->makeProvider(['hrp'], 'peer@example.test', $this->tirthan);
        $this->makeProvider(['hlh'], 'host@example.test', $this->tirthan);

        $this->actingAs($this->hrp->user);
        $names = collect($this->overview()->assertOk()->json('providers'))->pluck('name');

        $this->assertSame(['Host'], $names->all());
    }

    /** An HRP coordinates these providers — it does not administer them. */
    public function test_bank_details_and_documents_are_not_exposed(): void
    {
        $host = $this->makeProvider(['hlh'], 'host@example.test', $this->tirthan);
        $host->update([
            'bank_account_number' => '1234567890',
            'bank_ifsc' => 'HDFC0001234',
            'documents' => [['label' => 'Government ID', 'path' => 'x.jpg']],
        ]);

        $this->actingAs($this->hrp->user);
        $body = $this->overview()->assertOk()->getContent();

        $this->assertStringNotContainsString('1234567890', $body);
        $this->assertStringNotContainsString('HDFC0001234', $body);
        $this->assertStringNotContainsString('bank_account_number', $body);
        $this->assertStringNotContainsString('documents', $body);
    }

    /** Hosts and suppliers have no region to oversee. */
    public function test_a_host_cannot_read_the_region_overview(): void
    {
        $host = $this->makeProvider(['hlh'], 'host@example.test', $this->tirthan);

        $this->actingAs($host->user);
        $this->overview()->assertStatus(403);
    }

    public function test_an_unauthenticated_caller_is_blocked(): void
    {
        $this->overview()->assertStatus(401);
    }

    /** The overview has to actually be reachable from the partner's dashboard. */
    public function test_the_dashboard_carries_the_region_overview_for_a_partner(): void
    {
        $this->actingAs($this->hrp->user)
            ->get("http://{$this->portal}/sp/dashboard")
            ->assertOk()
            ->assertSee('Hosts &amp; Providers in My Region', false)
            ->assertSee('get_hrp_region_providers');
    }

    public function test_the_dashboard_hides_the_region_overview_from_a_host(): void
    {
        $host = $this->makeProvider(['hlh'], 'host@example.test', $this->tirthan);

        $this->actingAs($host->user)
            ->get("http://{$this->portal}/sp/dashboard")
            ->assertOk()
            ->assertDontSee('Hosts &amp; Providers in My Region', false);
    }
}
