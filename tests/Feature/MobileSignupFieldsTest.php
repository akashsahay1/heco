<?php

namespace Tests\Feature;

use App\Models\Region;
use App\Models\ServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Everything the app's signup screens ask for has to survive the trip into the
 * database.
 *
 * The mobile API forwards an allow-list of fields into the ajax handler and
 * drops the rest without a word. Every question added to the signup screens for
 * the client's brief — the roles held, the languages spoken, whether there is a
 * business, what they offer, how they may be contacted — was being dropped
 * there. The app sent them, the handler could store them, and the boundary in
 * between threw them away. An application submitted from a phone said the
 * applicant spoke no languages and hosted nothing.
 *
 * Nothing failed and no error was shown, which is why this is pinned per field
 * rather than as one happy-path assertion.
 */
class MobileSignupFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected Region $region;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->region = Region::create([
            'name' => 'Jibhi',
            'slug' => 'jibhi',
            'country' => 'India',
            'is_active' => true,
        ]);
    }

    /** The payload the app posts, as SignupData.toDraftJson() builds it. */
    private function apply(array $overrides = []): ServiceProvider
    {
        $this->postJson('/api/v1/providers/applications', array_merge([
            'provider_type' => 'hlh',
            'provider_types' => ['hlh', 'osp'],
            'name' => 'Tirthan Valley Homestay',
            'contact_person' => 'Aarav Mehta',
            'email' => 'aarav@example.test',
            'phone_1' => '9812345670',
            'phone_2' => '9812345671',
            'region_id' => $this->region->id,
            'address' => 'House 14, Gushaini Road',
            'city' => 'Banjar',
            'postal_code' => '175123',
            'country' => 'India',
            'speaks_english' => true,
            'speaks_hindi' => true,
            'other_languages' => 'Pahari, Ladakhi',
            'has_business' => true,
            'business_type' => 'Sole proprietor',
            'registration_number' => 'UDYAM-HP-12-0001234',
            'year_established' => '2016',
            'experience_categories' => ['Experiential accommodation'],
            'service_categories' => ['Taxi services'],
            'other_services' => 'Airport pickup from Bhuntar',
            'contact_by_email' => true,
            'contact_by_whatsapp' => false,
            'description' => 'Nine years of hosting travellers in my village.',
            'password' => 'Str0ng!passphrase',
            'password_confirmation' => 'Str0ng!passphrase',
        ], $overrides))->assertOk();

        return ServiceProvider::where('email', $overrides['email'] ?? 'aarav@example.test')
            ->firstOrFail();
    }

    public function test_the_roles_held_are_all_saved(): void
    {
        $sp = $this->apply();

        $this->assertSame(['hlh', 'osp'], $sp->provider_types);
        $this->assertTrue($sp->isHost());
        $this->assertTrue($sp->suppliesServices());
    }

    public function test_the_languages_spoken_are_saved(): void
    {
        $sp = $this->apply();

        $this->assertTrue((bool) $sp->speaks_english);
        $this->assertTrue((bool) $sp->speaks_hindi);
        $this->assertSame('Pahari, Ladakhi', $sp->other_languages);
    }

    /** A "no" is an answer; only an unasked question is null. */
    public function test_the_business_answer_is_saved_either_way(): void
    {
        $yes = $this->apply();
        $this->assertTrue((bool) $yes->has_business);

        $no = $this->apply([
            'email' => 'nobiz@example.test',
            'has_business' => false,
        ]);
        $this->assertNotNull($no->has_business);
        $this->assertFalse((bool) $no->has_business);
    }

    public function test_what_they_offer_is_saved_per_role(): void
    {
        $sp = $this->apply();

        $this->assertSame(['Experiential accommodation'], $sp->experience_categories);
        $this->assertSame(['Taxi services'], $sp->service_categories);
        $this->assertSame('Airport pickup from Bhuntar', $sp->other_services);
    }

    /** Defaults are true, so a stated "no" is the case worth proving. */
    public function test_contact_permissions_are_saved_as_stated(): void
    {
        $sp = $this->apply();

        $this->assertTrue((bool) $sp->contact_by_email);
        $this->assertFalse(
            (bool) $sp->contact_by_whatsapp,
            'a declined channel must not fall back to the column default'
        );
    }

    /**
     * The client's brief is explicit that most members will not have a
     * business — "You don't need to own a business." The trading name is only
     * asked for behind the "yes" answer, so someone who says no never supplies
     * one, and the application used to be refused for a field they were never
     * shown. Nobody without a business could join at all.
     */
    public function test_someone_without_a_business_can_still_apply(): void
    {
        $sp = $this->apply([
            'email' => 'individual@example.test',
            'has_business' => false,
            'name' => '',
            'business_type' => '',
            'registration_number' => '',
            'year_established' => '',
        ]);

        $this->assertSame(
            'Aarav Mehta',
            $sp->name,
            'their own name is the name we know them by'
        );
        $this->assertFalse((bool) $sp->has_business);
    }

    /**
     * A regional partner sells nothing, so their background is the whole of
     * their application — "For HRPs, we'd rather collect information about
     * their background and skills." The app's role screen promises this is
     * asked; it has to arrive.
     */
    public function test_a_regional_partners_background_is_saved(): void
    {
        $sp = $this->apply([
            'email' => 'hrp@example.test',
            'provider_type' => 'hrp',
            'provider_types' => ['hrp'],
            'education_level' => "Bachelor's degree",
            'education_notes' => 'BA in Geography, HP University.',
            'english_level' => 'Conversational',
            'computer_skill_level' => 'Intermediate',
            'work_experience' => [
                ['role' => 'Field coordinator', 'organisation' => 'Himalayan Trust',
                 'years' => '2018-2023', 'description' => 'Ran village programmes.'],
                // A repeater always leaves one of these behind.
                ['role' => '', 'organisation' => '', 'years' => '', 'description' => ''],
            ],
            'causes_note' => 'Volunteer with a river clean-up group.',
            'community_note' => 'Born in Kinnaur; known to every panchayat here.',
        ]);

        $this->assertSame("Bachelor's degree", $sp->education_level);
        $this->assertSame('Conversational', $sp->english_level);
        $this->assertSame('Intermediate', $sp->computer_skill_level);
        $this->assertStringContainsString('river clean-up', $sp->causes_note);
        $this->assertStringContainsString('panchayat', $sp->community_note);

        $this->assertCount(1, $sp->work_experience, 'the blank row is dropped');
        $this->assertSame('Field coordinator', $sp->work_experience[0]['role']);
        $this->assertSame('Himalayan Trust', $sp->work_experience[0]['organisation']);
    }

    /** A host has no competences to record, and storing them would mislead. */
    public function test_a_host_does_not_pick_up_competences(): void
    {
        $sp = $this->apply([
            'email' => 'host@example.test',
            'provider_type' => 'hlh',
            'provider_types' => ['hlh'],
            'education_level' => "Master's degree or above",
            'english_level' => 'Fluent',
        ]);

        $this->assertNull($sp->education_level);
        $this->assertNull($sp->english_level);
    }

    /**
     * The portal's own web form posts straight to /ajax, so it never had this
     * problem — which is exactly why the app's path needs its own cover.
     */
    public function test_an_application_from_the_app_matches_one_from_the_portal(): void
    {
        $fromApp = $this->apply();

        $this->post('http://' . config('app.portal_domain') . '/ajax', [
            'submit_sp_application' => 1,
            'provider_type' => 'hlh',
            'provider_types' => ['hlh', 'osp'],
            'name' => 'Tirthan Valley Homestay',
            'contact_person' => 'Aarav Mehta',
            'email' => 'portal@example.test',
            'phone_1' => '9812345670',
            'region_id' => $this->region->id,
            'speaks_english' => true,
            'speaks_hindi' => true,
            'other_languages' => 'Pahari, Ladakhi',
            'has_business' => true,
            'experience_categories' => ['Experiential accommodation'],
            'service_categories' => ['Taxi services'],
            'password' => 'Str0ng!passphrase',
            'password_confirmation' => 'Str0ng!passphrase',
        ])->assertOk();

        $fromPortal = ServiceProvider::where('email', 'portal@example.test')->firstOrFail();

        foreach ([
            'provider_types', 'speaks_english', 'speaks_hindi', 'other_languages',
            'has_business', 'experience_categories', 'service_categories',
        ] as $field) {
            $this->assertEquals(
                $fromPortal->$field,
                $fromApp->$field,
                "$field differs between the app and the portal"
            );
        }
    }
}
