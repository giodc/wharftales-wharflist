<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $siteName }} — Unsubscribe</title>
    <style>
        body { font-family: Georgia, 'Times New Roman', serif; background: #f6f4ef; color: #111; margin: 0; }
        .wrap { min-height: 100vh; display: grid; place-items: center; padding: 2rem; }
        .card { max-width: 28rem; text-align: center; }
        h1 { font-size: 2rem; margin: 0 0 .75rem; }
        p { font-size: 1.05rem; line-height: 1.5; color: #333; }
        button { background: #111; color: #fff; border: 0; padding: .85rem 1.4rem; font: inherit; cursor: pointer; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <p style="letter-spacing:.08em;text-transform:uppercase;font-size:.75rem;color:#666;">{{ $siteName }}</p>
            @if ($status === 'confirm')
                <h1>Unsubscribe?</h1>
                <p>Confirm unsubscribing <strong>{{ $email }}</strong>@if(!empty($listId)) from this list@else from all emails@endif.</p>
                <form method="POST" action="{{ $actionUrl }}" style="margin-top:1.5rem;">
                    @csrf
                    <button type="submit">Confirm unsubscribe</button>
                </form>
            @elseif ($status === 'success')
                <h1>Unsubscribed</h1>
                <p>You have been unsubscribed{{ !empty($listSpecific) ? ' from this list' : '' }}.</p>
            @else
                <h1>Invalid link</h1>
                <p>This unsubscribe link is invalid or has expired.</p>
            @endif
        </div>
    </div>
</body>
</html>
