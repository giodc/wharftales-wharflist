<?php

use App\Concerns\PasswordValidationRules;
use App\Models\MailingList;
use App\Models\User;
use App\Services\SettingsService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts::auth.wide')]
#[Title('Setup WharfList')]
class extends Component {
    use PasswordValidationRules;

    public string $name = '';
    public string $username = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $site_name = 'WharfList';
    public string $site_url = '';
    public string $timezone = 'UTC';
    public string $smtp_host = '';
    public string $smtp_port = '587';
    public string $smtp_user = '';
    public string $smtp_pass = '';
    public string $smtp_from = '';

    public function mount(SettingsService $settings): void
    {
        if ($settings->isInstalled()) {
            $this->redirect(route('dashboard'), navigate: true);

            return;
        }

        $this->site_url = rtrim(request()->getSchemeAndHttpHost() ?: (string) config('app.url'), '/');
        $this->timezone = config('app.timezone', 'UTC');
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

    public function install(SettingsService $settings): void
    {
        if ($settings->isInstalled()) {
            $this->redirect(route('dashboard'), navigate: true);

            return;
        }

        try {
            $validated = $this->validate([
                'name' => ['required', 'string', 'max:255'],
                'username' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:users,username'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'password' => $this->passwordRules(),
                'site_name' => ['required', 'string', 'max:255'],
                'site_url' => ['required', 'string', 'max:255', 'regex:/^https?:\/\/.+/i'],
                'timezone' => ['required', 'timezone'],
                'smtp_host' => ['nullable', 'string', 'max:255'],
                'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
                'smtp_user' => ['nullable', 'string', 'max:255'],
                'smtp_pass' => ['nullable', 'string', 'max:255'],
                'smtp_from' => ['nullable', 'email', 'max:255'],
            ]);
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: __('Please fix the highlighted fields.'));

            throw $e;
        }

        try {
            $user = DB::transaction(function () use ($validated, $settings) {
                $user = User::query()->create([
                    'name' => $validated['name'],
                    'username' => $validated['username'],
                    'email' => $validated['email'],
                    'password' => $validated['password'],
                    'email_verified_at' => now(),
                ]);

                MailingList::query()->create([
                    'name' => 'Default',
                    'description' => 'Default mailing list',
                    'is_default' => true,
                ]);

                $settings->setMany([
                    'site_name' => $validated['site_name'],
                    'site_url' => rtrim($validated['site_url'], '/'),
                    'timezone' => $validated['timezone'],
                    'smtp_host' => $validated['smtp_host'] ?: null,
                    'smtp_port' => $validated['smtp_port'] ?: 587,
                    'smtp_user' => $validated['smtp_user'] ?: null,
                    'smtp_pass' => $validated['smtp_pass'] ?: null,
                    'smtp_from' => $validated['smtp_from'] ?: null,
                    'emails_per_batch' => 50,
                    'delay_between_emails' => 0,
                ]);

                $settings->markInstalled();

                return $user;
            });
        } catch (\Throwable $e) {
            report($e);
            Flux::toast(variant: 'danger', text: __('Setup failed: :message', ['message' => $e->getMessage()]));

            return;
        }

        Auth::login($user);

        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        // Full browser redirect so the new session cookie is applied reliably.
        $this->redirect(route('dashboard'), navigate: false);
    }
}; ?>

<div class="w-full">
    <div class="mb-8 text-center">
        <flux:heading size="lg">{{ __('Set up WharfList') }}</flux:heading>
        <flux:subheading>{{ __('Create your admin account and configure the basics') }}</flux:subheading>
    </div>

    @if ($errors->any())
        <div class="mb-6 rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200" role="alert">
            <p class="mb-2 font-medium">{{ __('Please fix the following:') }}</p>
            <ul class="list-disc space-y-1 ps-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form wire:submit="install" class="space-y-8">
        <div class="space-y-4">
            <flux:heading size="sm">{{ __('Administrator') }}</flux:heading>
            <flux:input wire:model="name" :label="__('Name')" required autocomplete="name" />
            <flux:input wire:model="username" :label="__('Username')" required autocomplete="username" />
            <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />
            <flux:input wire:model="password" :label="__('Password')" type="password" required autocomplete="new-password" viewable description="{{ __('At least 8 characters.') }}" />
            <flux:input wire:model="password_confirmation" :label="__('Confirm password')" type="password" required autocomplete="new-password" viewable />
        </div>

        <flux:separator />

        <div class="space-y-4">
            <flux:heading size="sm">{{ __('Site') }}</flux:heading>
            <flux:input wire:model="site_name" :label="__('Site name')" required />
            <flux:input wire:model="site_url" :label="__('Site URL')" type="url" required placeholder="http://wharftales-wharflist.test" description="{{ __('Use your local Herd URL, including http:// or https://') }}" />
            <flux:select wire:model="timezone" :label="__('Timezone')">
                @foreach ($this->timezones() as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:separator />

        <div class="space-y-4">
            <flux:heading size="sm">{{ __('SMTP (optional)') }}</flux:heading>
            <flux:subheading>{{ __('You can configure mail delivery later in settings.') }}</flux:subheading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="smtp_host" :label="__('SMTP host')" placeholder="smtp.example.com" />
                <flux:input wire:model="smtp_port" :label="__('SMTP port')" type="number" />
            </div>
            <flux:input wire:model="smtp_user" :label="__('SMTP username')" />
            <flux:input wire:model="smtp_pass" :label="__('SMTP password')" type="password" viewable />
            <flux:input wire:model="smtp_from" :label="__('From address')" type="email" />
        </div>

        <flux:button variant="primary" type="submit" class="w-full" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="install">{{ __('Complete setup') }}</span>
            <span wire:loading wire:target="install">{{ __('Installing…') }}</span>
        </flux:button>
    </form>
</div>
