<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WharfList Widget Demo</title>
    <style>
        body { font-family: Georgia, 'Times New Roman', serif; margin: 0; background: #f4f1ea; color: #111; }
        main { max-width: 640px; margin: 0 auto; padding: 3rem 1.25rem; }
        h1 { font-size: 1.75rem; margin: 0 0 .5rem; }
        p { color: #444; line-height: 1.5; }
        code { background: #fff; padding: .1rem .35rem; border: 1px solid #ddd; }
        .panel { margin-top: 2rem; padding: 1.5rem; background: #fff; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <main>
        <h1>Widget demo</h1>
        <p>
            Open DevTools → Console. Look for <code>[WharfList]</code> logs.
            Site domain must match this page host for Referer checks
            (this demo uses <code>{{ $site->domain }}</code>).
        </p>
        <p>API key prefix: <code>{{ \Illuminate\Support\Str::limit($site->api_key, 12, '…') }}</code></p>

        <div class="panel">
            <div id="wharflist-form"></div>
        </div>
    </main>

    <script>
    (function () {
        var script = document.createElement('script');
        script.src = @json(url('/widget.js'));
        script.setAttribute('data-api-key', @json($site->api_key));
        script.setAttribute('data-site-id', @json((string) $site->id));
        document.head.appendChild(script);
    })();
    </script>
</body>
</html>
