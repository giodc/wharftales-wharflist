<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Site extends Model
{
    protected $fillable = [
        'name',
        'domain',
        'list_id',
        'api_key',
        'custom_fields',
    ];

    protected static function booted(): void
    {
        static::creating(function (Site $site): void {
            if (blank($site->api_key)) {
                $site->api_key = bin2hex(random_bytes(32));
            }

            $site->domain = static::normalizeDomain($site->domain);
        });

        static::updating(function (Site $site): void {
            if ($site->isDirty('domain')) {
                $site->domain = static::normalizeDomain($site->domain);
            }
        });
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(MailingList::class, 'list_id');
    }

    public function subscribers(): HasMany
    {
        return $this->hasMany(Subscriber::class);
    }

    public function maskedApiKey(): string
    {
        if (strlen($this->api_key) <= 8) {
            return str_repeat('*', strlen($this->api_key));
        }

        return Str::mask($this->api_key, '*', 4, -4);
    }

    public static function normalizeDomain(?string $domain): string
    {
        $domain = strtolower(trim((string) $domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = preg_replace('#/.*$#', '', $domain) ?? $domain;
        $domain = preg_replace('#^www\.#', '', $domain) ?? $domain;

        return rtrim($domain, '.');
    }

    public function matchesRefererDomain(?string $referer): bool
    {
        if (blank($referer)) {
            return true;
        }

        $host = parse_url($referer, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        $normalized = static::normalizeDomain($host);

        if ($normalized === $this->domain) {
            return true;
        }

        // Allow the WharfList app host itself (local widget demo / preview pages).
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($appHost)
            && $appHost !== ''
            && $normalized === static::normalizeDomain($appHost);
    }
}

