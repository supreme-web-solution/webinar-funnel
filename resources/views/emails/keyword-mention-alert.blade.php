<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Mentions Alert</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0f1117; color: #e2e8f0; margin: 0; padding: 24px; }
        .wrapper { max-width: 600px; margin: 0 auto; }
        .header { background: #1a1f2e; border-radius: 12px 12px 0 0; padding: 28px 32px; border-bottom: 1px solid #2d3748; }
        .header h1 { margin: 0; font-size: 20px; color: #fff; }
        .header p { margin: 6px 0 0; font-size: 14px; color: #94a3b8; }
        .body { background: #1a1f2e; padding: 24px 32px; border-radius: 0 0 12px 12px; }
        .mention-card { background: #0f1117; border: 1px solid #2d3748; border-radius: 8px; padding: 16px; margin-bottom: 12px; }
        .mention-platform { display: inline-block; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 4px; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em; }
        .platform-reddit   { background: #ff4500/20; color: #ff6b35; background-color: rgba(255,69,0,0.15); }
        .platform-youtube  { background: rgba(255,0,0,0.15); color: #ff4444; }
        .platform-twitter  { background: rgba(29,161,242,0.15); color: #1da1f2; }
        .platform-news     { background: rgba(26,115,232,0.15); color: #4e9af1; }
        .mention-title { font-size: 14px; font-weight: 600; color: #e2e8f0; margin: 0 0 6px; }
        .mention-content { font-size: 13px; color: #94a3b8; margin: 0 0 10px; line-height: 1.5; }
        .mention-meta { font-size: 12px; color: #64748b; }
        .mention-link { display: inline-block; margin-top: 10px; font-size: 12px; color: #40E0D0; text-decoration: none; }
        .footer { margin-top: 24px; font-size: 12px; color: #4a5568; text-align: center; }
        .footer a { color: #40E0D0; text-decoration: none; }
        .stat-row { display: flex; gap: 12px; margin-bottom: 20px; }
        .stat { background: #0f1117; border: 1px solid #2d3748; border-radius: 8px; padding: 12px 16px; flex: 1; text-align: center; }
        .stat-value { font-size: 22px; font-weight: 700; color: #40E0D0; }
        .stat-label { font-size: 11px; color: #64748b; margin-top: 2px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>🔔 New Mentions Detected</h1>
            <p>
                Found <strong>{{ $mentions->count() }}</strong>
                {{ Str::plural('mention', $mentions->count()) }}
                for <strong>"{{ $keyword->name }}"</strong>
                on <strong>{{ ucfirst($platform) }}</strong>
            </p>
        </div>

        <div class="body">
            <div class="stat-row">
                <div class="stat">
                    <div class="stat-value">{{ $mentions->count() }}</div>
                    <div class="stat-label">New mentions</div>
                </div>
                <div class="stat">
                    <div class="stat-value">{{ ucfirst($platform) }}</div>
                    <div class="stat-label">Platform</div>
                </div>
                <div class="stat">
                    <div class="stat-value">{{ now()->format('M j') }}</div>
                    <div class="stat-label">Today</div>
                </div>
            </div>

            @foreach ($mentions->take(10) as $mention)
                <div class="mention-card">
                    <span class="mention-platform platform-{{ strtolower($platform) }}">
                        {{ ucfirst($platform) }}
                    </span>

                    @if ($mention->title)
                        <p class="mention-title">{{ Str::limit($mention->title, 120) }}</p>
                    @endif

                    @if ($mention->content)
                        <p class="mention-content">{{ Str::limit($mention->content, 200) }}</p>
                    @endif

                    <div class="mention-meta">
                        @if ($mention->username)
                            <strong>@</strong>{{ $mention->username }}
                        @endif
                        @if ($mention->posted_at)
                            &nbsp;·&nbsp; {{ $mention->posted_at->diffForHumans() }}
                        @endif
                        @if ($mention->like_count || $mention->retweet_count)
                            &nbsp;·&nbsp;
                            ❤️ {{ number_format($mention->like_count) }}
                            @if ($mention->retweet_count)
                                &nbsp; 🔁 {{ number_format($mention->retweet_count) }}
                            @endif
                        @endif
                    </div>

                    @if ($mention->permalink)
                        <a href="{{ $mention->permalink }}" class="mention-link">View on {{ ucfirst($platform) }} →</a>
                    @endif
                </div>
            @endforeach

            @if ($mentions->count() > 10)
                <p style="font-size: 13px; color: #64748b; text-align: center; margin: 12px 0;">
                    + {{ $mentions->count() - 10 }} more mention(s) in your dashboard.
                </p>
            @endif

            <p style="margin-top: 20px;">
                <a href="{{ config('app.url') }}/mentions" style="display: inline-block; background: #40E0D0; color: #060d1a; font-weight: 600; font-size: 13px; padding: 10px 20px; border-radius: 8px; text-decoration: none;">
                    View all mentions →
                </a>
            </p>
        </div>

        <div class="footer">
            <p>
                You're receiving this because you enabled notifications for
                <strong>"{{ $keyword->name }}"</strong>.
                <a href="{{ config('app.url') }}/mentions">Manage keywords</a>
            </p>
        </div>
    </div>
</body>
</html>
