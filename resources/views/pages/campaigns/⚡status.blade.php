<?php

use App\Models\CampaignSend;
use App\Models\EmailCampaign;
use App\Services\SettingsService;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Campaign status')] class extends Component {
    public EmailCampaign $campaign;

    public function mount(EmailCampaign $campaign): void
    {
        $this->campaign = $campaign->load(['lists', 'latestSend']);
    }

    #[Computed]
    public function send(): ?CampaignSend
    {
        return $this->campaign->latestSend;
    }

    public function shouldPoll(): bool
    {
        $send = $this->send;

        return $send && in_array($send->status, ['pending', 'processing', 'scheduled'], true);
    }

    public function refreshCampaign(): void
    {
        $this->campaign->refresh()->load(['lists', 'latestSend']);
        unset($this->send);
    }

    public function cancel(): void
    {
        $send = $this->send;

        if (! $send || ! $send->isCancellable()) {
            Flux::toast(variant: 'danger', text: __('This campaign cannot be cancelled.'));

            return;
        }

        $send->forceFill([
            'status' => 'cancelled',
            'completed_at' => now(),
        ])->save();

        $this->campaign->forceFill(['status' => 'draft'])->save();
        $this->refreshCampaign();

        Flux::toast(variant: 'success', text: __('Campaign cancelled.'));
    }
}; ?>

<div
    class="flex flex-col gap-6 p-6 lg:p-8"
    @if ($this->shouldPoll()) wire:poll.2s="refreshCampaign" @endif
>
    <div>
        <flux:heading size="xl">{{ $campaign->subject }}</flux:heading>
        <flux:subheading>{{ __('Campaign send status') }}</flux:subheading>
    </div>

    @if ($send = $this->send)
        <div class="max-w-2xl space-y-6">
            <div class="flex items-center gap-3">
                <flux:badge size="sm" :color="match($send->status) {
                    'completed' => 'green',
                    'processing', 'pending' => 'yellow',
                    'scheduled' => 'blue',
                    'failed' => 'red',
                    'cancelled' => 'zinc',
                    default => 'zinc',
                }">
                    {{ ucfirst($send->status) }}
                </flux:badge>
                <flux:text variant="subtle">{{ $campaign->lists->pluck('name')->join(', ') }}</flux:text>
            </div>

            @if ($send->total > 0)
                <div>
                    <div class="mb-2 flex justify-between text-sm">
                        <flux:text>{{ __('Progress') }}</flux:text>
                        <flux:text>{{ $send->progress }} / {{ $send->total }} ({{ $send->percentComplete() }}%)</flux:text>
                    </div>
                    <div class="h-3 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                        <div
                            class="h-full rounded-full bg-green-500 transition-all duration-300"
                            style="width: {{ $send->percentComplete() }}%"
                        ></div>
                    </div>
                </div>
            @endif

            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                @if ($send->scheduled_at)
                    <div>
                        <dt class="font-medium">{{ __('Scheduled at') }}</dt>
                        <dd class="text-zinc-500">{{ $send->scheduled_at->timezone(app(\App\Services\SettingsService::class)->timezone())->format('M j, Y g:i A T') }}</dd>
                    </div>
                @endif
                @if ($send->started_at)
                    <div>
                        <dt class="font-medium">{{ __('Started at') }}</dt>
                        <dd class="text-zinc-500">{{ $send->started_at->format('M j, Y g:i A') }}</dd>
                    </div>
                @endif
                @if ($send->completed_at)
                    <div>
                        <dt class="font-medium">{{ __('Completed at') }}</dt>
                        <dd class="text-zinc-500">{{ $send->completed_at->format('M j, Y g:i A') }}</dd>
                    </div>
                @endif
            </dl>

            @if ($send->error)
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
                    <strong>{{ __('Error') }}:</strong> {{ $send->error }}
                </div>
            @endif

            <div class="flex gap-3">
                @if ($send->isCancellable())
                    <flux:button variant="danger" wire:click="cancel">{{ __('Cancel') }}</flux:button>
                @endif
                <flux:button variant="ghost" :href="route('campaigns.index')" wire:navigate>{{ __('All campaigns') }}</flux:button>
                @if ($campaign->status === 'draft')
                    <flux:button variant="primary" :href="route('campaigns.compose', $campaign)" wire:navigate>{{ __('Edit draft') }}</flux:button>
                @endif
            </div>
        </div>
    @else
        <flux:text variant="subtle">{{ __('No send record found for this campaign.') }}</flux:text>
    @endif
</div>
