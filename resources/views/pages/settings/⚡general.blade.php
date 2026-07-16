<?php

use App\Services\SettingsService;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('General settings')] class extends Component {
    use WithFileUploads;

    public string $site_name = '';
    public string $site_url = '';
    public string $timezone = 'UTC';
    public $email_logo;
    public ?string $email_logo_current = null;
    public string $footer_company = '';
    public string $footer_address = '';
    public string $footer_city = '';
    public string $footer_country = '';

    public function mount(SettingsService $settings): void
    {
        $this->site_name = (string) $settings->get('site_name', '');
        $this->site_url = (string) $settings->get('site_url', '');
        $this->timezone = $settings->timezone();
        $this->email_logo_current = $settings->get('email_logo');
        $this->footer_company = (string) $settings->get('footer_company', '');
        $this->footer_address = (string) $settings->get('footer_address', '');
        $this->footer_city = (string) $settings->get('footer_city', '');
        $this->footer_country = (string) $settings->get('footer_country', '');
    }

    /**
     * @return array<string, string>
     */
    public function timezones(): array
    {
        return [
            'UTC' => 'UTC',
            'America/New_York' => 'America/New York',
            'America/Chicago' => 'America/Chicago',
            'America/Denver' => 'America/Denver',
            'America/Los_Angeles' => 'America/Los Angeles',
            'America/Phoenix' => 'America/Phoenix',
            'Europe/London' => 'Europe/London',
            'Europe/Paris' => 'Europe/Paris',
            'Europe/Berlin' => 'Europe/Berlin',
            'Asia/Tokyo' => 'Asia/Tokyo',
            'Asia/Shanghai' => 'Asia/Shanghai',
            'Australia/Sydney' => 'Australia/Sydney',
            'Pacific/Auckland' => 'Pacific/Auckland',
        ];
    }

    public function save(SettingsService $settings): void
    {
        $validated = $this->validate([
            'site_name' => ['required', 'string', 'max:255'],
            'site_url' => ['required', 'url', 'max:255'],
            'timezone' => ['required', 'timezone'],
            'email_logo' => ['nullable', 'image', 'max:5120'],
            'footer_company' => ['nullable', 'string', 'max:255'],
            'footer_address' => ['nullable', 'string', 'max:255'],
            'footer_city' => ['nullable', 'string', 'max:255'],
            'footer_country' => ['nullable', 'string', 'max:255'],
        ]);

        $logoUrl = $this->email_logo_current;

        if ($this->email_logo) {
            $path = $this->email_logo->store('logos', 'public');
            $logoUrl = Storage::disk('public')->url($path);
        }

        $settings->setMany([
            'site_name' => $validated['site_name'],
            'site_url' => rtrim($validated['site_url'], '/'),
            'timezone' => $validated['timezone'],
            'email_logo' => $logoUrl,
            'footer_company' => $validated['footer_company'] ?: null,
            'footer_address' => $validated['footer_address'] ?: null,
            'footer_city' => $validated['footer_city'] ?: null,
            'footer_country' => $validated['footer_country'] ?: null,
        ]);

        $this->email_logo_current = $logoUrl;
        $this->reset('email_logo');

        Flux::toast(variant: 'success', text: __('Settings saved.'));
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('General')" :subheading="__('Site branding, timezone, and email footer')">
        <form wire:submit="save" class="space-y-6">
            <flux:input wire:model="site_name" :label="__('Site name')" required />
            <flux:input wire:model="site_url" :label="__('Site URL')" type="url" required />
            <flux:select wire:model="timezone" :label="__('Timezone')">
                @foreach ($this->timezones() as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <div>
                <flux:label>{{ __('Email logo') }}</flux:label>
                @if ($email_logo_current)
                    <img src="{{ $email_logo_current }}" alt="" class="mt-2 h-12 object-contain" />
                @endif
                <input type="file" wire:model="email_logo" accept="image/*" class="mt-2 block w-full text-sm" />
            </div>

            <flux:separator />

            <flux:heading size="sm">{{ __('Email footer') }}</flux:heading>
            <flux:input wire:model="footer_company" :label="__('Company')" />
            <flux:input wire:model="footer_address" :label="__('Address')" />
            <flux:input wire:model="footer_city" :label="__('City')" />
            <flux:input wire:model="footer_country" :label="__('Country')" />

            <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
        </form>
    </x-pages::settings.layout>
</section>
