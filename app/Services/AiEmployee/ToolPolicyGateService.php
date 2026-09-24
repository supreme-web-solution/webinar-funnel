<?php

namespace App\Services\AiEmployee;

use App\Ai\Tools\GatedTool;
use App\Models\AiEmployeeSetting;

class ToolPolicyGateService
{
    public function __construct(
        protected ToolPolicyRegistry $registry,
    ) {}

    /**
     * @return array{ok: bool, message?: string, stage_approval?: bool}
     */
    public function inspect(GatedTool $tool, AiEmployeeSetting $setting): array
    {
        if ($setting->isKilled()) {
            return [
                'ok' => false,
                'message' => 'The AI employee is paused. Turn the kill switch off in Command Center settings, then retry.',
            ];
        }

        $class = $this->registry->classForTool($tool);

        if ($tool->permission() === 'destructive' || $class === ToolPolicyRegistry::DESTRUCTIVE) {
            return [
                'ok' => true,
                'stage_approval' => true,
            ];
        }

        if ($class === ToolPolicyRegistry::READ) {
            return ['ok' => true];
        }

        if ($class === ToolPolicyRegistry::PREPARE) {
            return ['ok' => true];
        }

        $autonomy = $setting->autonomy ?: AiEmployeeSetting::AUTONOMY_ASSISTED;

        if ($autonomy === AiEmployeeSetting::AUTONOMY_COPILOT) {
            return [
                'ok' => false,
                'message' => 'Autonomy is Copilot — I can read and draft, but I cannot execute “'.$tool->toolName().'”. Switch to Assisted and say LAUNCH, or raise autonomy.',
            ];
        }

        if ($autonomy === AiEmployeeSetting::AUTONOMY_AUTOPILOT && $setting->allowsExecute($tool->toolName())) {
            return ['ok' => true, 'stage_approval' => false];
        }

        return ['ok' => true, 'stage_approval' => true];
    }
}
