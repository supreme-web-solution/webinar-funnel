<?php

namespace App\Services\AiEmployee;

use App\Models\AiEmployeeSetting;
use App\Models\User;
use Illuminate\Support\Str;

class WhatsAppPairingService
{
    public function __construct(
        protected AiEmployeeSettingsService $settings,
    ) {}

    /**
     * @return array{code: string, expires_at: string, phone: ?string, wa_link: string, qr_url: string}
     */
    public function start(User $user): array
    {
        $setting = $this->settings->for($user);
        $code = 'LINK-'.Str::upper(Str::random(6));
        $setting->forceFill([
            'pairing_code' => $code,
            'pairing_expires_at' => now()->addMinutes((int) config('ai_employee.whatsapp.pairing_ttl_minutes', 30)),
        ])->save();

        return $this->payload($setting);
    }

    public function consumeCode(string $code): ?AiEmployeeSetting
    {
        $normalized = strtoupper(trim($code));
        $setting = AiEmployeeSetting::query()
            ->where('pairing_code', $normalized)
            ->where('pairing_expires_at', '>', now())
            ->first();

        if ($setting === null) {
            return null;
        }

        $setting->forceFill([
            'pairing_code' => null,
            'pairing_expires_at' => null,
        ])->save();

        return $setting;
    }

    /**
     * @return array{code: string, expires_at: string, phone: ?string, wa_link: string, qr_url: string}|null
     */
    public function current(User $user): ?array
    {
        $setting = $this->settings->for($user);
        if (! is_string($setting->pairing_code) || $setting->pairing_code === '') {
            return null;
        }
        if ($setting->pairing_expires_at === null || $setting->pairing_expires_at->isPast()) {
            return null;
        }

        return $this->payload($setting);
    }

    /**
     * @return array{code: string, expires_at: string, phone: ?string, wa_link: string, qr_url: string}
     */
    public function payload(AiEmployeeSetting $setting): array
    {
        $businessPhone = preg_replace('/\D+/', '', (string) config('ai_employee.whatsapp.phone', '')) ?? '';
        $text = rawurlencode($setting->pairing_code ?? '');
        $waLink = $businessPhone !== ''
            ? 'https://wa.me/'.$businessPhone.'?text='.$text
            : '';

        return [
            'code' => (string) $setting->pairing_code,
            'expires_at' => $setting->pairing_expires_at?->toIso8601String() ?? '',
            'phone' => config('ai_employee.whatsapp.phone'),
            'wa_link' => $waLink,
            'qr_url' => $waLink !== ''
                ? 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&data='.rawurlencode($waLink)
                : '',
        ];
    }
}
