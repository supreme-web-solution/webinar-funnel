<?php

namespace App\Console\Commands;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Zernio\ZernioClient;
use Illuminate\Console\Command;

class TestXThreadPublishCommand extends Command
{
    protected $signature = 'promotion:test-x-thread
                            {--user=1 : User ID}
                            {--mode=threadItems : threadItems|chain}';

    protected $description = 'Publish a simple 3-tweet X thread via Zernio for debugging';

    public function handle(ZernioClient $zernio): int
    {
        if (! $zernio->isConfigured()) {
            $this->error('ZERNIO_API_KEY is not set.');

            return self::FAILURE;
        }

        $user = User::query()->find((int) $this->option('user'));
        if ($user === null) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $account = SocialAccount::query()
            ->where('user_id', $user->id)
            ->where('platform', 'twitter')
            ->whereNotNull('zernio_account_id')
            ->first();

        if ($account === null) {
            $this->error('No connected Twitter account for user '.$user->id);

            return self::FAILURE;
        }

        $stamp = now()->format('H:i:s');
        $parts = [
            "🧪 Thread test {$stamp} — part 1/3 (hook tweet)",
            "🧪 Thread test {$stamp} — part 2/3 (middle value tweet)",
            "🧪 Thread test {$stamp} — part 3/3 (final CTA tweet)",
        ];

        $mode = (string) $this->option('mode');
        $this->info("Publishing 3-tweet thread via mode: {$mode}");
        $this->info('Account: '.$account->platform_username.' ('.$account->zernio_account_id.')');

        if ($mode === 'threadItems') {
            return $this->publishThreadItems($zernio, $account, $parts);
        }

        return $this->publishChain($zernio, $account, $parts);
    }

    /**
     * @param  list<string>  $parts
     */
    private function publishThreadItems(ZernioClient $zernio, SocialAccount $account, array $parts): int
    {
        $threadItems = array_map(fn (string $p): array => ['content' => $p], $parts);

        $result = $zernio->createPost(
            content: $parts[0],
            platforms: [[
                'platform' => 'twitter',
                'accountId' => (string) $account->zernio_account_id,
                'platformSpecificData' => ['threadItems' => $threadItems],
            ]],
            publishNow: true,
        );

        return $this->reportResult($zernio, $result, 'threadItems');
    }

    /**
     * @param  list<string>  $parts
     */
    private function publishChain(ZernioClient $zernio, SocialAccount $account, array $parts): int
    {
        $lastId = null;
        $rootUrl = null;

        foreach ($parts as $i => $content) {
            $target = [
                'platform' => 'twitter',
                'accountId' => (string) $account->zernio_account_id,
            ];
            if ($lastId !== null) {
                $target['platformSpecificData'] = ['replyToTweetId' => $lastId];
            }

            $this->line('Posting part '.($i + 1).'…');
            $result = $zernio->createPost(
                content: $content,
                platforms: [$target],
                publishNow: true,
            );

            if (! ($result['success'] ?? false)) {
                $this->error('Part '.($i + 1).' failed: '.($result['error'] ?? 'unknown'));

                return self::FAILURE;
            }

            $row = collect($result['published'] ?? [])->firstWhere('platform', 'twitter');
            $lastId = is_array($row) ? ($row['external_id'] ?? null) : null;
            if ($i === 0 && is_array($row)) {
                $rootUrl = $row['url'] ?? null;
            }

            $this->line('  → '.($row['url'] ?? $lastId ?? 'no url'));
            if ($i < count($parts) - 1) {
                sleep(2);
            }
        }

        $this->info('Chain complete. Root: '.($rootUrl ?? 'n/a'));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function reportResult(ZernioClient $zernio, array $result, string $mode): int
    {
        if (! ($result['success'] ?? false)) {
            $this->error('Publish failed: '.($result['error'] ?? 'unknown'));

            return self::FAILURE;
        }

        $zernioPostId = $result['zernio_post_id'] ?? null;
        $row = collect($result['published'] ?? [])->firstWhere('platform', 'twitter');

        $this->info('Zernio post: '.($zernioPostId ?? 'n/a'));
        $this->info('Root tweet: '.(is_array($row) ? ($row['url'] ?? $row['external_id'] ?? 'n/a') : 'n/a'));

        if (is_string($zernioPostId) && $zernioPostId !== '') {
            sleep(5);
            $post = $zernio->getPost($zernioPostId);
            if (is_array($post)) {
                $this->line('getPost platforms: '.json_encode($post['platforms'] ?? [], JSON_PRETTY_PRINT));
            }
        }

        $this->info("Done ({$mode}). Open the root tweet on X and tap “Show this thread”.");

        return self::SUCCESS;
    }
}
