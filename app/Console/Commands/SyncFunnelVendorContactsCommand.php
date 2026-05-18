<?php

namespace App\Console\Commands;

use App\Models\Funnel;
use Illuminate\Console\Command;

class SyncFunnelVendorContactsCommand extends Command
{
    protected $signature = 'funnels:sync-vendor-contacts {--force : Overwrite existing funnel vendor contacts}';

    protected $description = 'Copy template vendor_contact data onto funnel settings';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $updated = 0;
        $skipped = 0;

        Funnel::query()
            ->with(['settings', 'template:id,vendor_contact'])
            ->chunkById(100, function ($funnels) use ($force, &$updated, &$skipped): void {
                foreach ($funnels as $funnel) {
                    $contact = $funnel->template?->vendor_contact;

                    if (! is_array($contact) || $contact === []) {
                        $skipped++;

                        continue;
                    }

                    $settings = $funnel->settings;

                    if (! $settings) {
                        $skipped++;

                        continue;
                    }

                    if (! $force && filled($settings->vendor_contact)) {
                        $skipped++;

                        continue;
                    }

                    $settings->forceFill(['vendor_contact' => $contact])->saveQuietly();
                    $updated++;
                }
            });

        $this->info("Updated {$updated} funnel(s). Skipped {$skipped}.");

        return self::SUCCESS;
    }
}
