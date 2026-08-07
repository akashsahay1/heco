<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `hrp`, `hlh` and `osp` become one role: `provider`.
 *
 * The account's role was a copy of service_providers.provider_type, and a copy
 * that could not be right — a member can host and run a taxi at once, and one
 * value has to pick one of them. Which kinds of partner someone is belongs to
 * service_providers.provider_types, which holds the set; the account only needs
 * to know it is a partner at all.
 *
 * users_email_role_unique keeps working and gets simpler: one address can be a
 * traveller and a provider, which is the case it was added for. What it can no
 * longer do is hold three provider accounts on one address — which was never a
 * thing anyone wanted, only something the old roles allowed.
 */
return new class extends Migration
{
    private const OLD_ROLES = ['hrp', 'hlh', 'osp'];

    public function up(): void
    {
        // Widen first, move the rows, then narrow — the rows cannot say
        // 'provider' until the column allows it, and the column cannot drop the
        // old values while rows still hold them.
        $this->setRoles(['administrator', 'collaborator', 'traveller', 'provider', ...self::OLD_ROLES]);

        DB::table('users')->whereIn('user_role', self::OLD_ROLES)->update(['user_role' => 'provider']);

        $this->setRoles(['administrator', 'collaborator', 'traveller', 'provider']);
    }

    public function down(): void
    {
        // The primary type on each provider record is what the role used to
        // hold, so it is what the role goes back to. An account with no
        // provider record has nothing to restore from and stays a traveller.
        $this->setRoles(['administrator', 'collaborator', 'traveller', 'provider', ...self::OLD_ROLES]);

        DB::table('users')->where('user_role', 'provider')->orderBy('id')->each(function ($user) {
            $type = DB::table('service_providers')->where('user_id', $user->id)->value('provider_type');

            DB::table('users')->where('id', $user->id)->update(['user_role' => $type ?: 'traveller']);
        });

        $this->setRoles(['administrator', 'collaborator', 'traveller', ...self::OLD_ROLES]);
    }

    /**
     * MySQL has no portable way to alter an enum through the schema builder.
     * sqlite — which the tests run on — cannot modify a column in place at all,
     * and does not need to: its schema is built from the baseline migration,
     * which already names the roles this one arrives at.
     *
     * @param  array<int,string>  $roles
     */
    private function setRoles(array $roles): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $values = implode(',', array_map(fn ($role) => "'" . $role . "'", $roles));

        DB::statement("ALTER TABLE users MODIFY COLUMN user_role ENUM($values) NOT NULL DEFAULT 'traveller'");
    }
};
