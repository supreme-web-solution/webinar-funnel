<?php

namespace App\Jobs;

use App\Services\Content\ContentEmployeePlanService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessDueContentEmployeeItemsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue((string) config('promotion.queues.generate', 'promotion-generate'));
    }

    public function handle(ContentEmployeePlanService $plans): void
    {
        $plans->processDueItems();
    }
}
