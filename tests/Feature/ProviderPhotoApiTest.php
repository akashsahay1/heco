<?php

namespace Tests\Feature;

use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use App\Models\ApiToken;
use Tests\TestCase;

/**
 * The app changes its picture through its own endpoint rather than the profile
 * PUT: PHP does not parse a multipart body on PUT, and a member changing their
 * photo should not have to re-send their bank details to do it.
 */
class ProviderPhotoApiTest extends TestCase
{
    use RefreshDatabase;

    protected ServiceProvider $provider;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $region = Region::create([
            'name' => 'Kumaon', 'slug' => 'kumaon', 'country' => 'India', 'is_active' => true,
        ]);
        $user = User::create([
            'full_name' => 'Neema Rawat', 'email' => 'host@example.test',
            'password' => 'password', 'user_role' => 'hlh', 'status' => 'active',
        ]);
        $this->provider = ServiceProvider::create([
            'user_id' => $user->id, 'provider_type' => 'hlh', 'provider_types' => ['hlh'],
            'name' => 'Munsiyari Homestay', 'email' => 'host@example.test',
            'phone_1' => '9000000000', 'region_id' => $region->id, 'status' => 'approved',
        ]);
        [, $this->token] = ApiToken::issueFor($user, 'test-device');
    }

    private function auth(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    public function test_the_app_can_upload_a_photo_and_gets_the_saved_account_back(): void
    {
        $res = $this->postJson('/api/v1/provider/profile/photo', [
            'photo' => UploadedFile::fake()->image('house.jpg', 900, 900),
        ], $this->auth());

        $res->assertOk()->assertJson(['success' => true]);

        $saved = $this->provider->fresh()->photo;
        $this->assertNotNull($saved);
        $this->assertSame($saved, $res->json('provider.avatar_url'));
    }

    public function test_the_app_can_clear_a_photo(): void
    {
        $this->postJson('/api/v1/provider/profile/photo', [
            'photo' => UploadedFile::fake()->image('house.jpg'),
        ], $this->auth())->assertOk();

        $res = $this->postJson('/api/v1/provider/profile/photo', ['remove_photo' => true], $this->auth());

        $res->assertOk();
        $this->assertNull($this->provider->fresh()->photo);
        $this->assertNull($res->json('provider.avatar_url'));
    }

    public function test_a_call_that_sends_nothing_is_refused_rather_than_silently_clearing(): void
    {
        $this->postJson('/api/v1/provider/profile/photo', [
            'photo' => UploadedFile::fake()->image('house.jpg'),
        ], $this->auth())->assertOk();
        $before = $this->provider->fresh()->photo;

        $this->postJson('/api/v1/provider/profile/photo', [], $this->auth())->assertStatus(422);

        $this->assertSame($before, $this->provider->fresh()->photo);
    }

    public function test_profile_reads_back_the_photo(): void
    {
        $this->postJson('/api/v1/provider/profile/photo', [
            'photo' => UploadedFile::fake()->image('house.jpg'),
        ], $this->auth())->assertOk();

        $this->getJson('/api/v1/provider/profile', $this->auth())
            ->assertOk()
            ->assertJsonPath('provider.avatar_url', $this->provider->fresh()->photo);
    }
}
