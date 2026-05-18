<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Zernio\ZernioClient;
use App\Services\Zernio\ZernioProfileManager;
use Illuminate\Console\Command;

class ZernioTestConnectCommand extends Command
{
    protected $signature = 'zernio:test-connect {platform=reddit} {--user=1}';

    protected $description = 'Test Zernio GET /v1/connect/{platform} and print auth URL + callback';

    public function handle(ZernioClient $zernio, ZernioProfileManager $profiles): int
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

        $platform = $this->argument('platform');
        $callback = route('settings.social-traffic.zernio.callback');

        try {
            $profileId = $profiles->ensureForUser($user);
            $connect = $zernio->getConnectUrl($platform, $profileId, $callback);

            $this->info('APP_URL: '.config('app.url'));
            $this->info('Callback registered with Zernio: '.$callback);
            $this->info('Profile: '.$profileId);
            $this->line('Auth URL: '.$connect['authUrl']);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
