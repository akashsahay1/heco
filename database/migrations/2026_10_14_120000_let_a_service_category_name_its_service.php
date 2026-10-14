<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Signup asks a partner what they do in their own words ("Taxi services"),
 * but the Trip Manager finds partners by service type ("Transport") in
 * services_offered, which signup never asked. So every partner arrived with
 * an empty services_offered and no trip could ever find them.
 *
 * maps_to names the service type a signup category stands for. It is kept on
 * the category row so a new category is mapped in the Control Panel, not here.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('system_lists', function (Blueprint $table) {
            $table->string('maps_to', 150)->nullable()->after('description');
        });

        foreach ([
            'Standard accommodation' => 'Accommodation',
            'Taxi services' => 'Transport',
            'Guide' => 'Guide',
            'Rental' => 'Rental',
            'Other services' => 'Other',
        ] as $category => $type) {
            DB::table('system_lists')
                ->where('list_type', 'service_category')
                ->where('name', $category)
                ->update(['maps_to' => $type]);
        }

        // Partners who signed up before this carry the categories but not the
        // services. Give them what their categories already said.
        $map = DB::table('system_lists')->where('list_type', 'service_category')
            ->whereNotNull('maps_to')->pluck('maps_to', 'name');

        DB::table('service_providers')->whereNotNull('service_categories')->get(['id', 'service_categories', 'services_offered'])
            ->each(function ($p) use ($map) {
                $offered = (array) json_decode($p->services_offered ?? '[]', true);
                $mapped = collect((array) json_decode($p->service_categories, true))
                    ->map(fn ($c) => $map[$c] ?? null)->filter();
                $merged = collect($offered)->merge($mapped)->unique()->values()->all();
                if ($merged !== $offered) {
                    DB::table('service_providers')->where('id', $p->id)
                        ->update(['services_offered' => json_encode($merged)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('system_lists', function (Blueprint $table) {
            $table->dropColumn('maps_to');
        });
    }
};
