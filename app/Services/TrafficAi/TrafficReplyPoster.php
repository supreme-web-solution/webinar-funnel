<?php

namespace App\Services\TrafficAi;

use App\Models\Mention;
use App\Models\SocialAccount;
use App\Services\Zernio\ZernioClient;

final class TrafficReplyPoster
{
    public function __construct(private readonly ZernioClient $zernio) {}

    /**
     * @return array{success: bool, external_id?: string|null, error?: string, rate_limited?: bool}
     */
    public function post(SocialAccount $account, Mention $mention, string $replyText): array
    {

        if (! $this->zernio->isEnabled()) {

            return ['success' => false, 'error' => 'Zernio is not configured.'];

        }

        if (! $account->isConnectedForPosting()) {

            return ['success' => false, 'error' => 'Social account not connected via Zernio; reconnect in settings.'];

        }

        $postId = $mention->post_id;

        if ($postId === null || $postId === '') {

            return ['success' => false, 'error' => 'Missing platform post id on mention.'];

        }

        $commentId = $mention->reply_target_id;

        if ($account->platform === 'reddit' && $commentId === null) {

            $postId = str_starts_with($postId, 't3_') ? $postId : 't3_'.$postId;

        }

        return $this->zernio->replyToPost(

            $postId,

            (string) $account->zernio_account_id,

            $replyText,

            is_string($commentId) && $commentId !== '' ? $commentId : null,

        );

    }
}
