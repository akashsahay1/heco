<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\Setting;
use App\Models\SpPricing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Self-service listing caps.
 *
 * The client asked for a ceiling of 10 so a bad-faith signup cannot bury HCT's
 * review queue. What matters here is that the ceiling stops NEW listings while
 * never blocking an edit, that the two catalogues are counted separately, and
 * that HCT itself is not caught by it.
 */
class ProviderListingCapTest extends TestCase
{
    use RefreshDatabase;

    protected string $portal;
    protected Region $region;
    protected ServiceProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->portal = config('app.portal_domain');
        $this->region = Region::create([
            'name' => 'Tirthan Valley',
            'slug' => 'tirthan-valley',
            'country' => 'India',
            'is_active' => true,
        ]);

        // Both a host and a service supplier, so one fixture exercises both caps.
        $this->provider = $this->makeProvider(['hlh', 'osp'], 'both@example.test');
    }

    private function makeProvider(array $types, string $email): ServiceProvider
    {
        $user = User::create([
            'full_name' => 'Provider',
            'email' => $email,
            'password' => 'password',
            'user_role' => 'provider',
            'status' => 'active',
        ]);

        return ServiceProvider::create([
            'user_id' => $user->id,
            'provider_types' => $types,
            'name' => 'Test Provider',
            'email' => $email,
            'phone_1' => '9000000000',
            'region_id' => $this->region->id,
            'status' => 'approved',
        ]);
    }

    private function ajax(array $payload)
    {
        return $this->post("http://{$this->portal}/ajax", $payload);
    }

    private function experiencePayload(array $overrides = []): array
    {
        return array_merge([
            'save_sp_experience' => 1,
            'name' => 'Forest Walk',
            'region_id' => $this->region->id,
            'type' => 'Nature',
            'short_description' => 'A walk.',
            'duration_type' => 'single_day',
        ], $overrides);
    }

    private function servicePayload(array $overrides = []): array
    {
        return array_merge([
            'save_sp_pricing' => 1,
            'service_type' => 'guide',
            'price' => 1500,
            'unit' => 'per day',
        ], $overrides);
    }

    private function seedExperiences(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            Experience::create([
                'name' => "Seeded {$i}",
                'slug' => "seeded-{$i}",
                'region_id' => $this->region->id,
                'hlh_id' => $this->provider->id,
                'owner_provider_id' => $this->provider->id,
                'owner_type' => 'hlh',
                'type' => 'Nature',
                'short_description' => 'Seeded listing.',
                'duration_type' => 'single_day',
            ]);
        }
    }

    private function seedServices(int $count, array $extra = []): void
    {
        for ($i = 0; $i < $count; $i++) {
            SpPricing::create(array_merge([
                'service_provider_id' => $this->provider->id,
                'service_type' => 'guide',
                'price' => 1000,
                'unit' => 'per day',
            ], $extra));
        }
    }

    public function test_a_provider_can_add_up_to_the_cap(): void
    {
        $this->seedExperiences(9);
        $this->actingAs($this->provider->user);

        $this->ajax($this->experiencePayload(['name' => 'The Tenth']))->assertOk();
        $this->assertSame(10, Experience::ownedBy($this->provider->id)->count());
    }

    public function test_the_eleventh_experience_is_refused(): void
    {
        $this->seedExperiences(10);
        $this->actingAs($this->provider->user);

        $this->ajax($this->experiencePayload(['name' => 'One Too Many']))
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'You have reached the limit of 10 experiences. Please contact HECO if you need to list more.']);

        $this->assertDatabaseMissing('experiences', ['name' => 'One Too Many']);
    }

    /**
     * Being at the cap must not trap a provider inside their own listings. The
     * seeded rows are approved, so the edit parks as a revision rather than
     * touching the live row — what matters here is that it is not refused.
     */
    public function test_editing_an_existing_listing_still_works_at_the_cap(): void
    {
        $this->seedExperiences(10);
        $existing = Experience::ownedBy($this->provider->id)->first();
        $this->actingAs($this->provider->user);

        $this->ajax($this->experiencePayload([
            'id' => $existing->id,
            'name' => 'Renamed At The Cap',
        ]))->assertOk();

        $this->assertNotNull($existing->fresh()->pending_changes);
    }

    /**
     * An experience can never be deleted — it may sit inside a booked trip, so
     * it is only hidden. If rejections consumed slots permanently, ten refusals
     * would lock a provider out with no way back.
     */
    public function test_rejected_listings_do_not_consume_a_slot(): void
    {
        $this->seedExperiences(10);
        Experience::ownedBy($this->provider->id)->limit(2)
            ->update(['approval_status' => 'rejected']);

        $this->actingAs($this->provider->user);
        $this->ajax($this->experiencePayload(['name' => 'Back Under The Cap']))->assertOk();

        $this->assertDatabaseHas('experiences', ['name' => 'Back Under The Cap']);
    }

    /** Hiding a listing is not a delete, so it must not free a slot either. */
    public function test_hiding_a_listing_does_not_free_a_slot(): void
    {
        $this->seedExperiences(10);
        $this->actingAs($this->provider->user);
        $hidden = Experience::ownedBy($this->provider->id)->first();

        $this->ajax(['delete_sp_experience' => 1, 'id' => $hidden->id])->assertOk();
        $this->assertFalse((bool) $hidden->fresh()->is_active);

        $this->ajax($this->experiencePayload(['name' => 'Still Blocked']))->assertStatus(422);
    }

    public function test_the_experience_cap_does_not_touch_the_rate_card(): void
    {
        $this->seedExperiences(10);
        $this->actingAs($this->provider->user);

        // Ten experiences is the ceiling for experiences and nothing else.
        $this->ajax($this->servicePayload())->assertOk();
        $this->assertSame(1, SpPricing::where('service_provider_id', $this->provider->id)->count());
    }

    /**
     * Rates are not capped at all. The limit exists so HCT is not handed an
     * unbounded catalogue of experiences to review; a supplier's rate card is a
     * different thing, and a taxi operator with several vehicles priced for
     * both plains and hills passes ten without doing anything unusual.
     */
    public function test_a_rate_card_is_not_capped(): void
    {
        $this->seedServices(10);
        $this->actingAs($this->provider->user);

        $this->ajax($this->servicePayload(['specialties' => 'The eleventh rate']))->assertOk();

        $this->assertSame(11, SpPricing::where('service_provider_id', $this->provider->id)->count());
    }

    /** The cap protects HCT, so it must not be applied to HCT. */
    public function test_hct_is_not_capped(): void
    {
        $this->seedServices(10);
        $admin = User::create([
            'full_name' => 'HCT Admin',
            'email' => 'hct@example.test',
            'password' => 'password',
            'user_role' => 'administrator',
            'status' => 'active',
        ]);

        $this->actingAs($admin);
        $this->ajax($this->servicePayload(['provider_id' => $this->provider->id]))->assertOk();

        $this->assertSame(11, SpPricing::where('service_provider_id', $this->provider->id)->count());
    }

    /** The ceiling is a setting so HCT can lift it without a deploy. */
    public function test_the_cap_is_read_from_settings(): void
    {
        Setting::setValue('max_experiences_per_provider', 2, 'providers');
        $this->seedExperiences(2);
        $this->actingAs($this->provider->user);

        $this->ajax($this->experiencePayload(['name' => 'Blocked At Two']))->assertStatus(422);

        Setting::setValue('max_experiences_per_provider', 3, 'providers');
        $this->ajax($this->experiencePayload(['name' => 'Allowed At Three']))->assertOk();
    }

    /**
     * The host has to see the ceiling before filling in a form they cannot
     * save, and HCT changing the setting must move what they see.
     */
    public function test_the_experiences_page_shows_the_cap(): void
    {
        $this->actingAs($this->provider->user)
            ->get("http://{$this->portal}/sp/experiences")
            ->assertOk()
            ->assertSee('SP_EXP_CAP = 10', false);

        Setting::setValue('max_experiences_per_provider', 25, 'providers');
        $this->actingAs($this->provider->user)
            ->get("http://{$this->portal}/sp/experiences")
            ->assertOk()
            ->assertSee('SP_EXP_CAP = 25', false);
    }

    /** 0 is the escape hatch for a provider who genuinely has no limit. */
    public function test_a_zero_cap_means_unlimited(): void
    {
        Setting::setValue('max_experiences_per_provider', 0, 'providers');
        $this->seedExperiences(12);
        $this->actingAs($this->provider->user);

        $this->ajax($this->experiencePayload(['name' => 'No Ceiling']))->assertOk();
    }
}
