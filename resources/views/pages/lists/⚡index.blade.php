<?php

use App\Models\MailingList;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Lists')] class extends Component {
    public bool $showCreateModal = false;
    public bool $showDeleteModal = false;
    public ?int $deletingId = null;

    public string $name = '';
    public string $description = '';

    #[Computed]
    public function lists()
    {
        return MailingList::query()->orderBy('name')->get();
    }

    public function openCreate(): void
    {
        $this->reset('name', 'description');
        $this->showCreateModal = true;
    }

    public function create(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        MailingList::query()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'is_default' => false,
        ]);

        $this->showCreateModal = false;
        $this->reset('name', 'description');
        unset($this->lists);

        Flux::toast(variant: 'success', text: __('List created.'));
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        if (! $this->deletingId) {
            return;
        }

        $list = MailingList::query()->findOrFail($this->deletingId);

        if ($list->is_default) {
            Flux::toast(variant: 'danger', text: __('The default list cannot be deleted.'));

            return;
        }

        $list->delete();

        $this->showDeleteModal = false;
        $this->deletingId = null;
        unset($this->lists);

        Flux::toast(variant: 'success', text: __('List deleted.'));
    }
}; ?>

<div class="flex flex-col gap-6 p-6 lg:p-8">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Lists') }}</flux:heading>
            <flux:subheading>{{ __('Manage mailing lists and subscriber segments') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreate">
            {{ __('New list') }}
        </flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Description') }}</flux:table.column>
            <flux:table.column>{{ __('Verified') }}</flux:table.column>
            <flux:table.column>{{ __('Pending') }}</flux:table.column>
            <flux:table.column>{{ __('Unsubscribed') }}</flux:table.column>
            <flux:table.column>{{ __('Total') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->lists as $list)
                <flux:table.row :key="$list->id">
                    <flux:table.cell variant="strong">
                        {{ $list->name }}
                        @if ($list->is_default)
                            <flux:badge size="sm" class="ms-2">{{ __('Default') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $list->description ?: '—' }}</flux:table.cell>
                    <flux:table.cell>{{ number_format($list->verifiedCount()) }}</flux:table.cell>
                    <flux:table.cell>{{ number_format($list->pendingCount()) }}</flux:table.cell>
                    <flux:table.cell>{{ number_format($list->unsubscribedCount()) }}</flux:table.cell>
                    <flux:table.cell>{{ number_format($list->subscribers()->count()) }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <div class="flex justify-end gap-2">
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="users"
                                :href="route('subscribers.index', ['list_id' => $list->id])"
                                wire:navigate
                            />
                            @unless ($list->is_default)
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    wire:click="confirmDelete({{ $list->id }})"
                                    class="text-red-500"
                                />
                            @endunless
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model="showCreateModal" class="max-w-lg">
        <form wire:submit="create" class="space-y-6">
            <flux:heading size="lg">{{ __('Create list') }}</flux:heading>
            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:textarea wire:model="description" :label="__('Description')" rows="3" />
            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showCreateModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showDeleteModal" class="max-w-md">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Delete list') }}</flux:heading>
            <flux:text>{{ __('Are you sure you want to delete this list? This cannot be undone.') }}</flux:text>
            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="danger" wire:click="delete">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
