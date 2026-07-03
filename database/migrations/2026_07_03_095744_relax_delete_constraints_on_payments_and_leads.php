<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FKs that were created with the default RESTRICT, which blocks deleting a
     * trip / user / provider that still has payments or leads. Switch them to
     * nullOnDelete so the financial/lead record is PRESERVED (its FK is nulled)
     * rather than blocking the delete or cascade-deleting the money record (#29).
     *
     * [table, column, referenced_table]
     */
    private array $fks = [
        ['traveller_payments', 'trip_id', 'trips'],
        ['traveller_payments', 'user_id', 'users'],
        ['traveller_payments', 'recorded_by', 'users'],
        ['sp_payments', 'trip_id', 'trips'],
        ['sp_payments', 'service_provider_id', 'service_providers'],
        ['sp_payment_entries', 'recorded_by', 'users'],
        ['leads', 'trip_id', 'trips'],
        ['leads', 'user_id', 'users'],
    ];

    public function up(): void
    {
        // FK re-definition needs MySQL's ALTER; sqlite (tests) doesn't enforce
        // these delete rules and can't drop/re-add FKs in place, so skip there.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        foreach ($this->fks as [$table, $column, $ref]) {
            Schema::table($table, function (Blueprint $t) use ($column) {
                $t->dropForeign([$column]);
                $t->unsignedBigInteger($column)->nullable()->change();
            });
            Schema::table($table, function (Blueprint $t) use ($column, $ref) {
                $t->foreign($column)->references('id')->on($ref)->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        // Restore the original RESTRICT + NOT NULL. This will fail if any row was
        // nulled by a delete in the meantime — which correctly signals the reverse
        // isn't clean anymore (restore from the pre-migration DB backup instead).
        foreach ($this->fks as [$table, $column, $ref]) {
            Schema::table($table, function (Blueprint $t) use ($column) {
                $t->dropForeign([$column]);
                $t->unsignedBigInteger($column)->nullable(false)->change();
            });
            Schema::table($table, function (Blueprint $t) use ($column, $ref) {
                $t->foreign($column)->references('id')->on($ref);
            });
        }
    }
};
