# WharfList

Email collection and newsletter system rebuilt on Laravel 13 + Livewire 4 + Flux (free) + Alpine (via Livewire).

Starter kit base: [laravel/livewire-starter-kit](https://github.com/laravel/livewire-starter-kit).

## Stack

- Laravel 13
- Livewire 4 + Alpine.js
- Flux free UI components (`livewire/flux` v2 — current package major)
- Fortify (login, TOTP 2FA, recovery codes, passkeys)
- SQLite by default (MySQL/Postgres supported)
- `andreapollastri/checkpoint` (dev security scanner)
- `league/csv` for imports

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install && npm run build
composer run dev
```

Open the app — first visit runs the install wizard at `/setup`.

## Scheduler / queues

```bash
php artisan schedule:work
php artisan queue:work
```

## Public embed

```html
<div id="wharflist-form"></div>
<script>
(function() {
    var script = document.createElement('script');
    script.src = 'https://yourdomain.com/widget.js';
    script.setAttribute('data-api-key', 'YOUR_API_KEY');
    script.setAttribute('data-site-id', 'YOUR_SITE_ID');
    document.head.appendChild(script);
})();
</script>
```

See `DEV.md` for the full feature inventory and acceptance criteria.
