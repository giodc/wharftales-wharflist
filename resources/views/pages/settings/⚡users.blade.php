<?php

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Users')] class extends Component {
    use PasswordValidationRules;

    public bool $showCreateModal = false;
    public bool $showDeleteModal = false;
    public ?int $deletingId = null;

    public string $name = '';
    public string $username = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    #[Computed]
    public function users()
    {
        return User::query()->orderBy('name')->get();
    }

    public function openCreate(): void
    {
        $this->reset('name', 'username', 'email', 'password', 'password_confirmation');
        $this->showCreateModal = true;
    }

    public function create(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => $this->passwordRules(),
        ]);

        User::query()->create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'email_verified_at' => now(),
        ]);

        $this->showCreateModal = false;
        unset($this->users);

        Flux::toast(variant: 'success', text: __('User created.'));
    }

    public function confirmDelete(int $id): void
    {
        if ($id === Auth::id()) {
            Flux::toast(variant: 'danger', text: __('You cannot delete your own account.'));

            return;
        }

        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        if (! $this->deletingId || $this->deletingId === Auth::id()) {
            return;
        }

        User::query()->findOrFail($this->deletingId)->delete();

        $this->showDeleteModal = false;
        $this->deletingId = null;
        unset($this->users);

        Flux::toast(variant: 'success', text: __('User deleted.'));
    }

    public function resetTwoFactor(int $id): void
    {
        if ($id === Auth::id()) {
            Flux::toast(variant: 'danger', text: __('Use Security settings to manage your own 2FA.'));

            return;
        }

        $user = User::query()->findOrFail($id);

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        unset($this->users);

        Flux::toast(variant: 'success', text: __('Two-factor authentication reset for :name', ['name' => $user->name]));
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <div class="flex items-start max-md:flex-col">
        <div class="me-10 w-full pb-4 md:w-[220px]">
            <flux:navlist aria-label="{{ __('Settings') }}">
                <flux:navlist.item :href="route('settings.general')" wire:navigate>{{ __('General') }}</flux:navlist.item>
                <flux:navlist.item :href="route('settings.smtp')" wire:navigate>{{ __('Mail') }}</flux:navlist.item>
                <flux:navlist.item :href="route('security.edit')" wire:navigate>{{ __('Security') }}</flux:navlist.item>
                <flux:navlist.item :href="route('settings.users')" :current="true" wire:navigate>{{ __('Users') }}</flux:navlist.item>
                <flux:navlist.item :href="route('settings.scheduler')" wire:navigate>{{ __('Scheduler') }}</flux:navlist.item>
            </flux:navlist>
        </div>

        <flux:separator class="md:hidden" />

        <div class="flex-1 self-stretch max-md:pt-6 w-full">
            <div class="mb-6 flex items-center justify-between gap-4">
                <div>
                    <flux:heading>{{ __('Users') }}</flux:heading>
                    <flux:subheading>{{ __('Manage admin accounts') }}</flux:subheading>
                </div>
                <flux:button variant="primary" icon="plus" wire:click="openCreate">{{ __('Add user') }}</flux:button>
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Username') }}</flux:table.column>
                    <flux:table.column>{{ __('Email') }}</flux:table.column>
                    <flux:table.column>{{ __('2FA') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->users as $user)
                        <flux:table.row :key="$user->id">
                            <flux:table.cell variant="strong">
                                {{ $user->name }}
                                @if ($user->id === auth()->id())
                                    <flux:badge size="sm" class="ms-2">{{ __('You') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $user->username }}</flux:table.cell>
                            <flux:table.cell>{{ $user->email }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($user->two_factor_confirmed_at)
                                    <flux:badge size="sm" color="green">{{ __('Enabled') }}</flux:badge>
                                @else
                                    <flux:badge size="sm">{{ __('Off') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <div class="flex justify-end gap-2">
                                    @if ($user->two_factor_confirmed_at && $user->id !== auth()->id())
                                        <flux:button size="sm" variant="ghost" wire:click="resetTwoFactor({{ $user->id }})">
                                            {{ __('Reset 2FA') }}
                                        </flux:button>
                                    @endif
                                    @if ($user->id !== auth()->id())
                                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="confirmDelete({{ $user->id }})" class="text-red-500" />
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    </div>

    <flux:modal wire:model="showCreateModal" class="max-w-lg">
        <form wire:submit="create" class="space-y-6">
            <flux:heading size="lg">{{ __('Add user') }}</flux:heading>
            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:input wire:model="username" :label="__('Username')" required />
            <flux:input wire:model="email" :label="__('Email')" type="email" required />
            <flux:input wire:model="password" :label="__('Password')" type="password" required viewable />
            <flux:input wire:model="password_confirmation" :label="__('Confirm password')" type="password" required viewable />
            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showCreateModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showDeleteModal" class="max-w-md">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Delete user') }}</flux:heading>
            <flux:text>{{ __('Are you sure you want to delete this user?') }}</flux:text>
            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="danger" wire:click="delete">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
