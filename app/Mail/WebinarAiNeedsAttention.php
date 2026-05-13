<?php

namespace App\Mail;

use App\Models\Funnel;
use App\Models\ChatMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WebinarAiNeedsAttention extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Funnel $funnel,
        public readonly ChatMessage $incomingMessage,
        public readonly string $reason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Webinar AI needs attention: '.$this->funnel->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.webinar-ai-needs-attention',
        );
    }
}

