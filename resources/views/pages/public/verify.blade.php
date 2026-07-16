<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $siteName }} — Verify</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, sans-serif;
            background: #ffffff;
            color: #111111;
            margin: 0;
        }
        .wrap { min-height: 100vh; display: grid; place-items: center; padding: 2rem; }
        .card { max-width: 28rem; text-align: center; }
        h1 { font-size: 2rem; font-weight: 600; margin: 0 0 .75rem; letter-spacing: -0.02em; }
        p { font-size: 1.05rem; line-height: 1.5; color: #333333; }
        .brand { letter-spacing: .08em; text-transform: uppercase; font-size: .75rem; font-weight: 500; color: #666666; margin: 0 0 1rem; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <p class="brand">{{ $siteName }}</p>
            @if ($status === 'success')
                <h1>You’re verified</h1>
                <p>Thanks — your subscription is confirmed.</p>
            @elseif ($status === 'already')
                <h1>Already verified</h1>
                <p>This email was already confirmed.</p>
            @else
                <h1>Invalid link</h1>
                <p>This verification link is invalid or has expired.</p>
            @endif
        </div>
    </div>
</body>
</html>
