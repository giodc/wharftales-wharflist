<?php

use App\Services\SettingsService;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('General settings')] class extends Component {
    use WithFileUploads;

    public string $site_name = '';
    public string $site_url = '';
    public string $timezone = 'UTC';
    public $email_logo = null;
    public ?string $email_logo_current = null;
    public string $email_logo_position = 'left';
    public string $footer_company = '';
    public string $footer_address = '';
    public string $footer_city = '';
    public string $footer_country = '';

    public function mount(SettingsService $settings): void
    {
        $this->site_name = (string) $settings->get('site_name', '');
        $this->site_url = (string) $settings->get('site_url', '');
        $this->timezone = $settings->timezone();
        $this->email_logo_current = $settings->emailLogoUrl();
        $this->email_logo_position = $settings->emailLogoPosition();
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

    public function updatedEmailLogo(): void
    {
        $this->validateOnly('email_logo', [
            'email_logo' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
        ]);
    }

    public function removeEmailLogo(SettingsService $settings): void
    {
        $settings->set('email_logo', null);
        $this->email_logo_current = null;
        $this->reset('email_logo');

        Flux::toast(variant: 'success', text: __('Logo removed.'));
    }

    public function save(SettingsService $settings): void
    {
        $validated = $this->validate([
            'site_name' => ['required', 'string', 'max:255'],
            'site_url' => ['required', 'url', 'max:255'],
            'timezone' => ['required', 'timezone'],
            'email_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
            'email_logo_position' => ['required', Rule::in(['left', 'center', 'right'])],
            'footer_company' => ['nullable', 'string', 'max:255'],
            'footer_address' => ['nullable', 'string', 'max:255'],
            'footer_city' => ['nullable', 'string', 'max:255'],
            'footer_country' => ['nullable', 'string', 'max:255'],
        ]);

        $logoUrl = $this->email_logo_current;

        if ($this->email_logo) {
            $extension = strtolower((string) $this->email_logo->getClientOriginalExtension()) ?: 'png';
            $path = $this->email_logo->storeAs(
                'logos',
                Str::uuid()->toString().'.'.$extension,
                'public',
            );

            if ($path === false) {
                $this->addError('email_logo', __('Could not store the logo. Check storage permissions.'));

                return;
            }

            $logoUrl = Storage::disk('public')->url($path);
        }

        $settings->setMany([
            'site_name' => $validated['site_name'],
            'site_url' => rtrim($validated['site_url'], '/'),
            'timezone' => $validated['timezone'],
            'email_logo' => $logoUrl ?: null,
            'email_logo_position' => $validated['email_logo_position'],
            'footer_company' => $validated['footer_company'] ?: null,
            'footer_address' => $validated['footer_address'] ?: null,
            'footer_city' => $validated['footer_city'] ?: null,
            'footer_country' => $validated['footer_country'] ?: null,
        ]);

        $this->email_logo_current = $logoUrl ?: null;
        $this->reset('email_logo');

        Flux::toast(variant: 'success', text: __('Settings saved.'));
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('General')" :subheading="__('Site branding, email logo, timezone, and footer')">
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

                @if ($email_logo)
                    <img src="{{ $email_logo->temporaryUrl() }}" alt="" class="mt-2 h-12 object-contain" />
                @elseif ($email_logo_current)
                    <img src="{{ $email_logo_current }}" alt="" class="mt-2 h-12 object-contain" />
                @endif

                <input
                    type="file"
                    wire:model="email_logo"
                    accept="image/png,image/jpeg,image/gif,image/webp,.png,.jpg,.jpeg,.gif,.webp"
                    class="mt-2 block w-full text-sm"
                />

                <div wire:loading wire:target="email_logo" class="mt-1">
                    <flux:text variant="subtle">{{ __('Uploading…') }}</flux:text>
                </div>

                @error('email_logo')
                    <flux:text class="mt-1 text-red-500">{{ $message }}</flux:text>
                @enderror

                <flux:text variant="subtle" class="mt-1">{{ __('PNG, JPG, GIF, or WebP up to 5MB. Shown at the top of transactional and campaign emails.') }}</flux:text>

                @if ($email_logo_current && ! $email_logo)
                    <div class="mt-2">
                        <flux:button type="button" size="sm" class="border" variant="ghost" wire:click="removeEmailLogo" wire:confirm="{{ __('Remove the email logo?') }}">
                            {{ __('Remove logo') }}
                        </flux:button>
                    </div>
                @endif
            </div>

            <flux:radio.group wire:model="email_logo_position" :label="__('Logo position')" variant="segmented">
                <flux:radio value="left">{{ __('Left') }}</flux:radio>
                <flux:radio value="center">{{ __('Center') }}</flux:radio>
                <flux:radio value="right">{{ __('Right') }}</flux:radio>
            </flux:radio.group>

            <flux:separator />

            <flux:heading size="sm">{{ __('Email footer') }}</flux:heading>
            <flux:input wire:model="footer_company" :label="__('Company')" />
            <flux:input wire:model="footer_address" :label="__('Address')" />
            <flux:input wire:model="footer_city" :label="__('City')" />
            <flux:input wire:model="footer_country" :label="__('Country')" />

            <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="save,email_logo">
                <span wire:loading.remove wire:target="save,email_logo">{{ __('Save') }}</span>
                <span wire:loading wire:target="save,email_logo">{{ __('Saving…') }}</span>
            </flux:button>
        </form>
    </x-pages::settings.layout>
</section>
