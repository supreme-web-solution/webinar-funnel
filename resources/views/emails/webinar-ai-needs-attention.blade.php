<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webinar AI Needs Attention</title>
</head>
<body style="font-family: Arial, sans-serif; background:#0f1117; color:#e2e8f0; margin:0; padding:24px;">
    <div style="max-width:640px; margin:0 auto; background:#1a1f2e; border:1px solid #2d3748; border-radius:12px; overflow:hidden;">
        <div style="padding:20px 24px; border-bottom:1px solid #2d3748;">
            <h1 style="margin:0; font-size:20px; color:#fff;">Webinar AI needs manual follow-up</h1>
            <p style="margin:8px 0 0; font-size:13px; color:#94a3b8;">
                The assistant could not send a confident reply in chat.
            </p>
        </div>
        <div style="padding:20px 24px;">
            <p style="margin:0 0 10px; font-size:13px;"><strong>Funnel:</strong> {{ $funnel->name }} (ID: {{ $funnel->id }})</p>
            <p style="margin:0 0 10px; font-size:13px;"><strong>Reason:</strong> {{ $reason }}</p>
            <p style="margin:0 0 6px; font-size:13px;"><strong>Attendee:</strong> {{ $incomingMessage->attendee_name ?? $incomingMessage->author_name }}</p>
            <p style="margin:0 0 12px; font-size:13px;"><strong>Email:</strong> {{ $incomingMessage->attendee_email ?? 'N/A' }}</p>
            <div style="background:#0f1117; border:1px solid #2d3748; border-radius:8px; padding:12px;">
                <p style="margin:0 0 6px; font-size:12px; color:#94a3b8;">Incoming message</p>
                <p style="margin:0; font-size:14px; color:#e2e8f0; white-space:pre-wrap;">{{ $incomingMessage->message }}</p>
            </div>
            <p style="margin:14px 0 0;">
                <a href="{{ url('/funnels/'.$funnel->id.'/chat') }}" style="display:inline-block; padding:10px 14px; border-radius:8px; text-decoration:none; background:#40E0D0; color:#061019; font-weight:700; font-size:13px;">
                    Open Chat Manager
                </a>
            </p>
        </div>
    </div>
</body>
</html>

