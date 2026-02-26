<?php

namespace App\Console\Commands;

use App\Services\IcalSyncService;
use App\Models\ServiceProvider;
use Illuminate\Console\Command;

class SyncIcalFeeds extends Command
{
    protected $signature = 'sp:sync-ical {--sp= : Sync a specific service provider ID}';
    protected $description = 'Sync iCal availability feeds for service providers';

    public function handle(): int
    {
        $syncService = new IcalSyncService();

        if ($spId = $this->option('sp')) {
            $sp = ServiceProvider::find($spId);
            if (!$sp) {
                $this->error("Service provider #{$spId} not found.");
                return 1;
            }
            if (!$sp->ical_url) {
                $this->warn("SP #{$spId} has no iCal URL configured.");
                return 1;
            }

            try {
                $count = $syncService->syncProvider($sp);
                $this->info("Synced SP #{$spId} ({$sp->name}): {$count} dates imported.");
            } catch (\Exception $e) {
                $this->error("Failed: " . $e->getMessage());
                return 1;
            }
        } else {
            $results = $syncService->syncAll();
            if (empty($results)) {
                $this->info('No providers with iCal URLs found.');
                return 0;
            }
            foreach ($results as $id => $r) {
                if ($r['error']) {
                    $this->warn("SP #{$id} ({$r['name']}): FAILED - {$r['error']}");
                } else {
                    $this->info("SP #{$id} ({$r['name']}): {$r['synced']} dates imported.");
                }
            }
        }

        return 0;
    }
}
