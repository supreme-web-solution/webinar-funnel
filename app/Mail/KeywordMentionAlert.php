<?php

namespace App\Mail;

use App\Models\Keyword;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class KeywordMentionAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Keyword $keyword,
        public readonly Collection $mentions,
        public readonly string $platform,
    ) {}

    public function envelope(): Envelope
    {
        $count = $this->mentions->count();
        $word = $count === 1 ? 'mention' : 'mentions';
        $platformLabel = ucfirst($this->platform);

        return new Envelope(
            subject: "🔔 {$count} new {$word} for \"{$this->keyword->name}\" on {$platformLabel}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.keyword-mention-alert',
        );
    }
}
