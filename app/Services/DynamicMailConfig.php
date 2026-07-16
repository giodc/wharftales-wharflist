<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use MarceloEatWorld\PlunkLaravel\Services\PlunkService;

class DynamicMailConfig
{
    public function __construct(protected SettingsService $settings) {}

    public function apply(): void
    {
        $mailer = $this->settings->get('mail_mailer', 'smtp');

        if ($mailer === 'plunk') {
            $this->applyPlunk();
        } else {
            $this->applySmtp();
        }

        $from = $this->settings->get('smtp_from') ?: config('mail.from.address');

        Config::set('mail.from', [
            'address' => $from,
            'name' => $this->settings->siteName(),
        ]);
    }

    protected function applySmtp(): void
    {
        $host = $this->settings->get('smtp_host');

        if (blank($host)) {
            return;
        }

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp', array_merge(config('mail.mailers.smtp', []), [
            'transport' => 'smtp',
            'host' => $host,
            'port' => (int) $this->settings->get('smtp_port', 587),
            'encryption' => ((int) $this->settings->get('smtp_port', 587) === 465) ? 'ssl' : 'tls',
            'username' => $this->settings->get('smtp_user'),
            'password' => $this->settings->get('smtp_pass'),
            'timeout' => null,
        ]));

        Mail::purge('smtp');
    }

    protected function applyPlunk(): void
    {
        $apiKey = $this->settings->get('plunk_api_key') ?: config('plunk.api_key');

        if (blank($apiKey)) {
            return;
        }

        $apiUrl = $this->settings->get('plunk_api_url') ?: config('plunk.api_url', 'https://api.useplunk.com');
        $endpoint = $this->settings->get('plunk_api_endpoint') ?: config('plunk.endpoint', '/v1/send');

        Config::set('mail.default', 'plunk');
        Config::set('mail.mailers.plunk', [
            'transport' => 'plunk',
        ]);
        Config::set('plunk.api_key', $apiKey);
        Config::set('plunk.api_url', $apiUrl);
        Config::set('plunk.endpoint', $endpoint);

        // Singleton was built from boot-time config; refresh with settings.
        app()->forgetInstance(PlunkService::class);
        app()->instance(PlunkService::class, new PlunkService($apiKey, $apiUrl, $endpoint));

        Mail::purge('plunk');
    }
}
