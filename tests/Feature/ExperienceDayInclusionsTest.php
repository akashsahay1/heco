<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `experience_days.inclusions` is cast to array, and every reader iterates it —
 * the admin table, the traveller detail page, the app. A string stored here
 * does not show up as one odd row: it throws inside the render loop and takes
 * the whole screen with it. The admin Experiences page sat on "Loading..."
 * forever because of exactly one such row.
 *
 * So the write path normalises, rather than trusting the caller.
 */
class ExperienceDayInclusionsTest extends TestCase
{
    use RefreshDatabase;

    protected string $portal;
    protected ServiceProvider $hlh;
    protected Region $region;

    protected function setUp(): void
    {
        parent::setUp();
        $this->portal = config('app.portal_domain');
        $this->region = Region::create([
            'name' => 'Kumaon', 'slug' => 'kumaon', 'country' => 'India', 'is_active' => true,
        ]);
        $user = User::create([
            'full_name' => 'Host', 'email' => 'host@example.test',
            'password' => 'password', 'user_role' => 'hlh', 'status' => 'active',
        ]);
        $this->hlh = ServiceProvider::create([
            'user_id' => $user->id, 'provider_type' => 'hlh', 'provider_types' => ['hlh'],
            'name' => 'Munsiyari Homestay', 'email' => 'host@example.test',
            'phone_1' => '9000000000', 'region_id' => $this->region->id, 'status' => 'approved',
        ]);
    }

    private function saveWithInclusions(mixed $inclusions): Experience
    {
        $this->actingAs($this->hlh->user)
            ->post("http://{$this->portal}/ajax", [
                'save_sp_experience' => 1,
                'name' => 'Khaliya Top Trek',
                'region_id' => $this->region->id,
                'type' => 'Trekking',
                'category' => 'Guided Cultural & Outdoor Activities',
                'short_description' => 'A meadow walk.',
                'duration_type' => 'multi_day',
                'experience_days' => [
                    ['day_number' => 1, 'title' => 'To the base', 'inclusions' => $inclusions],
                ],
            ])->assertOk();

        return Experience::firstWhere('name', 'Khaliya Top Trek');
    }

    public function test_a_list_of_inclusions_is_stored_as_a_list(): void
    {
        $experience = $this->saveWithInclusions(['Guide', 'Breakfast', 'Camp']);

        $this->assertSame(['Guide', 'Breakfast', 'Camp'], $experience->days->first()->inclusions);
    }

    public function test_comma_separated_text_becomes_a_list(): void
    {
        $experience = $this->saveWithInclusions('Guide, packed lunch, camp');

        $this->assertSame(
            ['Guide', 'packed lunch', 'camp'],
            $experience->days->first()->inclusions,
            'a string here used to reach every screen that iterates it',
        );
    }

    public function test_blank_entries_are_dropped(): void
    {
        $experience = $this->saveWithInclusions(['Guide', '', '  ', 'Camp']);

        $this->assertSame(['Guide', 'Camp'], $experience->days->first()->inclusions);
    }

    public function test_nothing_sent_is_an_empty_list_not_a_null(): void
    {
        $experience = $this->saveWithInclusions([]);

        $this->assertSame([], $experience->days->first()->inclusions);
    }
}
