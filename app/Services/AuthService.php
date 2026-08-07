<?php

namespace App\Services;

use App\Mail\AdminNewApplicationEmail;
use App\Mail\SpApplicationReceivedEmail;
use App\Models\ServiceProvider;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use function Illuminate\Support\defer;

/**
 * Becoming a member of the collective, and the account that comes with it.
 *
 * Two surfaces file the same application: the portal's /join wizard and the
 * provider app's signup. They used to share it by having the API call the
 * portal's AJAX dispatcher — which worked, but meant reading four files and a
 * 9,000-line controller to follow one signup, and let a field vanish silently
 * if it was missing from a whitelist along the way.
 *
 * The logic lives here instead. Both controllers call it directly, so there is
 * one implementation and one place a bug can be. Nothing here knows about HTTP:
 * it takes plain data and uploaded files, and returns a status and a body for
 * the caller to send.
 */
class AuthService
{
    /**
     * File a provider application and create the account behind it.
     *
     * @param  array<string,mixed>  $data
     * @param  array<int,UploadedFile>  $documents  Verification uploads, paired
     *         by position with $data['document_labels'].
     * @return array{status:int, body:array<string,mixed>, user:?User}
     */
    public function submitProviderApplication(array $data, array $documents = []): array
    {
        // Signup is the one flow we cannot watch: it happens on a member's own
        // phone, once, and a failure leaves them with a spinner and us with
        // nothing. These lines say which step it reached, so "it didn't work"
        // can be answered without asking them to do it again.
        //
        // Never the payload itself — it carries the password they just chose.
        $trace = fn (string $step, array $context = []) => Log::info(
            '[signup] ' . $step,
            $context + ['email' => $data['email'] ?? null],
        );

        $trace('received', [
            'documents' => count($documents),
            'types' => $data['provider_types'] ?? ($data['provider_type'] ?? null),
        ]);

        // A trading name is only asked for when there is a business, and the
        // client's brief is explicit that most members will not have one:
        // "You don't need to own a business." For them their own name is the
        // name we know them by, so it stands in rather than the application
        // being refused for a field they were never shown.
        if (blank($data['name'] ?? null) && filled($data['contact_person'] ?? null)) {
            $data['name'] = $data['contact_person'];
        }

        $validator = Validator::make(
            $data + ['documents' => $documents],
            $this->rules(),
            $this->messages(),
        );

        if ($validator->fails()) {
            // Which field, not just that something was wrong — the caller shows
            // the applicant one message and we keep the rest.
            $trace('rejected', ['errors' => $validator->errors()->toArray()]);

            return [
                'status' => 422,
                'body' => ['error' => $validator->errors()->first()],
                'user' => null,
            ];
        }

        // Persist the uploads first, so the row can carry their stored paths.
        $labels = (array) ($data['document_labels'] ?? []);
        $stored = $this->storeDocuments($documents, $labels);
        $trace('documents stored', ['sent' => count($documents), 'stored' => count($stored)]);

        $provider = $this->createProvider($data, $stored, $documents, $labels);
        $trace('provider created', ['provider' => $provider->id, 'photo' => (bool) $provider->photo]);

        // An email that already has a provider account is linked to this
        // application instead, and that person signs in with the password they
        // already have.
        [$user, $redirect] = $this->provisionAccount($provider, $data['password'] ?? null);
        $trace('account provisioned', ['user' => $user?->id, 'existing' => $user === null]);

        $this->announce($provider);

        // Reaching this means the application is filed and answered. Anything
        // missing after it is the mail, which is deferred and logs its own
        // failure.
        $trace('complete', ['provider' => $provider->id]);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'redirect' => $redirect,
                'existing_account' => $user === null,
                'message' => $user === null
                    ? 'You already have a HECO account. Please log in to track your application.'
                    : 'Application submitted successfully',
            ],
            'user' => $user,
        ];
    }

    /**
     * Store verification documents and return their metadata.
     *
     * Public because a member can also file one after signup, from the app's
     * Documents screen — same folder, same naming, same rules.
     *
     * Written next to the applicant's photo under public/uploads/providers, so
     * one provider's files are in one place and the URL says nothing about how
     * the framework happens to store them.
     *
     * Copied rather than moved: the avatar is picked out of the same uploads
     * afterwards, and a moved temp file is gone by then. PHP clears the temp
     * copy when the request ends either way.
     *
     * @param  array<int,UploadedFile>  $documents
     * @param  array<int,string>  $labels
     * @return array<int,array{label:string,path:string,original_name:string}>
     */
    public function storeDocuments(array $documents, array $labels = []): array
    {
        $stored = [];
        [$dir, $relativeDir] = ImageUploadService::ensureDir('providers');

        foreach ($documents as $i => $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }

            // Guessed from the bytes, not from the name the phone sent — and
            // already narrowed to jpg/jpeg/png/pdf by the validator.
            $ext = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
            $filename = (string) Str::uuid() . '.' . $ext;

            if (!@copy($file->getRealPath(), $dir . DIRECTORY_SEPARATOR . $filename)) {
                Log::error('Failed to store application document', [
                    'label' => $labels[$i] ?? null,
                    'original_name' => $file->getClientOriginalName(),
                ]);
                continue;
            }

            $stored[] = [
                'label' => $labels[$i] ?? 'Document',
                'path' => '/' . $relativeDir . '/' . $filename,
                'original_name' => $file->getClientOriginalName(),
            ];
        }

        return $stored;
    }

    /**
     * The application's own validation. Both surfaces get the same rules
     * because there is only one copy of them.
     *
     * @return array<string,mixed>
     */
    private function rules(): array
    {
        return [
            'provider_type' => 'required|in:hrp,hlh,osp',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:service_providers,email',
            'phone_1' => 'required|string|max:20',
            'region_id' => 'required|exists:regions,id',
            // An applicant may be more than one thing at once — a host that
            // also runs a taxi ticks HLH and OSP. Optional, so the older
            // single-type web form keeps working unchanged.
            'provider_types' => 'nullable|array|min:1',
            'provider_types.*' => 'in:hrp,hlh,osp',
            // Spoken languages (screen 6) and the business gate (screen 7).
            'speaks_english' => 'nullable|boolean',
            'speaks_hindi' => 'nullable|boolean',
            'other_languages' => 'nullable|string|max:255',
            'has_business' => 'nullable|boolean',
            // What they offer (screen 8), one list per role they picked.
            'experience_categories' => 'nullable|array',
            'experience_categories.*' => 'string|max:120',
            'service_categories' => 'nullable|array',
            'service_categories.*' => 'string|max:120',
            'other_services' => 'nullable|string|max:255',
            // A regional partner sells nothing, so their background is the
            // application: "For HRPs, we'd rather collect information about
            // their background and skills."
            'education_level' => 'nullable|string|max:100',
            'education_notes' => 'nullable|string|max:1000',
            'english_level' => 'nullable|string|max:100',
            'computer_skill_level' => 'nullable|string|max:100',
            'work_experience' => 'nullable|array|max:10',
            'work_experience.*.role' => 'nullable|string|max:255',
            'work_experience.*.organisation' => 'nullable|string|max:255',
            'work_experience.*.years' => 'nullable|string|max:100',
            'work_experience.*.description' => 'nullable|string|max:1000',
            'causes_note' => 'nullable|string|max:2000',
            'community_note' => 'nullable|string|max:2000',
            // Verification documents. The client caps these at 2 MB; the type
            // list keeps an applicant from posting something that is not a
            // document at all. Enforced here as well as in the app, because the
            // app is not the only thing that can reach this.
            'documents' => 'nullable|array|max:8',
            'documents.*' => 'file|mimes:jpg,jpeg,png,pdf|max:2048',
            'contact_by_email' => 'nullable|boolean',
            'contact_by_whatsapp' => 'nullable|boolean',
            // The applicant chooses their password on the signup form itself.
            'password' => ['required', 'string', 'min:8', 'regex:/[0-9]/', 'regex:/[^A-Za-z0-9]/', 'confirmed'],
        ];
    }

    /** @return array<string,string> */
    private function messages(): array
    {
        return [
            'email.unique' => 'An application with this email already exists. Please log in to track it, or contact us to update it.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.regex' => 'Password must include a number and a symbol.',
            'password.confirmed' => 'The passwords do not match.',
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     * @param  array<int,array<string,string>>  $stored
     * @param  array<int,UploadedFile>  $documents
     * @param  array<int,string>  $labels
     */
    private function createProvider(array $data, array $stored, array $documents, array $labels): ServiceProvider
    {
        // The set always contains the primary type, so a caller that sends only
        // the enum still ends up with a usable provider_types list.
        $types = array_values(array_unique(array_merge(
            [$data['provider_type']],
            $this->list($data, 'provider_types') ?: [],
        )));

        // "No" on screen 7 skips the business screen entirely, so its fields
        // are dropped rather than stored half-filled from an earlier pass.
        $hasBusiness = array_key_exists('has_business', $data)
            ? filter_var($data['has_business'], FILTER_VALIDATE_BOOLEAN)
            : null;

        $isRegionalPartner = in_array('hrp', $types, true);

        return ServiceProvider::create([
            'provider_type' => $data['provider_type'],
            'provider_types' => $types,
            'has_business' => $hasBusiness,
            'business_type' => $hasBusiness === false ? null : ($data['business_type'] ?? null),
            'registration_number' => $hasBusiness === false ? null : ($data['registration_number'] ?? null),
            'year_established' => $hasBusiness === false ? null : ($data['year_established'] ?? null ?: null),
            'name' => $data['name'],
            'contact_person' => $data['contact_person'] ?? null,
            'email' => $data['email'],
            'phone_1' => $data['phone_1'],
            'phone_2' => $data['phone_2'] ?? null,
            'speaks_english' => $this->bool($data, 'speaks_english'),
            'speaks_hindi' => $this->bool($data, 'speaks_hindi'),
            'other_languages' => $data['other_languages'] ?? null,
            // Categories only belong to a role they actually picked, so a
            // host's experience picks are dropped if they never ticked HLH.
            'experience_categories' => in_array('hlh', $types, true)
                ? $this->list($data, 'experience_categories')
                : null,
            'service_categories' => in_array('osp', $types, true)
                ? $this->list($data, 'service_categories')
                : null,
            'other_services' => in_array('osp', $types, true) ? ($data['other_services'] ?? null) : null,
            // Default to reachable — a missing key means an older client that
            // never asked, not a member who declined.
            'contact_by_email' => array_key_exists('contact_by_email', $data)
                ? $this->bool($data, 'contact_by_email')
                : true,
            'contact_by_whatsapp' => array_key_exists('contact_by_whatsapp', $data)
                ? $this->bool($data, 'contact_by_whatsapp')
                : true,
            'region_id' => $data['region_id'],
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'country' => $data['country'] ?? null,
            'services_offered' => $this->list($data, 'services_offered'),
            'accommodation_categories' => $this->list($data, 'accommodation_categories'),
            'vehicle_types' => $this->list($data, 'vehicle_types'),
            'guide_types' => $this->list($data, 'guide_types'),
            'activity_types' => $this->list($data, 'activity_types'),
            // Competences belong to a regional partner. Someone who never
            // ticked HRP has none, and storing them would put answers on a
            // record nobody will ever read them from.
            ...($isRegionalPartner ? [
                'education_level' => $data['education_level'] ?? null,
                'education_notes' => $data['education_notes'] ?? null,
                'english_level' => $data['english_level'] ?? null,
                'computer_skill_level' => $data['computer_skill_level'] ?? null,
                'work_experience' => $this->workExperience($data),
                'causes_note' => $data['causes_note'] ?? null,
                'community_note' => $data['community_note'] ?? null,
            ] : []),
            'documents' => $stored ?: null,
            'photo' => $this->avatar($documents, $labels),
            'notes' => $data['description'] ?? ($data['notes'] ?? null),
            'status' => 'pending',
        ]);
    }

    /**
     * Ensure the applicant has a login WITHOUT signing them in, returning
     * [newUser|null, redirect]. A brand-new email gets a fresh account with the
     * password chosen on the form; an email that already belongs to a provider
     * account is linked to the application and sent to log in with the password
     * it already has.
     *
     * @return array{0:?User, 1:string}
     */
    private function provisionAccount(ServiceProvider $provider, ?string $password): array
    {
        // Scoped to provider roles: the applicant's traveller account is a
        // different account that happens to share the address.
        $existing = User::findByEmailForRoles($provider->email, User::PROVIDER_ROLES);
        if ($existing) {
            $provider->forceFill(['user_id' => $existing->id])->save();

            return [null, '/login'];
        }

        $user = User::create([
            'full_name' => $provider->contact_person ?: $provider->name,
            'email' => $provider->email,
            // Falls back to an unusable random secret so a caller that skips it
            // (an admin creating a provider by hand) cannot be logged into.
            'password' => $password ?: Str::random(40),
            'auth_type' => 'email',
            'user_role' => $provider->provider_type,
        ]);

        // forceFill because password_set_at is deliberately not fillable — it
        // records a fact about the account, not something a form may claim.
        // They chose the password here, so there is nothing left to verify;
        // leaving it null would make approval mail them a reset link.
        $user->forceFill(['password_set_at' => now()])->save();

        $provider->forceFill(['user_id' => $user->id])->save();

        return [$user, '/application-status'];
    }

    /** Tell the applicant it arrived, and HCT that it is waiting. */
    private function announce(ServiceProvider $provider): void
    {
        $label = ServiceProvider::TYPE_LABELS[$provider->provider_type] ?? 'Partner';

        $this->mail(
            $provider->email,
            new SpApplicationReceivedEmail($provider->contact_person ?: $provider->name, $label),
            'sp_application:' . $provider->id,
        );

        $this->mail(
            Setting::getValue('site_email') ?: 'info@heco.eco',
            new AdminNewApplicationEmail($provider->fresh(['region']), $label),
            'admin_new_application:' . $provider->id,
        );
    }

    /**
     * Sent after the response, not before it.
     *
     * An SMTP round trip is seconds of wall clock, and on Windows PHP charges
     * that to max_execution_time. A signup that also had documents to receive,
     * store and resize was spending its whole budget and dying mid-request,
     * leaving the uploaded files on disk with no application to belong to.
     *
     * defer() and not queue(): the mail still goes out from this same process,
     * so nothing depends on a worker being up — and on the shared hosting this
     * deploys to, nothing could run one.
     */
    private function mail(string $to, object $mailable, string $tag): void
    {
        defer(function () use ($to, $mailable, $tag) {
            try {
                Mail::to($to)->send($mailable);
            } catch (\Throwable $e) {
                Log::error('Mail send failed [' . $tag . ']: ' . $e->getMessage(), [
                    'to' => $to,
                    'mailable' => get_class($mailable),
                ]);
            }
        });
    }

    /**
     * The picture attached to the "Profile photo" slot is also their avatar.
     *
     * Which slot that is comes from a setting rather than this method, because
     * HCT can rename the document types.
     *
     * @param  array<int,UploadedFile>  $documents
     * @param  array<int,string>  $labels
     */
    private function avatar(array $documents, array $labels): ?string
    {
        $wanted = (string) Setting::getValue('signup_avatar_document', 'Profile photo');
        if ($wanted === '') {
            return null;
        }

        foreach ($documents as $i => $file) {
            if (!$file instanceof UploadedFile || !$file->isValid() || ($labels[$i] ?? null) !== $wanted) {
                continue;
            }

            // A PDF is a valid document but not a usable avatar; the service
            // returns null for anything it cannot render, and the document
            // itself is stored either way.
            $stored = ImageUploadService::storeUploadedImage($file, 'providers', 600) ?: null;
            if (!$stored) {
                // They picked something for this slot and got initials anyway.
                // A PDF explains itself; a JPG means GD refused it.
                Log::info('[signup] avatar not produced', [
                    'original_name' => $file->getClientOriginalName(),
                    'extension' => $file->guessExtension(),
                ]);
            }

            return $stored;
        }

        return null;
    }

    /**
     * A posted list, emptied of the blanks a form sends for untouched rows.
     *
     * @param  array<string,mixed>  $data
     * @return array<int,mixed>|null
     */
    private function list(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return null;
        }

        $value = array_values(array_filter((array) $value, fn ($v) => $v !== null && $v !== ''));

        return $value ?: null;
    }

    /** @param array<string,mixed> $data */
    private function bool(array $data, string $key): bool
    {
        return filter_var($data[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * The roles a regional partner has held, minus the empty rows the form
     * leaves behind.
     *
     * @param  array<string,mixed>  $data
     * @return array<int,array<string,string>>|null
     */
    private function workExperience(array $data): ?array
    {
        $rows = [];

        foreach ((array) ($data['work_experience'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $role = trim((string) ($row['role'] ?? ''));
            $organisation = trim((string) ($row['organisation'] ?? ''));
            if ($role === '' && $organisation === '') {
                continue;
            }

            $rows[] = [
                'role' => $role,
                'organisation' => $organisation,
                'years' => trim((string) ($row['years'] ?? '')),
                'description' => trim((string) ($row['description'] ?? '')),
            ];
        }

        return $rows ?: null;
    }
}
