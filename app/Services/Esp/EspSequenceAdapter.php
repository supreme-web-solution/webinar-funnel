<?php

namespace App\Services\Esp;

interface EspSequenceAdapter
{
    /**
     * Upload email swipes to the ESP (drafts, sequence, or webhook payload).
     *
     * @param  array<int, array{subject: string, body: string, sequence_key?: string, sort_order?: int}>  $emails
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $config  tag, campaign_name, affiliate_link, …
     * @return array{ok: bool, message: string, uploaded?: int, details?: array<int, string>}
     */
    public function uploadSequence(array $emails, array $credentials, array $config): array;
}
