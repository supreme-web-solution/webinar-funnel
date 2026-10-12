<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccessGrantedMail extends Mailable
{
    use Queueable, SerializesModels;

    private const ROLE_LABELS = [
        'FE' => 'Front End (app features)',
        'Bundle' => 'Bundle (full access)',
        'Reseller' => 'Reseller',
        'Affiliate Campaign Vault' => 'Affiliate Campaign Vault',
        'Profit Multiplier' => 'Profit Multiplier',
    ];

    public function __construct(
        public readonly User $user,
        public readonly string $roleName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                (string) config('mail.from.address'),
                (string) config('mail.from.name'),
            ),
            subject: 'Your '.$this->accessLabel().' access is active',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.access-granted',
            with: ['accessLabel' => $this->accessLabel()],
        );
    }

    public function accessLabel(): string
    {
        return self::ROLE_LABELS[$this->roleName] ?? $this->roleName;
    }
}
