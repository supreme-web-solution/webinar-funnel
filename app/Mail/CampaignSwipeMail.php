<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CampaignSwipeMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $body,
        public string $campaignName,
        public string $recipientName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->renderBody(),
        );
    }

    protected function renderBody(): string
    {
        $body = nl2br(e(trim($this->body)));
        $name = e($this->recipientName);
        $campaign = e($this->campaignName);

        return <<<HTML
<!DOCTYPE html>
<html>
<body style="font-family: system-ui, sans-serif; line-height: 1.6; color: #0f172a; max-width: 640px; margin: 0 auto; padding: 24px;">
<p>Hi {$name},</p>
<div style="margin: 16px 0;">{$body}</div>
<p style="color: #64748b; font-size: 12px; margin-top: 32px;">Sent via {$campaign}</p>
</body>
</html>
HTML;
    }
}
