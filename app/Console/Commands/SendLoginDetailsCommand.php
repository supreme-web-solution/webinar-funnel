<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Auth\WelcomeEmailSender;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendLoginDetailsCommand extends Command
{
    protected $signature = 'users:send-login-details
        {emails?* : Specific user emails}
        {--since= : All users created on/after this date (e.g. 2026-10-09) that have no login email on record}
        {--dry-run : List the users without emailing them}';

    protected $description = 'Set a new password and email login details to users who never received their welcome email';

    public function handle(WelcomeEmailSender $welcomeEmails): int
    {
        $emails = array_filter((array) $this->argument('emails'));
        $since = $this->option('since');

        if ($emails === [] && ! $since) {
            $this->error('Pass one or more emails, or --since=YYYY-MM-DD.');

            return self::FAILURE;
        }

        $users = User::query()
            ->when($emails !== [], fn ($q) => $q->whereIn('email', $emails))
            ->when($emails === [] && $since, fn ($q) => $q
                ->whereNull('welcome_email_sent_at')
                ->where('created_at', '>=', Carbon::parse((string) $since)->startOfDay()))
            ->orderBy('id')
            ->get();

        if ($users->isEmpty()) {
            $this->info('No matching users.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->table(['ID', 'Email', 'Created'], $users->map(fn (User $u): array => [$u->id, $u->email, $u->created_at?->toDateTimeString()])->all());
            $this->info($users->count().' user(s) would be emailed.');

            return self::SUCCESS;
        }

        $failed = 0;
        foreach ($users as $user) {
            $error = $welcomeEmails->resetPasswordAndSend($user, 'cli_resend');
            if ($error === null) {
                $this->line("Sent: {$user->email}");
            } else {
                $failed++;
                $this->error("Failed: {$user->email} — {$error}");
            }
        }

        $this->info(($users->count() - $failed)." sent, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
