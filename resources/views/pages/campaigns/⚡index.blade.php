<?php

use App\Models\CampaignSend;
use App\Models\EmailCampaign;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Campaigns')] class extends Component {
    public bool $showCancelModal = false;
    public ?int $cancellingSendId = null;

    #[Computed]
    public function campaigns()
    {
        return EmailCampaign::query()
            ->with(['lists', 'latestSend'])
            ->latest()
            ->get();
    }

    public function confirmCancel(int $sendId): void
    {
        $this->cancellingSendId = $sendId;
        $this->showCancelModal = true;
    }

    public function cancel(): void
    {
        if (! $this->cancellingSendId) {
            return;
        }

        $send = CampaignSend::query()->with('campaign')->findOrFail($this->cancellingSendId);

        if (! $send->isCancellable()) {
            Flux::toast(variant: 'danger', text: __('This campaign cannot be cancelled.'));

            return;
        }

        $send->forceFill([
            'status' => 'cancelled',
            'completed_at' => now(),
        ])->save();

        $send->campaign?->forceFill(['status' => 'draft'])->save();

        $this->showCancelModal = false;
        $this->cancellingSendId = null;
        unset($this->campaigns);

        Flux::toast(variant: 'success', text: __('Campaign cancelled.'));
    }

    public function sendStatusLabel(EmailCampaign $campaign): string
    {
        $send = $campaign->latestSend;

        if ($send) {
            return $send->status;
        }

        return $campaign->status;
    }

    public function sendStatusColor(EmailCampaign $campaign): string
    {
        return match ($this->sendStatusLabel($campaign)) {
            'completed', 'sent' => 'green',
            'processing', 'pending' => 'yellow',
            'scheduled' => 'blue',
            'failed' => 'red',
            'cancelled' => 'zinc',
            default => 'zinc',
        };
    }
}; ?>

<div class="flex flex-col gap-6 p-6 lg:p-8">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Campaigns') }}</flux:heading>
            <flux:subheading>{{ __('View sent, scheduled, and draft campaigns') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="pencil-square" :href="route('campaigns.compose')" wire:navigate>
            {{ __('Compose') }}
        </flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Subject') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Lists') }}</flux:table.column>
            <flux:table.column>{{ __('Schedule / progress') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->campaigns as $campaign)
                @php $send = $campaign->latestSend; @endphp
                <flux:table.row :key="$campaign->id">
                    <flux:table.cell variant="strong">{{ $campaign->subject }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$this->sendStatusColor($campaign)">
                            {{ ucfirst($this->sendStatusLabel($campaign)) }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $campaign->lists->pluck('name')->join(', ') ?: '—' }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($send?->status === 'scheduled' && $send->scheduled_at)
                            {{ $send->scheduled_at->timezone(app(\App\Services\SettingsService::class)->timezone())->format('M j, Y g:i A T') }}
                        @elseif ($send && in_array($send->status, ['processing', 'pending', 'completed']))
                            {{ $send->progress }} / {{ $send->total }}
                        @elseif ($campaign->sent_at)
                            {{ $campaign->sent_at->format('M j, Y g:i A') }}
                        @else
                            —
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <div class="flex justify-end gap-2">
                            @if ($campaign->status === 'draft')
                                <flux:button size="sm" variant="ghost" icon="pencil" :href="route('campaigns.compose', $campaign)" wire:navigate />
                            @endif
                            @if ($send && in_array($send->status, ['pending', 'processing', 'scheduled', 'completed', 'failed', 'cancelled']))
                                <flux:button size="sm" variant="ghost" icon="chart-bar" :href="route('campaigns.status', $campaign)" wire:navigate />
                            @endif
                            @if ($send?->isCancellable())
                                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="confirmCancel({{ $send->id }})" class="text-red-500" />
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">
                        <flux:text variant="subtle">{{ __('No campaigns yet.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model="showCancelModal" class="max-w-md">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Cancel campaign') }}</flux:heading>
            <flux:text>{{ __('Stop sending this campaign? Progress will be saved and the campaign will return to draft.') }}</flux:text>
            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showCancelModal', false)">{{ __('Keep sending') }}</flux:button>
                <flux:button variant="danger" wire:click="cancel">{{ __('Cancel campaign') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
