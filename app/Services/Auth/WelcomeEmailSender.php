<?php

namespace App\Services\Auth;

use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class WelcomeEmailSender
{
    /**
     * Emails login details and records welcome_email_sent_at.
     *
     * @param  list<string>  $copyTo
     * @return string|null Error message when the email could not be sent.
     */
    public function send(User $user, string $password, array $copyTo = [], string $context = 'welcome'): ?string
    {
        try {
            Mail::to($user->email)->send(new WelcomeMail($user, $password));
        } catch (Throwable $exception) {
            Log::error('Welcome email failed to send.', [
                'context' => $context,
                'user_id' => $user->id,
                'email' => $user->email,
                'mailer' => config('mail.default'),
                'exception' => $exception->getMessage(),
            ]);

            report($exception);

            return $exception->getMessage();
        }

        $user->forceFill(['welcome_email_sent_at' => now()])->save();

        Log::info('Welcome email sent.', [
            'context' => $context,
            'user_id' => $user->id,
            'email' => $user->email,
            'mailer' => config('mail.default'),
        ]);

        foreach ($copyTo as $address) {
            try {
                Mail::to($address)->send(new WelcomeMail($user, $password));
            } catch (Throwable $exception) {
                Log::warning('Welcome email copy failed to send.', [
                    'user_id' => $user->id,
                    'copy_to' => $address,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return null;
    }

    /**
     * Gives the user a new password and emails it — for buyers whose welcome email never arrived.
     *
     * @return string|null Error message when the email could not be sent.
     */
    public function resetPasswordAndSend(User $user, string $context = 'resend'): ?string
    {
        $password = Str::random(12);
        $user->forceFill(['password' => $password])->save();

        return $this->send($user, $password, context: $context);
    }
}
