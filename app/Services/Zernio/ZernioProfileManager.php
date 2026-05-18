<?php

namespace App\Services\Zernio;

use App\Models\User;
use Illuminate\Support\Facades\Log;

final class ZernioProfileManager
{
    public function __construct(private readonly ZernioClient $zernio) {}

    public function ensureForUser(User $user): string
    {
        if (! $this->zernio->isConfigured()) {
            throw new \RuntimeException('Zernio API key is not configured.');
        }

        $existing = $user->zernio_profile_id;
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $label = trim((string) ($user->name ?: $user->email));
        if ($label === '') {
            $label = 'User '.$user->id;
        }

        $response = $this->zernio->createProfile($label, 'DFY Webinar Forge user #'.$user->id);
        $profileId = $this->extractProfileId($response);

        if ($profileId === null) {
            throw new \RuntimeException('Zernio did not return a profile id.');
        }

        $user->forceFill(['zernio_profile_id' => $profileId])->save();

        Log::info('ZernioProfileManager: created profile', [
            'user_id' => $user->id,
            'zernio_profile_id' => $profileId,
        ]);

        return $profileId;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function extractProfileId(array $response): ?string
    {
        $candidates = [
            $response['id'] ?? null,
            $response['_id'] ?? null,
            is_array($response['data'] ?? null) ? ($response['data']['id'] ?? $response['data']['_id'] ?? null) : null,
            is_array($response['profile'] ?? null) ? ($response['profile']['id'] ?? $response['profile']['_id'] ?? null) : null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }
}
