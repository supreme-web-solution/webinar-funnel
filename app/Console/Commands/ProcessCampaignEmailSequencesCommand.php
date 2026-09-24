<?php

namespace App\Console\Commands;

use App\Services\Campaigns\CampaignEmailSequenceService;
use Illuminate\Console\Command;

class ProcessCampaignEmailSequencesCommand extends Command
{
    protected $signature = 'campaigns:process-email-sequences';

    protected $description = 'Send due in-app campaign email swipes to captured leads';

    public function handle(CampaignEmailSequenceService $sequences): int
    {
        $sent = $sequences->processDueSends();
        $this->info("Processed campaign email sequence sends: {$sent} sent.");

        return self::SUCCESS;
    }
}
