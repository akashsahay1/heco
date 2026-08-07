<?php

namespace Tests\Feature;

use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * A picture for the provider.
 *
 * The app's profile header could always render one — ProviderAccount carries an
 * avatarUrl and AppAvatar draws it — but nothing supplied it, so every member
 * saw their initials. It lives on the provider, not the user: for a homestay it
 * is the house, for a regional partner it is them.
 */
class ProviderPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected string $portal;
    protected ServiceProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->portal = config('app.portal_domain');
        $region = Region::create([
            'name' => 'Kumaon', 'slug' => 'kumaon', 'country' => 'India', 'is_active' => true,
        ]);
        $user = User::create([
            'full_name' => 'Neema Rawat', 'email' => 'host@example.test',
            'password' => 'password', 'user_role' => 'provider', 'status' => 'active',
        ]);
        $this->provider = ServiceProvider::create([
            'user_id' => $user->id, 'provider_type' => 'hlh', 'provider_types' => ['hlh'],
            'name' => 'Munsiyari Homestay', 'email' => 'host@example.test',
            'phone_1' => '9000000000', 'region_id' => $region->id, 'status' => 'approved',
        ]);
    }

    private function save(array $payload)
    {
        return $this->actingAs($this->provider->user)
            ->post("http://{$this->portal}/ajax", array_merge([
                'update_sp_profile' => 1,
                'name' => 'Munsiyari Homestay',
            ], $payload));
    }

    public function test_a_provider_can_set_a_photo(): void
    {
        $res = $this->save(['photo' => UploadedFile::fake()->image('house.jpg', 800, 600)]);

        $res->assertOk()->assertJson(['success' => true]);
        $this->assertNotNull($this->provider->fresh()->photo);
    }

    public function test_the_photo_reaches_the_app_as_avatar_url(): void
    {
        $this->save(['photo' => UploadedFile::fake()->image('house.jpg')])->assertOk();

        $payload = \App\Http\Resources\ProviderAccountResource::make($this->provider->fresh());

        $this->assertSame($this->provider->fresh()->photo, $payload['avatar_url']);
    }

    public function test_a_provider_with_no_photo_sends_null_rather_than_an_empty_string(): void
    {
        $payload = \App\Http\Resources\ProviderAccountResource::make($this->provider);

        $this->assertNull($payload['avatar_url'], 'the app treats empty as "show initials"');
    }

    public function test_a_photo_can_be_removed(): void
    {
        $this->save(['photo' => UploadedFile::fake()->image('house.jpg')])->assertOk();
        $this->assertNotNull($this->provider->fresh()->photo);

        $this->save(['remove_photo' => 1])->assertOk();

        $this->assertNull($this->provider->fresh()->photo);
    }

    /**
     * A save that says nothing about the photo must leave it alone — the
     * profile form posts far more than this one field.
     */
    public function test_saving_other_fields_keeps_the_photo(): void
    {
        $this->save(['photo' => UploadedFile::fake()->image('house.jpg')])->assertOk();
        $before = $this->provider->fresh()->photo;

        $this->save(['contact_person' => 'Neema Rawat'])->assertOk();

        $this->assertSame($before, $this->provider->fresh()->photo);
    }

    public function test_a_file_that_is_not_an_image_is_refused(): void
    {
        $res = $this->save(['photo' => UploadedFile::fake()->create('rates.pdf', 40, 'application/pdf')]);

        $res->assertStatus(422);
        $this->assertNull($this->provider->fresh()->photo);
    }
}
