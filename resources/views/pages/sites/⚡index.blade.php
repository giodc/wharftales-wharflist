<?php

use App\Models\MailingList;
use App\Models\Site;
use App\Services\SettingsService;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Sites')] class extends Component {
    public bool $showCreateModal = false;
    public bool $showEmbedModal = false;
    public bool $showDeleteModal = false;

    public ?int $embedSiteId = null;
    public ?int $deletingId = null;

    public string $name = '';
    public string $domain = '';
    public ?int $list_id = null;
    public string $custom_fields = '';

    #[Computed]
    public function sites()
    {
        return Site::query()->with('list')->orderBy('name')->get();
    }

    #[Computed]
    public function mailingLists()
    {
        return MailingList::query()->orderBy('name')->get();
    }

    #[Computed]
    public function embedSite(): ?Site
    {
        return $this->embedSiteId
            ? Site::query()->find($this->embedSiteId)
            : null;
    }

    public function openCreate(): void
    {
        $this->reset('name', 'domain', 'custom_fields');
        $this->list_id = MailingList::query()->where('is_default', true)->value('id')
            ?? MailingList::query()->value('id');
        $this->showCreateModal = true;
    }

    public function create(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'max:255'],
            'list_id' => ['required', 'exists:lists,id'],
            'custom_fields' => ['nullable', 'string', 'max:1000'],
        ]);

        Site::query()->create([
            'name' => $validated['name'],
            'domain' => $validated['domain'],
            'list_id' => $validated['list_id'],
            'custom_fields' => $validated['custom_fields'] ?: null,
        ]);

        $this->showCreateModal = false;
        unset($this->sites);

        Flux::toast(variant: 'success', text: __('Site created.'));
    }

    public function showEmbed(int $id): void
    {
        $this->embedSiteId = $id;
        $this->showEmbedModal = true;
    }

    public function embedCode(SettingsService $settings): string
    {
        $site = $this->embedSite;

        if (! $site) {
            return '';
        }

        $baseUrl = $settings->siteUrl();

        return <<<HTML
<div id="wharflist-form"></div>
<script>
(function() {
    var script = document.createElement('script');
    script.src = '{$baseUrl}/widget.js';
    script.setAttribute('data-api-key', '{$site->api_key}');
    script.setAttribute('data-site-id', '{$site->id}');
    document.head.appendChild(script);
})();
</script>
HTML;
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

        Site::query()->findOrFail($this->deletingId)->delete();

        $this->showDeleteModal = false;
        $this->deletingId = null;
        unset($this->sites);

        Flux::toast(variant: 'success', text: __('Site deleted.'));
    }
}; ?>

<div class="flex flex-col gap-6 p-6 lg:p-8">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Sites') }}</flux:heading>
            <flux:subheading>{{ __('Embed the signup widget on external websites') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreate">
            {{ __('New site') }}
        </flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Domain') }}</flux:table.column>
            <flux:table.column>{{ __('List') }}</flux:table.column>
            <flux:table.column>{{ __('API key') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->sites as $site)
                <flux:table.row :key="$site->id">
                    <flux:table.cell variant="strong">{{ $site->name }}</flux:table.cell>
                    <flux:table.cell>{{ $site->domain }}</flux:table.cell>
                    <flux:table.cell>{{ $site->list?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <code class="text-xs">{{ $site->maskedApiKey() }}</code>
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <div class="flex justify-end gap-2">
                            <flux:button size="sm" variant="ghost" icon="code-bracket" wire:click="showEmbed({{ $site->id }})" />
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="confirmDelete({{ $site->id }})" class="text-red-500" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model="showCreateModal" class="max-w-lg">
        <form wire:submit="create" class="space-y-6">
            <flux:heading size="lg">{{ __('Create site') }}</flux:heading>
            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:input wire:model="domain" :label="__('Domain')" required placeholder="example.com" />
            <flux:select wire:model="list_id" :label="__('Target list')" required>
                @foreach ($this->mailingLists as $list)
                    <flux:select.option :value="$list->id">{{ $list->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:textarea wire:model="custom_fields" :label="__('Custom fields (comma-separated)')" rows="2" />
            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showCreateModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showEmbedModal" class="max-w-2xl">
        <div class="space-y-6" x-data="{ copied: false }">
            <flux:heading size="lg">{{ __('Embed code') }}</flux:heading>
            <flux:subheading>{{ __('Paste this snippet before the closing </body> tag on :domain', ['domain' => $this->embedSite?->domain ?? '']) }}</flux:subheading>
            <pre x-ref="embedCode" class="overflow-x-auto rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-xs dark:border-zinc-700 dark:bg-zinc-900"><code>{{ $this->embedCode(app(\App\Services\SettingsService::class)) }}</code></pre>
            <div class="flex justify-end gap-3">
                <flux:button
                    variant="primary"
                    icon="clipboard-document"
                    x-on:click="navigator.clipboard.writeText($refs.embedCode.textContent.trim()); copied = true; setTimeout(() => copied = false, 2000)"
                >
                    <span x-show="!copied">{{ __('Copy to clipboard') }}</span>
                    <span x-show="copied" x-cloak>{{ __('Copied!') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model="showDeleteModal" class="max-w-md">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Delete site') }}</flux:heading>
            <flux:text>{{ __('Are you sure you want to delete this site? The embed code will stop working.') }}</flux:text>
            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="danger" wire:click="delete">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
