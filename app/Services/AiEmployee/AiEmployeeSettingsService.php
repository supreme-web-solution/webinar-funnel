<?php

namespace App\Services\AiEmployee;

use App\Models\AiEmployeeSetting;
use App\Models\User;

class AiEmployeeSettingsService
{
    public function for(User $user): AiEmployeeSetting
    {
        return AiEmployeeSetting::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'killed' => false,
                'autonomy' => (string) config('ai_employee.autonomy.default', AiEmployeeSetting::AUTONOMY_ASSISTED),
                'execute_allowlist' => [],
            ]
        );
    }

    /**
     * @param  array{killed?: bool, autonomy?: string, execute_allowlist?: list<string>|null}  $data
     */
    public function update(User $user, array $data): AiEmployeeSetting
    {
        $setting = $this->for($user);

        if (array_key_exists('killed', $data)) {
            $setting->killed = (bool) $data['killed'];
        }

        if (isset($data['autonomy']) && in_array($data['autonomy'], config('ai_employee.autonomy.levels', []), true)) {
            $setting->autonomy = $data['autonomy'];
        }

        if (array_key_exists('execute_allowlist', $data)) {
            $list = $data['execute_allowlist'];
            $mutate = app(ToolPolicyRegistry::class)->mutateToolNames();
            $setting->execute_allowlist = is_array($list)
                ? array_values(array_filter(
                    $list,
                    fn ($item) => is_string($item) && $item !== '' && in_array($item, $mutate, true)
                ))
                : [];
        }

        $setting->save();

        return $setting;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(AiEmployeeSetting $setting): array
    {
        return [
            'killed' => $setting->isKilled(),
            'autonomy' => $setting->autonomy,
            'execute_allowlist' => $setting->allowlist(),
            'whatsapp_phone' => $setting->whatsapp_phone,
            'whatsapp_linked' => is_string($setting->whatsapp_phone) && $setting->whatsapp_phone !== '',
        ];
    }
}
