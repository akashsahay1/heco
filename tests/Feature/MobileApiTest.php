<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Experience;
use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The bearer-token mobile API (routes/api.php).
 *
 * These cover the two things a hand-rolled token layer has to get right —
 * that a token is required and actually identifies the right account — plus
 * proof that the bridge into AjaxController preserves its authorization rules
 * rather than quietly bypassing them.
 */
class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    protected Region $region;
    protected User $ospUser;
    protected ServiceProvider $osp;
    protected string $token;
    /** Only an HLH hosts experiences, so authoring is exercised through this one. */
    protected User $hlhUser;
    protected ServiceProvider $hlh;
    protected string $hostToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->region = Region::create([
            'name' => 'Tirthan Valley',
            'slug' => 'tirthan-valley',
            'country' => 'India',
            'is_active' => true,
        ]);

        $this->ospUser = User::create([
            'full_name' => 'Mountain View',
            'email' => 'osp@example.test',
            'password' => Hash::make('secret123'),
            'user_role' => 'osp',
            'status' => 'active',
        ]);

        $this->osp = ServiceProvider::create([
            'user_id' => $this->ospUser->id,
            'provider_type' => 'osp',
            'name' => 'Mountain View Hotel',
            'email' => 'osp@example.test',
            'phone_1' => '9000000000',
            'region_id' => $this->region->id,
            'status' => 'approved',
        ]);

        [, $this->token] = ApiToken::issueFor($this->ospUser, 'test-device');

        $this->hlhUser = User::create([
            'full_name' => 'Tirthan Host',
            'email' => 'host@example.test',
            'password' => Hash::make('secret123'),
            'user_role' => 'hlh',
            'status' => 'active',
        ]);

        $this->hlh = ServiceProvider::create([
            'user_id' => $this->hlhUser->id,
            'provider_type' => 'hlh',
            'name' => 'Tirthan Eco Retreat',
            'email' => 'host@example.test',
            'phone_1' => '9000000002',
            'region_id' => $this->region->id,
            'status' => 'approved',
        ]);

        [, $this->hostToken] = ApiToken::issueFor($this->hlhUser, 'host-device');
    }

    private function authed(array $headers = []): array
    {
        return array_merge(['Authorization' => 'Bearer ' . $this->token], $headers);
    }

    /** Bearer headers for the HLH host — the only provider that may author. */
    private function hostAuthed(array $headers = []): array
    {
        return array_merge(['Authorization' => 'Bearer ' . $this->hostToken], $headers);
    }

    // ── Auth ─────────────────────────────────────────────────────────────

    public function test_login_returns_a_token_and_the_provider(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'osp@example.test',
            'password' => 'secret123',
        ])->assertOk();

        $access = $response->json('access_token');
        $this->assertNotEmpty($access);
        $this->assertNotEmpty($response->json('refresh_token'));
        $this->assertSame('Bearer', $response->json('token_type'));
        $this->assertSame('Mountain View Hotel', $response->json('provider.name'));
        $this->assertSame('osp', $response->json('provider.provider_type'));

        // Only the hash is stored.
        $this->assertDatabaseMissing('api_tokens', ['token_hash' => $access]);
        $this->assertDatabaseHas('api_tokens', [
            'token_hash' => ApiToken::hashFor($access),
            'name' => ApiToken::ACCESS,
        ]);

        // The issued access token actually works.
        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer ' . $access])->assertOk();
    }

    // ── Signup (password chosen on the form) ─────────────────────────────

    private function submitApplication(): string
    {
        $res = $this->postJson('/api/v1/providers/applications', [
            'provider_type' => 'hlh',
            'name' => 'Riverside Homestay',
            'contact_person' => 'Neha',
            'email' => 'neha@example.test',
            'phone_1' => '9811122233',
            'region_id' => $this->region->id,
            'services_offered' => ['Accommodation'],
            'description' => 'A riverside homestay with home-cooked meals.',
            'password' => 'Passw0rd!',
            'password_confirmation' => 'Passw0rd!',
        ])->assertOk();

        return (string) $res->json('redirect');
    }

    public function test_mobile_signup_uploads_documents(): void
    {
        Mail::fake();
        \Illuminate\Support\Facades\Storage::fake('public');

        $this->post('/api/v1/providers/applications', [
            'provider_type' => 'hlh',
            'name' => 'Riverside Homestay',
            'email' => 'neha@example.test',
            'phone_1' => '9811122233',
            'region_id' => $this->region->id,
            'services_offered' => ['Accommodation'],
            'password' => 'Passw0rd!',
            'password_confirmation' => 'Passw0rd!',
            'documents' => [\Illuminate\Http\UploadedFile::fake()->image('id.jpg')],
            'document_labels' => ['Government ID'],
        ])->assertOk();

        $provider = ServiceProvider::where('email', 'neha@example.test')->firstOrFail();
        $this->assertCount(1, $provider->documents);
        $this->assertSame('Government ID', $provider->documents[0]['label']);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($provider->documents[0]['path']);
    }

    // ── In-app password reset (code, not a web link) ─────────────────────

    private function requestResetCode(string $email): array
    {
        Mail::fake();
        $res = $this->postJson('/api/v1/auth/forgot-password', ['email' => $email])->assertOk();

        $otp = null;
        Mail::assertSent(\App\Mail\PasswordResetOtpEmail::class, function ($mail) use (&$otp) {
            $otp = $mail->otp;
            return true;
        });

        return [$res->json('verification'), $otp];
    }

    public function test_reset_with_the_emailed_code_sets_the_password_and_signs_in(): void
    {
        [$verification, $otp] = $this->requestResetCode('osp@example.test');

        $res = $this->postJson('/api/v1/auth/reset-password', [
            'verification' => $verification,
            'otp' => $otp,
            'password' => 'Newpass1!',
        ])->assertOk();

        $res->assertJsonStructure(['access_token', 'refresh_token', 'provider']);
        $this->assertTrue(Hash::check('Newpass1!', $this->ospUser->fresh()->password));

        // The new token works and the old password no longer does.
        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer ' . $res->json('access_token')])
            ->assertOk();
        $this->postJson('/api/v1/auth/login', [
            'email' => 'osp@example.test',
            'password' => 'secret123',
        ])->assertStatus(422);
    }

    public function test_reset_rejects_a_wrong_code(): void
    {
        [$verification, $otp] = $this->requestResetCode('osp@example.test');
        $wrong = $otp === '000000' ? '111111' : '000000';

        $this->postJson('/api/v1/auth/reset-password', [
            'verification' => $verification,
            'otp' => $wrong,
            'password' => 'Newpass1!',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('secret123', $this->ospUser->fresh()->password));
    }

    /** An unknown email must look exactly like a known one. */
    public function test_forgot_password_does_not_reveal_whether_an_email_exists(): void
    {
        Mail::fake();
        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'osp@example.test'])->assertOk();
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.test'])->assertOk();

        $this->assertSame($known->json('message'), $unknown->json('message'));
        $this->assertNotEmpty($unknown->json('verification'));

        // ...but the decoy token can never reset anything.
        $this->postJson('/api/v1/auth/reset-password', [
            'verification' => $unknown->json('verification'),
            'otp' => '123456',
            'password' => 'Newpass1!',
        ])->assertStatus(422);
    }

    public function test_refresh_rotates_the_pair_and_consumes_the_old_one(): void
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'osp@example.test',
            'password' => 'secret123',
        ])->assertOk();

        $oldRefresh = $login->json('refresh_token');

        $refreshed = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $oldRefresh])
            ->assertOk();

        $newAccess = $refreshed->json('access_token');
        $this->assertNotEmpty($newAccess);
        $this->assertNotSame($oldRefresh, $refreshed->json('refresh_token'));

        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer ' . $newAccess])->assertOk();

        // The spent refresh token is gone.
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $oldRefresh])->assertStatus(401);
    }

    /** A refresh token must not authenticate ordinary calls. */
    public function test_a_refresh_token_cannot_be_used_as_a_bearer_token(): void
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'osp@example.test',
            'password' => 'secret123',
        ])->assertOk();

        $this->getJson('/api/v1/auth/me', [
            'Authorization' => 'Bearer ' . $login->json('refresh_token'),
        ])->assertStatus(401);
    }

    public function test_login_rejects_a_wrong_password(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'osp@example.test',
            'password' => 'not-the-password',
        ])->assertStatus(422);
    }

    public function test_login_rejects_non_providers(): void
    {
        User::create([
            'full_name' => 'Trav',
            'email' => 'trav@example.test',
            'password' => Hash::make('secret123'),
            'user_role' => 'traveller',
            'status' => 'active',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'trav@example.test',
            'password' => 'secret123',
        ])->assertStatus(403);
    }

    public function test_protected_routes_require_a_token(): void
    {
        $this->getJson('/api/v1/provider/profile')->assertStatus(401);
        $this->getJson('/api/v1/provider/pricing')->assertStatus(401);
        $this->getJson('/api/v1/provider/experiences')->assertStatus(401);
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        $this->getJson('/api/v1/provider/profile', ['Authorization' => 'Bearer nonsense'])
            ->assertStatus(401);
    }

    public function test_an_expired_token_is_rejected_and_discarded(): void
    {
        ApiToken::query()->update(['expires_at' => now()->subDay()]);

        $this->getJson('/api/v1/provider/profile', $this->authed())->assertStatus(401);
        // Only the presented token is discarded — other devices are untouched.
        $this->assertSame(0, ApiToken::where('user_id', $this->ospUser->id)->count());
    }

    public function test_me_returns_the_token_owner(): void
    {
        $this->getJson('/api/v1/auth/me', $this->authed())
            ->assertOk()
            ->assertJsonPath('provider.name', 'Mountain View Hotel');
    }

    public function test_logout_revokes_only_this_token(): void
    {
        [, $other] = ApiToken::issueFor($this->ospUser, 'second-device');

        $this->postJson('/api/v1/auth/logout', [], $this->authed())->assertOk();

        $this->getJson('/api/v1/provider/profile', $this->authed())->assertStatus(401);
        $this->getJson('/api/v1/provider/profile', ['Authorization' => 'Bearer ' . $other])
            ->assertOk();
    }

    // ── Reference data ───────────────────────────────────────────────────

    public function test_reference_data_is_public_and_carries_the_option_sets(): void
    {
        $response = $this->getJson('/api/v1/reference')->assertOk();

        $this->assertSame('Tirthan Valley', $response->json('regions.0.name'));
        foreach (['service_type', 'guide_preference', 'room_category', 'meal_plan'] as $type) {
            $this->assertArrayHasKey($type, $response->json('system_lists'));
        }
    }

    // ── Bridge into AjaxController ───────────────────────────────────────

    public function test_profile_update_writes_through_to_the_provider(): void
    {
        $this->putJson('/api/v1/provider/profile', [
            'name' => 'Mountain View Hotel & Camps',
            'phone_1' => '9111111111',
            'guide_types' => ['Local Guide', 'English-speaking'],
        ], $this->authed())->assertOk();

        $fresh = $this->osp->fresh();
        $this->assertSame('Mountain View Hotel & Camps', $fresh->name);
        $this->assertSame(['Local Guide', 'English-speaking'], $fresh->guide_types);
        // The portal stamps who edited — the bridge must not lose that.
        $this->assertSame('provider', $fresh->last_updated_by_role);
    }

    /** The app renders the response, so it must be the saved record. */
    public function test_profile_update_returns_the_saved_record(): void
    {
        $response = $this->putJson('/api/v1/provider/profile', [
            'name' => 'Renamed Hotel',
            'contact_person' => 'Pradeep K',
            'phone_1' => '9222222222',
            'bank_name' => 'HDFC Bank',
            'bank_ifsc' => 'HDFC0001234',
            'upi' => 'mountainview@hdfcbank',
            'services_offered' => ['Accommodation', 'Transport'],
        ], $this->authed())->assertOk();

        $this->assertSame('Renamed Hotel', $response->json('provider.name'));
        $this->assertSame('Pradeep K', $response->json('provider.contact_person'));
        $this->assertSame('HDFC Bank', $response->json('provider.bank.bank_name'));
        $this->assertSame(['Accommodation', 'Transport'], $response->json('provider.services_offered'));

        $this->assertDatabaseHas('service_providers', [
            'id' => $this->osp->id,
            'name' => 'Renamed Hotel',
            'upi' => 'mountainview@hdfcbank',
        ]);
    }

    /** A rejected update must not report success back to the app. */
    public function test_profile_update_rejects_a_missing_name(): void
    {
        $this->putJson('/api/v1/provider/profile', [
            'contact_person' => 'No name supplied',
        ], $this->authed())->assertStatus(422);

        $this->assertSame('Mountain View Hotel', $this->osp->fresh()->name);
    }

    public function test_experiences_round_trip_through_the_api(): void
    {
        $this->postJson('/api/v1/provider/experiences', [
            'name' => 'Riverside Forest Walk',
            'region_id' => $this->region->id,
            'type' => 'Nature',
            'short_description' => 'A slow morning walk.',
            'duration_type' => 'multi_day',
            'experience_days' => [
                ['day_number' => 1, 'title' => 'Arrive', 'inclusions' => ['dinner']],
                ['day_number' => 2, 'title' => 'Walk', 'inclusions' => ['breakfast', 'guide']],
            ],
        ], $this->hostAuthed())->assertOk();

        $experience = Experience::firstWhere('name', 'Riverside Forest Walk');
        $this->assertSame($this->hlh->id, (int) $experience->owner_provider_id);
        $this->assertSame('hlh', $experience->owner_type);
        $this->assertCount(2, $experience->days);

        $listed = $this->getJson('/api/v1/provider/experiences', $this->hostAuthed())->assertOk();
        $this->assertSame('Riverside Forest Walk', $listed->json('experiences.0.name'));
    }

    /** Photos have to survive the hop into the AJAX dispatcher. */
    public function test_an_experience_photo_uploads_through_the_api(): void
    {
        $this->post('/api/v1/provider/experiences', [
            'name' => 'Forest Trail With A Photo',
            'region_id' => $this->region->id,
            'type' => 'Trek',
            'short_description' => 'A walk in the woods.',
            'duration_type' => 'single_day',
            'card_image' => \Illuminate\Http\UploadedFile::fake()->image('trail.jpg', 900, 600),
        ], $this->hostAuthed())->assertOk();

        $experience = Experience::firstWhere('name', 'Forest Trail With A Photo');
        $this->assertNotEmpty($experience->card_image);
    }

    /**
     * The app swaps a regional partner's Services tab for their region, so the
     * endpoint behind it has to exist — and stay shut to everyone else.
     */
    public function test_the_region_overview_is_for_regional_partners_only(): void
    {
        $this->getJson('/api/v1/provider/region/providers', $this->authed())
            ->assertStatus(403);
        $this->getJson('/api/v1/provider/region/providers', $this->hostAuthed())
            ->assertStatus(403);

        $hrpUser = User::create([
            'full_name' => 'Regional Partner',
            'email' => 'partner@example.test',
            'password' => Hash::make('secret123'),
            'user_role' => 'hrp',
            'status' => 'active',
        ]);
        ServiceProvider::create([
            'user_id' => $hrpUser->id,
            'provider_type' => 'hrp',
            'provider_types' => ['hrp'],
            'name' => 'Tirthan Regional Partner',
            'email' => 'partner@example.test',
            'phone_1' => '9000000003',
            'region_id' => $this->region->id,
            'status' => 'approved',
        ]);
        [, $partnerToken] = ApiToken::issueFor($hrpUser, 'partner-device');

        $response = $this->getJson(
            '/api/v1/provider/region/providers',
            ['Authorization' => 'Bearer ' . $partnerToken],
        )->assertOk();

        // The OSP and the HLH from setUp both sit in this region.
        $names = collect($response->json('providers'))->pluck('name');
        $this->assertEqualsCanonicalizing(
            ['Mountain View Hotel', 'Tirthan Eco Retreat'],
            $names->all(),
        );
    }

    /** The bridge must not become a way around ACTION_LEVELS. */
    public function test_an_hrp_token_cannot_author_experiences(): void
    {
        $hrpUser = User::create([
            'full_name' => 'HRP',
            'email' => 'hrp@example.test',
            'password' => Hash::make('secret123'),
            'user_role' => 'hrp',
            'status' => 'active',
        ]);
        ServiceProvider::create([
            'user_id' => $hrpUser->id,
            'provider_type' => 'hrp',
            'name' => 'HRP Partner',
            'email' => 'hrp@example.test',
            'phone_1' => '9000000001',
            'region_id' => $this->region->id,
            'status' => 'approved',
        ]);
        [, $hrpToken] = ApiToken::issueFor($hrpUser);

        $this->postJson('/api/v1/provider/experiences', [
            'name' => 'Should Fail',
            'region_id' => $this->region->id,
            'type' => 'Nature',
            'short_description' => 'Nope.',
            'duration_type' => 'single_day',
        ], ['Authorization' => 'Bearer ' . $hrpToken])->assertStatus(403);

        // An OSP supplies services into an experience but never hosts one.
        $this->postJson('/api/v1/provider/experiences', [
            'name' => 'Should Also Fail',
            'region_id' => $this->region->id,
            'type' => 'Nature',
            'short_description' => 'Nope.',
            'duration_type' => 'single_day',
        ], $this->authed())->assertStatus(403);
        $this->assertDatabaseMissing('experiences', ['name' => 'Should Also Fail']);

        $this->assertDatabaseMissing('experiences', ['name' => 'Should Fail']);
    }

    /**
     * The app builds its booking cards from this payload, so the trip context
     * a provider needs to prepare must be present — without traveller identity.
     */
    public function test_bookings_carry_the_trip_context_the_app_needs(): void
    {
        $traveller = User::create([
            'full_name' => 'Guest',
            'email' => 'guest@example.test',
            'password' => Hash::make('secret123'),
            'user_role' => 'traveller',
            'status' => 'active',
        ]);

        $trip = \App\Models\Trip::create([
            'trip_id' => 'HECO-9001',
            'user_id' => $traveller->id,
            'trip_name' => 'Tirthan Slow Escape',
            'status' => 'confirmed',
            'stage' => 'open',
            'adults' => 2,
            'children' => 1,
            'infants' => 0,
            'traveller_origin' => 'Delhi',
            'pickup_location' => 'Aut Tunnel',
            'drop_location' => 'Bhuntar Airport',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-04',
        ]);
        \App\Models\TripRegion::create(['trip_id' => $trip->id, 'region_id' => $this->region->id]);

        $day = \App\Models\TripDay::create([
            'trip_id' => $trip->id,
            'day_number' => 1,
            'date' => '2026-09-01',
        ]);
        \App\Models\TripDayService::create([
            'trip_day_id' => $day->id,
            'service_provider_id' => $this->osp->id,
            'service_type' => 'accommodation',
            'description' => 'Riverside Cottage',
        ]);

        $row = collect($this->getJson('/api/v1/provider/bookings', $this->authed())
            ->assertOk()
            ->json('trips'))
            ->firstWhere('trip_id', 'HECO-9001');

        $this->assertNotNull($row);
        $this->assertSame('Tirthan Slow Escape', $row['trip_name']);
        $this->assertSame('Tirthan Valley', $row['region']);
        $this->assertSame(2, $row['adults']);
        $this->assertSame(1, $row['children']);
        $this->assertSame('Delhi', $row['traveller_origin']);
        $this->assertSame('Aut Tunnel', $row['pickup_location']);
        $this->assertSame('Riverside Cottage', $row['services']);
        $this->assertSame('1', $row['days']);

        // Traveller identity stays out of a provider's hands.
        $this->assertArrayNotHasKey('user_id', $row);
        $this->assertStringNotContainsString('guest@example.test', json_encode($row));
    }

    public function test_availability_block_and_unblock_round_trip(): void
    {
        $this->postJson('/api/v1/provider/availability/block', [
            'dates' => ['2026-09-10', '2026-09-11'],
            'notes' => 'Closed for repairs',
        ], $this->authed())->assertOk();

        $this->assertDatabaseHas('sp_availability', [
            'service_provider_id' => $this->osp->id,
            'date' => '2026-09-10 00:00:00',
            'status' => 'blocked',
        ]);

        $calendar = $this->getJson('/api/v1/provider/availability?year=2026&month=9', $this->authed())
            ->assertOk()
            ->json('calendar');

        $this->assertSame('blocked', $calendar['2026-09-10']['status']);
        $this->assertSame('available', $calendar['2026-09-12']['status']);

        $this->postJson('/api/v1/provider/availability/unblock', [
            'dates' => ['2026-09-10'],
        ], $this->authed())->assertOk();

        $this->assertDatabaseMissing('sp_availability', [
            'service_provider_id' => $this->osp->id,
            'date' => '2026-09-10 00:00:00',
            'status' => 'blocked',
        ]);
    }

    public function test_pricing_saves_and_lists_for_the_token_owner(): void
    {
        $this->postJson('/api/v1/provider/pricing', [
            'service_type' => 'transport',
            'vehicle_type' => 'SUV (Innova/Crysta)',
            'unit' => 'per km',
            'price' => 25,
        ], $this->authed())->assertOk();

        $this->assertDatabaseHas('sp_pricing', [
            'service_provider_id' => $this->osp->id,
            'service_type' => 'transport',
            'price' => 25,
        ]);

        $this->getJson('/api/v1/provider/pricing', $this->authed())->assertOk();
    }

    /** A provider may not act on someone else's rate card via the API. */
    public function test_pricing_is_scoped_to_the_token_owner(): void
    {
        $otherUser = User::create([
            'full_name' => 'Other',
            'email' => 'other@example.test',
            'password' => Hash::make('secret123'),
            'user_role' => 'osp',
            'status' => 'active',
        ]);
        $other = ServiceProvider::create([
            'user_id' => $otherUser->id,
            'provider_type' => 'osp',
            'name' => 'Other Provider',
            'email' => 'other@example.test',
            'phone_1' => '9000000002',
            'region_id' => $this->region->id,
            'status' => 'approved',
        ]);

        // Even when a provider_id is supplied, the row lands on the caller.
        $this->postJson('/api/v1/provider/pricing', [
            'provider_id' => $other->id,
            'service_type' => 'guide',
            'unit' => 'per day',
            'price' => 3000,
        ], $this->authed())->assertOk();

        $this->assertDatabaseHas('sp_pricing', [
            'service_provider_id' => $this->osp->id,
            'service_type' => 'guide',
        ]);
        $this->assertDatabaseMissing('sp_pricing', ['service_provider_id' => $other->id]);
    }
}
