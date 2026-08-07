<?php

namespace Tests\Feature;

use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin provider list filters by role. A provider can hold several roles at
 * once, and the filter used to look only at the primary one — so filtering by
 * OSP missed an HLH that also runs a taxi. It is the combined providers HCT
 * most needs to find, and they were the ones falling out of the list.
 */
class AdminProviderTypeFilterTest extends TestCase
{
    use RefreshDatabase;

    protected string $portal;
    protected Region $region;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->portal = config('app.portal_domain');
        $this->region = Region::create([
            'name' => 'Kumaon', 'slug' => 'kumaon',
            'country' => 'India', 'is_active' => true,
        ]);
        $this->admin = User::create([
            'full_name' => 'Admin', 'email' => 'admin@example.test',
            'password' => 'password', 'user_role' => 'administrator', 'status' => 'active',
        ]);
    }

    private function provider(string $name, array $types): ServiceProvider
    {
        return ServiceProvider::create([
            'name' => $name,
            'email' => str($name)->slug() . '@example.test',
            'phone_1' => '9800000000',
            'region_id' => $this->region->id,
            'provider_types' => $types,
            'status' => 'approved',
        ]);
    }

    /** @return string[] names returned by the list, sorted */
    private function filterBy(?string $type): array
    {
        $payload = ['get_providers' => 1, 'region_id' => $this->region->id];
        if ($type !== null) {
            $payload['provider_type'] = $type;
        }

        $res = $this->actingAs($this->admin)->post("http://{$this->portal}/ajax", $payload);
        $res->assertOk();

        $names = array_column($res->json('data'), 'name');
        sort($names);
        return $names;
    }

    public function test_a_combined_provider_is_found_under_either_role(): void
    {
        $this->provider('Munsiyari Homestay', ['hlh']);
        $this->provider('Nanda Devi Taxi', ['osp']);
        $this->provider('Binsar Heritage House', ['hlh', 'osp']);

        $this->assertSame(
            ['Binsar Heritage House', 'Munsiyari Homestay'],
            $this->filterBy('hlh'),
        );

        // The one that used to go missing: primary is hlh, but it supplies too.
        $this->assertSame(
            ['Binsar Heritage House', 'Nanda Devi Taxi'],
            $this->filterBy('osp'),
        );
    }

    public function test_a_role_the_provider_does_not_hold_excludes_it(): void
    {
        $this->provider('Binsar Heritage House', ['hlh', 'osp']);
        $this->provider('Deepak Bisht', ['hrp']);

        $this->assertSame(['Deepak Bisht'], $this->filterBy('hrp'));
    }

    public function test_no_filter_returns_everyone_in_the_region(): void
    {
        $this->provider('Munsiyari Homestay', ['hlh']);
        $this->provider('Binsar Heritage House', ['hlh', 'osp']);
        $this->provider('Deepak Bisht', ['hrp']);

        $this->assertCount(3, $this->filterBy(null));
    }
}
