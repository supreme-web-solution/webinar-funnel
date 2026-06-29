<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Session Expired - AffiliMachine Ai</title>
    <style>
        :root {
            --bg: #f8fafc;
            --surface: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --primary: #40E0D0;
            --accent: #FFAD00;
            --border: #e2e8f0;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif;
            background:
                radial-gradient(circle at 15% 10%, rgba(64, 224, 208, 0.18), transparent 40%),
                radial-gradient(circle at 85% 85%, rgba(255, 173, 0, 0.16), transparent 42%),
                var(--bg);
            color: var(--text);
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .card {
            width: 100%;
            max-width: 660px;
            border-radius: 20px;
            border: 1px solid var(--border);
            background: var(--surface);
            box-shadow: 0 20px 60px rgba(2, 8, 23, 0.08);
            padding: 34px 30px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: .02em;
            margin-bottom: 22px;
        }

        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--primary);
            box-shadow: 0 0 0 4px rgba(64, 224, 208, 0.18);
        }

        .eyebrow {
            display: inline-block;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: #92400e;
            background: rgba(255, 173, 0, 0.2);
            border: 1px solid rgba(255, 173, 0, 0.45);
            border-radius: 999px;
            padding: 6px 12px;
        }

        h1 {
            margin: 16px 0 8px;
            font-size: clamp(1.7rem, 4vw, 2.3rem);
            line-height: 1.15;
        }

        p {
            margin: 0;
            color: var(--muted);
            line-height: 1.65;
            font-size: 0.97rem;
        }

        .status {
            margin-top: 16px;
            font-size: 12px;
            font-weight: 700;
            color: #92400e;
            background: rgba(255, 173, 0, 0.2);
            border: 1px solid rgba(255, 173, 0, 0.5);
            border-radius: 999px;
            padding: 6px 12px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .status::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--accent);
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 26px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            padding: 11px 16px;
            transition: all .16s ease;
            border: 1px solid transparent;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #2dc4b5);
            color: #032525;
        }

        .btn-primary:hover { transform: translateY(-1px); opacity: .94; }

        .btn-secondary {
            color: #0f172a;
            background: #fff;
            border-color: var(--border);
        }

        .btn-secondary:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
        }
    </style>
</head>
<body>
<main class="card">
    <div class="brand">
        <span class="dot"></span>
        AffiliMachine Ai
    </div>

    <span class="eyebrow">Session Expired</span>
    <h1>Your session has expired.</h1>
    <p>
        For security, your session timed out. Refresh the page and try again.
        If you were submitting a form, you may need to sign in again.
    </p>
    <span class="status">Error 419</span>

    <div class="actions">
        <a class="btn btn-primary" href="{{ url()->current() }}">Refresh Page</a>
        <a class="btn btn-secondary" href="{{ route('login') }}">Sign In</a>
        <a class="btn btn-secondary" href="{{ url('/') }}">Back to Home</a>
    </div>
</main>
</body>
</html>
