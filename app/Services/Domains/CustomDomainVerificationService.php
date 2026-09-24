<?php

namespace App\Services\Domains;

use App\Models\CustomDomain;
use Illuminate\Support\Str;

class CustomDomainVerificationService
{
    public const TXT_PREFIX = 'affiliateos-verify=';

    /**
     * @return array{verification_token: string, txt_host: string, txt_value: string, cname_host: string, cname_target: string}
     */
    public function instructions(CustomDomain $domain): array
    {
        $token = $this->tokenFor($domain);
        $appHost = $this->appHost();

        return [
            'verification_token' => $token,
            'txt_host' => '_affiliateos.'.$domain->domain,
            'txt_value' => self::TXT_PREFIX.$token,
            'cname_host' => $domain->domain,
            'cname_target' => $appHost,
        ];
    }

    public function tokenFor(CustomDomain $domain): string
    {
        $meta = is_array($domain->meta) ? $domain->meta : [];
        $existing = $meta['verification_token'] ?? null;

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $token = Str::random(32);
        $meta['verification_token'] = $token;
        $domain->update(['meta' => $meta]);

        return $token;
    }

    public function verify(CustomDomain $domain): bool
    {
        $token = $this->tokenFor($domain);
        $needle = self::TXT_PREFIX.$token;

        foreach ($this->txtRecords($domain->domain) as $txt) {
            if (str_contains($txt, $needle)) {
                return true;
            }
        }

        foreach ($this->txtRecords('_affiliateos.'.$domain->domain) as $txt) {
            if (str_contains($txt, $needle)) {
                return true;
            }
        }

        return false;
    }

    public function markVerified(CustomDomain $domain): CustomDomain
    {
        $meta = is_array($domain->meta) ? $domain->meta : [];
        $meta['verified_via'] = 'dns_txt';
        $meta['last_verification_at'] = now()->toIso8601String();

        $domain->update([
            'status' => 'active',
            'verified_at' => now(),
            'meta' => $meta,
        ]);

        return $domain->fresh();
    }

    public function markFailed(CustomDomain $domain, string $reason): CustomDomain
    {
        $meta = is_array($domain->meta) ? $domain->meta : [];
        $meta['last_error'] = $reason;
        $meta['last_verification_at'] = now()->toIso8601String();

        $domain->update([
            'status' => 'failed',
            'meta' => $meta,
        ]);

        return $domain->fresh();
    }

    public function appHost(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : 'localhost';
    }

    /**
     * @return list<string>
     */
    protected function txtRecords(string $host): array
    {
        $records = @dns_get_record($host, DNS_TXT);

        if (! is_array($records)) {
            return [];
        }

        $values = [];
        foreach ($records as $record) {
            $txt = $record['txt'] ?? null;
            if (is_string($txt) && $txt !== '') {
                $values[] = $txt;
            }
        }

        return $values;
    }
}
