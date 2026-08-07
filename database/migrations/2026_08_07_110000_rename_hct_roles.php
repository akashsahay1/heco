<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `hct_admin` and `hct_collaborator` become `administrator` and `collaborator`.
 *
 * HCT is the organisation running the platform, not a kind of permission. The
 * prefix said who the person works for; the role should say what they may do,
 * and reads better everywhere it appears — a badge in the admin header, a
 * validation message, a seeder.
 *
 * Nothing about the permissions changes. isHctAdmin() and isHct() still mean
 * exactly what they meant, and the constants they read from moved with them.
 */
return new class extends Migration
{
    private const RENAMES = [
        'hct_admin' => 'administrator',
        'hct_collaborator' => 'collaborator',
    ];

    private const FINAL = ['administrator', 'collaborator', 'traveller', 'provider'];

    public function up(): void
    {
        $this->rename(self::RENAMES);
    }

    public function down(): void
    {
        $this->rename(array_flip(self::RENAMES));
    }

    /**
     * Widen the column to hold both spellings, move the rows, then narrow it to
     * the ones that remain. A database already using the target names moves no
     * rows and ends where it started.
     *
     * @param  array<string,string>  $map
     */
    private function rename(array $map): void
    {
        $this->setRoles(array_unique([...self::FINAL, ...array_keys($map), ...array_values($map)]));

        foreach ($map as $from => $to) {
            DB::table('users')->where('user_role', $from)->update(['user_role' => $to]);
        }

        $this->setRoles(array_unique([...array_values($map), 'traveller', 'provider']));
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
