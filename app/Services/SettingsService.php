<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SettingsService
{
    public const INSTALLED_KEY = 'installed';

    /**
     * Settings keys encrypted at rest.
     *
     * @var list<string>
     */
    protected array $encrypted = [
        'smtp_pass',
        'plunk_api_key',
    ];

    /**
     * @var array<string, mixed>
     */
    protected array $memo = [];

    public function isInstalled(): bool
    {
        try {
            if (! Schema::hasTable('settings')) {
                return false;
            }

            if (Setting::query()->where('key', self::INSTALLED_KEY)->where('value', '1')->exists()) {
                return true;
            }

            // Recover if admin exists but the flag was never written.
            return Schema::hasTable('users') && User::query()->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    public function markInstalled(): void
    {
        $this->set(self::INSTALLED_KEY, '1');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        try {
            $value = Setting::query()->where('key', $key)->value('value');
        } catch (\Throwable) {
            return $default;
        }

        if ($value === null) {
            return $default;
        }

        if (in_array($key, $this->encrypted, true) && filled($value)) {
            try {
                $value = Crypt::decryptString($value);
            } catch (\Throwable) {
                // leave as stored value
            }
        }

        if ($key === self::INSTALLED_KEY && in_array($value, ['0', '1'], true)) {
            $value = $value === '1';
        }

        return $this->memo[$key] = $value;
    }

    public function set(string $key, mixed $value): void
    {
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        $stored = $value;

        if (in_array($key, $this->encrypted, true) && filled($value)) {
            $stored = Crypt::encryptString((string) $value);
        }

        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $stored === null ? null : (string) $stored],
        );

        unset($this->memo[$key]);

        if ($key === self::INSTALLED_KEY) {
            $this->memo[$key] = $value === '1' || $value === 1 || $value === true;
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function all(array $keys = []): array
    {
        $query = Setting::query();

        if ($keys !== []) {
            $query->whereIn('key', $keys);
        }

        $result = [];

        foreach ($query->pluck('value', 'key') as $key => $value) {
            $result[$key] = $this->get($key);
        }

        return $result;
    }

    public function siteUrl(): string
    {
        return rtrim((string) $this->get('site_url', config('app.url')), '/');
    }

    public function siteName(): string
    {
        return (string) $this->get('site_name', config('app.name', 'WharfList'));
    }

    public function timezone(): string
    {
        return (string) $this->get('timezone', config('app.timezone', 'UTC'));
    }

    public function emailLogoUrl(): ?string
    {
        $logo = $this->get('email_logo');

        if (blank($logo)) {
            return null;
        }

        $logo = (string) $logo;

        if (str_starts_with($logo, 'http://') || str_starts_with($logo, 'https://')) {
            return $logo;
        }

        return Storage::disk('public')->url($logo);
    }

    /**
     * @return 'left'|'center'|'right'
     */
    public function emailLogoPosition(): string
    {
        $position = (string) $this->get('email_logo_position', 'left');

        return in_array($position, ['left', 'center', 'right'], true) ? $position : 'left';
    }
}
