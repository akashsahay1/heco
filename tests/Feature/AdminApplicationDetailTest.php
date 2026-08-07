<?php

namespace Tests\Feature;

use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HCT approves or rejects an application from one panel, so that panel has to
 * carry what the decision rests on.
 *
 * It was showing contact, business, address and documents — but not the
 * languages spoken, not what the applicant offers, not whether they have a
 * business at all, not whether they agreed to be messaged, and only the first
 * of the roles they hold. All of it was in the database and none of it reached
 * the screen, so an application was being judged on half its content.
 */
class AdminApplicationDetailTest extends TestCase
{
    use RefreshDatabase;

    protected string $admin;
    protected User $hct;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = config('app.admin_domain');

        $this->hct = User::create([
            'full_name' => 'HCT Admin',
            'email' => 'hct@example.test',
            'password' => 'password',
            'user_role' => 'administrator',
            'status' => 'active',
        ]);
    }

    private function applicant(): ServiceProvider
    {
        $region = Region::create([
            'name' => 'Garhwal',
            'slug' => 'garhwal',
            'country' => 'India',
            'is_active' => true,
        ]);

        $user = User::create([
            'full_name' => 'Aarav Mehta',
            'email' => 'aarav@example.test',
            'password' => 'password',
            'user_role' => 'provider',
            'status' => 'active',
        ]);

        return ServiceProvider::create([
            'user_id' => $user->id,
            'provider_type' => 'hlh',
            'provider_types' => ['hlh', 'osp'],
            'name' => 'Aarav Mehta',
            'contact_person' => 'Aarav Mehta',
            'email' => 'aarav@example.test',
            'phone_1' => '9812345670',
            'region_id' => $region->id,
            'status' => 'pending',
            'speaks_english' => true,
            'speaks_hindi' => true,
            'other_languages' => 'Garhwali, Nepali',
            'has_business' => false,
            'experience_categories' => ['Experiential accommodation'],
            'service_categories' => ['Taxi services', 'Rental'],
            'other_services' => 'Airport pickup from Bhuntar',
            'contact_by_email' => true,
            'contact_by_whatsapp' => false,
        ]);
    }

    private function applications(): array
    {
        $response = $this->actingAs($this->hct)
            ->post("http://{$this->admin}/ajax", [
                'get_provider_applications' => 1,
                'status' => 'pending',
            ])
            ->assertOk();

        return $response->json('data')[0];
    }

    public function test_the_panel_receives_every_answer_the_decision_rests_on(): void
    {
        $this->applicant();
        $row = $this->applications();

        // Both roles, not just the first.
        $this->assertSame(['hlh', 'osp'], $row['provider_types']);

        // Which travellers they can host.
        $this->assertTrue((bool) $row['speaks_english']);
        $this->assertTrue((bool) $row['speaks_hindi']);
        $this->assertSame('Garhwali, Nepali', $row['other_languages']);

        // What they applied to do — the substance of the application.
        $this->assertSame(['Experiential accommodation'], $row['experience_categories']);
        $this->assertSame(['Taxi services', 'Rental'], $row['service_categories']);
        $this->assertSame('Airport pickup from Bhuntar', $row['other_services']);

        // "No" is an answer, and has to be distinguishable from "never asked".
        $this->assertArrayHasKey('has_business', $row);
        $this->assertNotNull($row['has_business']);
        $this->assertFalse((bool) $row['has_business']);

        // A declined channel matters more than an accepted one.
        $this->assertTrue((bool) $row['contact_by_email']);
        $this->assertFalse((bool) $row['contact_by_whatsapp']);
    }

    /** The panel is built in the blade, so the fields have to be read there. */
    public function test_the_panel_actually_renders_those_answers(): void
    {
        $page = $this->actingAs($this->hct)
            ->get("http://{$this->admin}/provider-applications")
            ->assertOk();

        foreach ([
            'provider_types',
            'speaks_english', 'speaks_hindi', 'other_languages',
            'experience_categories', 'service_categories', 'other_services',
            'has_business',
            'contact_by_email', 'contact_by_whatsapp',
        ] as $field) {
            $page->assertSee("app.$field", false);
        }
    }

    /** The client's own spelling, and ours everywhere else. */
    public function test_the_host_role_is_named_as_the_client_names_it(): void
    {
        $this->actingAs($this->hct)
            ->get("http://{$this->admin}/provider-applications")
            ->assertOk()
            ->assertSee('Heco Local Host', false)
            ->assertDontSee('HECO Local Host', false);
    }
}
