<?php

use App\Models\MailingList;
use App\Models\Subscriber;
use App\Services\SubscribeService;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Subscribers')] class extends Component {
    use WithPagination;

    #[Url]
    public ?int $list_id = null;

    #[Url]
    public string $status = 'all';

    /** @var array<int> */
    public array $selected = [];

    public bool $selectAllOnPage = false;
    public bool $showAddToListsModal = false;

    /** @var array<int> */
    public array $bulkListIds = [];

    #[Computed]
    public function mailingLists()
    {
        return MailingList::query()->orderBy('name')->get();
    }

    #[Computed]
    public function subscribers()
    {
        $query = Subscriber::query()->with(['site', 'lists']);

        if ($this->list_id) {
            $query->whereHas('lists', fn ($q) => $q->where('lists.id', $this->list_id));
        }

        match ($this->status) {
            'verified' => $query->where('verified', true)->where('unsubscribed', false),
            'pending' => $query->where('verified', false)->where('unsubscribed', false),
            'unsubscribed' => $query->where(function ($q) {
                $q->where('unsubscribed', true)
                    ->orWhereHas('lists', fn ($l) => $l->where('subscriber_lists.unsubscribed', true));
            }),
            default => null,
        };

        return $query->latest('subscribed_at')->paginate(25);
    }

    public function updatedListId(): void
    {
        $this->resetPage();
        $this->clearSelection();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
        $this->clearSelection();
    }

    public function updatedSelectAllOnPage(bool $value): void
    {
        if ($value) {
            $this->selected = $this->subscribers->getCollection()->pluck('id')->map(fn ($id) => (int) $id)->all();
        } else {
            $this->selected = [];
        }
    }

    public function clearSelection(): void
    {
        $this->selected = [];
        $this->selectAllOnPage = false;
    }

    public function bulkVerify(): void
    {
        if ($this->selected === []) {
            return;
        }

        Subscriber::query()
            ->whereIn('id', $this->selected)
            ->update([
                'verified' => true,
                'verified_at' => now(),
            ]);

        $this->clearSelection();
        unset($this->subscribers);

        Flux::toast(variant: 'success', text: __('Subscribers verified.'));
    }

    public function bulkResend(SubscribeService $subscribeService): void
    {
        if ($this->selected === []) {
            return;
        }

        $count = 0;

        Subscriber::query()
            ->whereIn('id', $this->selected)
            ->where('verified', false)
            ->each(function (Subscriber $subscriber) use ($subscribeService, &$count): void {
                if ($subscribeService->sendVerification($subscriber)) {
                    $count++;
                }
            });

        $this->clearSelection();

        Flux::toast(variant: 'success', text: __(':count verification email(s) sent.', ['count' => $count]));
    }

    public function openAddToLists(): void
    {
        if ($this->selected === []) {
            return;
        }

        $this->bulkListIds = [];
        $this->showAddToListsModal = true;
    }

    public function bulkAddToLists(): void
    {
        $this->validate([
            'bulkListIds' => ['required', 'array', 'min:1'],
            'bulkListIds.*' => ['integer', 'exists:lists,id'],
        ]);

        Subscriber::query()
            ->whereIn('id', $this->selected)
            ->each(function (Subscriber $subscriber): void {
                foreach ($this->bulkListIds as $listId) {
                    if (! $subscriber->lists()->where('lists.id', $listId)->exists()) {
                        $subscriber->lists()->attach($listId, [
                            'unsubscribed' => false,
                            'subscribed_at' => now(),
                        ]);
                    } else {
                        $subscriber->lists()->updateExistingPivot($listId, [
                            'unsubscribed' => false,
                            'subscribed_at' => now(),
                        ]);
                    }
                }
            });

        $this->showAddToListsModal = false;
        $this->clearSelection();
        unset($this->subscribers);

        Flux::toast(variant: 'success', text: __('Subscribers added to lists.'));
    }

    public function bulkDelete(): void
    {
        if ($this->selected === []) {
            return;
        }

        Subscriber::query()->whereIn('id', $this->selected)->delete();

        $this->clearSelection();
        unset($this->subscribers);

        Flux::toast(variant: 'success', text: __('Subscribers deleted.'));
    }

    public function verify(int $id): void
    {
        Subscriber::query()->whereKey($id)->update([
            'verified' => true,
            'verified_at' => now(),
        ]);

        unset($this->subscribers);

        Flux::toast(variant: 'success', text: __('Subscriber verified.'));
    }

    public function resend(int $id, SubscribeService $subscribeService): void
    {
        $subscriber = Subscriber::query()->findOrFail($id);

        if ($subscribeService->sendVerification($subscriber)) {
            Flux::toast(variant: 'success', text: __('Verification email sent.'));
        } else {
            Flux::toast(variant: 'danger', text: __('Failed to send verification email.'));
        }
    }

    public function delete(int $id): void
    {
        Subscriber::query()->whereKey($id)->delete();
        unset($this->subscribers);

        Flux::toast(variant: 'success', text: __('Subscriber deleted.'));
    }
}; ?>

<div class="flex flex-col gap-6 p-6 lg:p-8">
    <div>
        <flux:heading size="xl">{{ __('Subscribers') }}</flux:heading>
        <flux:subheading>{{ __('Manage and filter your subscriber base') }}</flux:subheading>
    </div>

    <div class="flex flex-wrap items-end gap-4">
        <div class="w-48">
            <flux:select wire:model.live="list_id" :label="__('List')">
                <flux:select.option value="">{{ __('All lists') }}</flux:select.option>
                @foreach ($this->mailingLists as $list)
                    <flux:select.option :value="$list->id">{{ $list->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <div class="w-48">
            <flux:select wire:model.live="status" :label="__('Status')">
                <flux:select.option value="all">{{ __('All') }}</flux:select.option>
                <flux:select.option value="verified">{{ __('Verified') }}</flux:select.option>
                <flux:select.option value="pending">{{ __('Pending') }}</flux:select.option>
                <flux:select.option value="unsubscribed">{{ __('Unsubscribed') }}</flux:select.option>
            </flux:select>
        </div>
    </div>

    @if (count($selected) > 0)
        <div class="flex flex-wrap items-center gap-3 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text>{{ __(':count selected', ['count' => count($selected)]) }}</flux:text>
            <flux:button size="sm" wire:click="bulkVerify">{{ __('Verify') }}</flux:button>
            <flux:button size="sm" wire:click="bulkResend">{{ __('Resend verification') }}</flux:button>
            <flux:button size="sm" wire:click="openAddToLists">{{ __('Add to lists') }}</flux:button>
            <flux:button size="sm" variant="danger" wire:click="bulkDelete">{{ __('Delete') }}</flux:button>
        </div>
    @endif

    <flux:table :paginate="$this->subscribers">
        <flux:table.columns>
            <flux:table.column class="w-10">
                <flux:checkbox wire:model.live="selectAllOnPage" />
            </flux:table.column>
            <flux:table.column>{{ __('Email') }}</flux:table.column>
            <flux:table.column>{{ __('Lists') }}</flux:table.column>
            <flux:table.column>{{ __('Site') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Subscribed') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->subscribers as $subscriber)
                <flux:table.row :key="$subscriber->id">
                    <flux:table.cell>
                        <flux:checkbox wire:model.live="selected" :value="$subscriber->id" />
                    </flux:table.cell>
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
                    <flux:table.cell>{{ $subscriber->subscribed_at?->format('M j, Y') ?? '—' }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <div class="flex justify-end gap-1">
                            @unless ($subscriber->verified)
                                <flux:button size="sm" variant="ghost" icon="check" wire:click="verify({{ $subscriber->id }})" />
                                <flux:button size="sm" variant="ghost" icon="envelope" wire:click="resend({{ $subscriber->id }})" />
                            @endunless
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $subscriber->id }})" class="text-red-500" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model="showAddToListsModal" class="max-w-lg">
        <form wire:submit="bulkAddToLists" class="space-y-6">
            <flux:heading size="lg">{{ __('Add to lists') }}</flux:heading>
            <div class="space-y-2">
                @foreach ($this->mailingLists as $list)
                    <flux:checkbox wire:model="bulkListIds" :value="$list->id" :label="$list->name" />
                @endforeach
            </div>
            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showAddToListsModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Add') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
