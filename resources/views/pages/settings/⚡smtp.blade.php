<?php

use App\Mail\TestMail;
use App\Services\DynamicMailConfig;
use App\Services\SettingsService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Mail settings')] class extends Component {
    public string $mail_mailer = 'smtp';
    public string $smtp_host = '';
    public string $smtp_port = '587';
    public string $smtp_user = '';
    public string $smtp_pass = '';
    public string $smtp_from = '';
    public string $plunk_api_key = '';
    public string $plunk_api_url = 'https://api.useplunk.com';
    public string $plunk_api_endpoint = '/v1/send';
    public bool $has_plunk_api_key = false;
    public bool $has_smtp_pass = false;
    public bool $smtp_from_env = false;
    public bool $plunk_from_env = false;
    public bool $plunk_key_only_from_env = false;
    public int $emails_per_batch = 50;
    public int $delay_between_emails = 0;

    public function mount(SettingsService $settings): void
    {
        $this->smtp_from_env = $this->smtpConfiguredInEnv();
        $this->plunk_from_env = $this->plunkConfiguredInEnv();

        $defaultMailer = config('mail.default') === 'plunk' ? 'plunk' : 'smtp';

        $this->mail_mailer = (string) $settings->get('mail_mailer', $defaultMailer);
        $this->smtp_host = (string) $settings->get('smtp_host', '');
        $this->smtp_port = (string) $settings->get('smtp_port', '587');
        $this->smtp_user = (string) $settings->get('smtp_user', '');
        $this->smtp_pass = '';
        $this->has_smtp_pass = filled($settings->get('smtp_pass'))
            || ($this->smtp_from_env && filled(config('mail.mailers.smtp.password')));
        $this->smtp_from = (string) $settings->get('smtp_from', '');
        $this->refreshPlunkKeyFlags($settings);
        $this->plunk_api_key = '';
        $this->plunk_api_url = (string) $settings->get('plunk_api_url', config('plunk.api_url', 'https://api.useplunk.com'));
        $this->plunk_api_endpoint = (string) $settings->get('plunk_api_endpoint', config('plunk.endpoint', '/v1/send'));
        $this->emails_per_batch = (int) $settings->get('emails_per_batch', 50);
        $this->delay_between_emails = (int) $settings->get('delay_between_emails', 0);
    }

    protected function refreshPlunkKeyFlags(SettingsService $settings): void
    {
        $savedKey = $settings->get('plunk_api_key');
        $envKey = config('plunk.api_key');

        $this->has_plunk_api_key = filled($savedKey ?: $envKey);
        $this->plunk_key_only_from_env = blank($savedKey) && filled($envKey);
    }

    protected function smtpConfiguredInEnv(): bool
    {
        return config('mail.default') === 'smtp'
            && filled(config('mail.mailers.smtp.host'));
    }

    protected function plunkConfiguredInEnv(): bool
    {
        return filled(config('plunk.api_key'));
    }

    protected function smtpHostAvailable(SettingsService $settings, ?string $formHost = null): bool
    {
        return filled($formHost)
            || filled($settings->get('smtp_host'))
            || $this->smtpConfiguredInEnv();
    }

    public function save(SettingsService $settings): void
    {
        $validated = $this->validate([
            'mail_mailer' => ['required', Rule::in(['smtp', 'plunk'])],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_user' => ['nullable', 'string', 'max:255'],
            'smtp_pass' => ['nullable', 'string', 'max:255'],
            'smtp_from' => ['nullable', 'email', 'max:255'],
            'plunk_api_key' => ['nullable', 'string', 'max:255'],
            'plunk_api_url' => ['nullable', 'url', 'max:255'],
            'plunk_api_endpoint' => ['nullable', 'string', 'max:255'],
            'emails_per_batch' => ['required', 'integer', 'min:1', 'max:500'],
            'delay_between_emails' => ['required', 'integer', 'min:0', 'max:60'],
        ]);

        if ($validated['mail_mailer'] === 'smtp' && ! $this->smtpHostAvailable($settings, $validated['smtp_host'])) {
            $this->addError('smtp_host', __('SMTP host is required when using SMTP.'));

            return;
        }

        $existingPlunkKey = $settings->get('plunk_api_key') ?: config('plunk.api_key');

        if ($validated['mail_mailer'] === 'plunk' && blank($validated['plunk_api_key']) && blank($existingPlunkKey)) {
            $this->addError('plunk_api_key', __('Plunk API key is required when using Plunk.'));

            return;
        }

        $settings->setMany([
            'mail_mailer' => $validated['mail_mailer'],
            'smtp_host' => $validated['smtp_host'] ?: null,
            'smtp_port' => $validated['smtp_port'] ?: 587,
            'smtp_user' => $validated['smtp_user'] ?: null,
            'smtp_pass' => filled($validated['smtp_pass']) ? $validated['smtp_pass'] : $settings->get('smtp_pass'),
            'smtp_from' => $validated['smtp_from'] ?: null,
            'plunk_api_key' => filled($validated['plunk_api_key']) ? $validated['plunk_api_key'] : $settings->get('plunk_api_key'),
            'plunk_api_url' => $validated['plunk_api_url'] ?: 'https://api.useplunk.com',
            'plunk_api_endpoint' => $validated['plunk_api_endpoint'] ?: '/v1/send',
            'emails_per_batch' => $validated['emails_per_batch'],
            'delay_between_emails' => $validated['delay_between_emails'],
        ]);

        $this->smtp_pass = '';
        $this->plunk_api_key = '';
        $this->has_smtp_pass = filled($settings->get('smtp_pass'))
            || ($this->smtp_from_env && filled(config('mail.mailers.smtp.password')));
        $this->refreshPlunkKeyFlags($settings);

        Flux::toast(variant: 'success', text: __('Mail settings saved.'));
    }

    public function sendTest(DynamicMailConfig $mailConfig, SettingsService $settings): void
    {
        $this->save($settings);

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        if ($this->mail_mailer === 'plunk' && blank($settings->get('plunk_api_key') ?: config('plunk.api_key'))) {
            Flux::toast(variant: 'danger', text: __('Configure a Plunk API key before sending a test.'));

            return;
        }

        if ($this->mail_mailer === 'smtp' && ! $this->smtpHostAvailable($settings, $this->smtp_host)) {
            Flux::toast(variant: 'danger', text: __('Configure SMTP host before sending a test.'));

            return;
        }

        try {
            $mailConfig->apply();
            Mail::to(Auth::user()->email)->send(new TestMail);

            Flux::toast(variant: 'success', text: __('Test email sent to :email', ['email' => Auth::user()->email]));
        } catch (\Throwable $e) {
            Flux::toast(variant: 'danger', text: __('Failed to send test email: :message', ['message' => $e->getMessage()]));
        }
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Mail')" :subheading="__('Delivery provider, SMTP or Plunk, and send throttling')">
        <form wire:submit="save" class="space-y-6">
            <flux:radio.group wire:model.live="mail_mailer" :label="__('Mail provider')" variant="segmented">
                <flux:radio value="smtp">{{ __('SMTP') }}</flux:radio>
                <flux:radio value="plunk">{{ __('Plunk') }}</flux:radio>
            </flux:radio.group>

            <flux:input wire:model="smtp_from" :label="__('From address')" type="email" placeholder="hello@example.com" />

            @if ($mail_mailer === 'smtp')
                <flux:separator />
                <flux:heading size="sm">{{ __('SMTP') }}</flux:heading>

                @if ($smtp_from_env)
                    <flux:callout
                        variant="success"
                        icon="check-circle"
                        :heading="__('SMTP is already configured in .env')"
                        :text="__('MAIL_* values are set. Leave fields blank to keep using them, or enter values here to override.')"
                    />
                @endif

                <flux:input wire:model="smtp_host" :label="__('SMTP host')" placeholder="smtp.example.com" />
                <flux:input wire:model="smtp_port" :label="__('SMTP port')" type="number" />
                <flux:input wire:model="smtp_user" :label="__('SMTP username')" />
                <flux:input
                    wire:model="smtp_pass"
                    :label="__('SMTP password')"
                    type="password"
                    viewable
                    :placeholder="$has_smtp_pass ? '••••••••' : null"
                    :description="$has_smtp_pass ? __('Leave blank to keep the existing password.') : null"
                />
            @else
                <flux:separator />
                <flux:heading size="sm">{{ __('Plunk') }}</flux:heading>
                <flux:subheading>{{ __('Optional alternative to SMTP. Works with Plunk cloud or self-hosted. You can also set PLUNK_* in .env.') }}</flux:subheading>

                @if ($plunk_from_env)
                    <flux:callout
                        variant="success"
                        icon="check-circle"
                        :heading="__('Plunk is already configured in .env')"
                        :text="__('PLUNK_API_KEY is set. Leave the API key blank to keep using it, or enter a new key here to override.')"
                    />
                @endif

                <flux:input
                    wire:model="plunk_api_key"
                    :label="__('API key')"
                    type="password"
                    viewable
                    :placeholder="$has_plunk_api_key ? '••••••••' : 'sk_…'"
                    :description="$has_plunk_api_key ? ($plunk_key_only_from_env ? __('Leave blank to keep the key from .env.') : __('Leave blank to keep the saved key.')) : null"
                />
                <flux:input wire:model="plunk_api_url" :label="__('API URL')" placeholder="https://api.useplunk.com" />
                <flux:input wire:model="plunk_api_endpoint" :label="__('Send endpoint')" placeholder="/v1/send" description="{{ __('Cloud: /v1/send · Self-hosted: /api/v1/send') }}" />
            @endif

            <flux:separator />

            <flux:heading size="sm">{{ __('Campaign throttling') }}</flux:heading>
            <flux:input wire:model="emails_per_batch" :label="__('Emails per batch')" type="number" min="1" max="500" />
            <flux:input wire:model="delay_between_emails" :label="__('Delay between emails (seconds)')" type="number" min="0" max="60" />

            <div class="flex flex-wrap gap-3">
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                <flux:button type="button" variant="ghost" wire:click="sendTest">{{ __('Send test email') }}</flux:button>
            </div>
        </form>
    </x-pages::settings.layout>
</section>
