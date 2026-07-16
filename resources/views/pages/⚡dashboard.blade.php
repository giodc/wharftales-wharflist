<?php

use App\Models\MailingList;
use App\Models\Site;
use App\Models\Subscriber;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    #[Computed]
    public function verifiedCount(): int
    {
        return Subscriber::query()
            ->where('verified', true)
            ->where('unsubscribed', false)
            ->count();
    }

    #[Computed]
    public function pendingCount(): int
    {
        return Subscriber::query()
            ->where('verified', false)
            ->where('unsubscribed', false)
            ->count();
    }

    #[Computed]
    public function listsCount(): int
    {
        return MailingList::query()->count();
    }

    #[Computed]
    public function sitesCount(): int
    {
        return Site::query()->count();
    }

    #[Computed]
    public function latestSubscribers()
    {
        return Subscriber::query()
            ->with(['site', 'lists'])
            ->latest('subscribed_at')
            ->limit(10)
            ->get();
    }
}; ?>

<div class="flex flex-col gap-6 p-6 lg:p-8">
    <div>
        <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>
        <flux:subheading>{{ __('Overview of your mailing lists and subscribers') }}</flux:subheading>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text variant="subtle">{{ __('Verified') }}</flux:text>
            <flux:heading size="xl" class="mt-1">{{ number_format($this->verifiedCount) }}</flux:heading>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text variant="subtle">{{ __('Pending') }}</flux:text>
            <flux:heading size="xl" class="mt-1">{{ number_format($this->pendingCount) }}</flux:heading>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text variant="subtle">{{ __('Lists') }}</flux:text>
            <flux:heading size="xl" class="mt-1">{{ number_format($this->listsCount) }}</flux:heading>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text variant="subtle">{{ __('Sites') }}</flux:text>
            <flux:heading size="xl" class="mt-1">{{ number_format($this->sitesCount) }}</flux:heading>
        </div>
    </div>

    <div>
        <flux:heading size="lg" class="mb-4">{{ __('Latest subscribers') }}</flux:heading>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
                <flux:table.column>{{ __('Lists') }}</flux:table.column>
                <flux:table.column>{{ __('Site') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Subscribed') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->latestSubscribers as $subscriber)
                    <flux:table.row :key="$subscriber->id">
                        <flux:table.cell variant="strong">{{ $subscriber->email }}</flux:table.cell>
                        <flux:table.cell>{{ $subscriber->lists->pluck('name')->join(', ') ?: '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $subscriber->site?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="match($subscriber->statusLabel()) {
                                'verified' => 'green',
                                'pending' => 'yellow',
                                default => 'zinc',
                            }">
                                {{ ucfirst($subscriber->statusLabel()) }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $subscriber->subscribed_at?->format('M j, Y g:i A') ?? '—' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5">
                            <flux:text variant="subtle">{{ __('No subscribers yet.') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
