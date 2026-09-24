<?php

namespace App\Listeners;

use App\Ai\Tools\GatedTool;
use App\Services\AiEmployee\AiEmployeeSessionService;
use Laravel\Ai\Events\InvokingTool;
use Laravel\Ai\Events\ToolInvoked;

class RecordAiEmployeeToolProgress
{
    public function handleInvoking(InvokingTool $event): void
    {
        app(AiEmployeeSessionService::class)->progress('Using '.$this->name($event->tool).'…');
    }

    public function handleInvoked(ToolInvoked $event): void
    {
        app(AiEmployeeSessionService::class)->progress('Finished '.$this->name($event->tool));
    }

    protected function name(mixed $tool): string
    {
        if ($tool instanceof GatedTool) {
            return $tool->toolName();
        }

        return class_basename($tool);
    }
}
