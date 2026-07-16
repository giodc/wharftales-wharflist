<?php

use App\Jobs\SendCampaignJob;
use App\Models\CampaignSend;
use App\Models\EmailCampaign;
use App\Models\MailingList;
use App\Services\CampaignAudience;
use App\Services\SettingsService;
use Carbon\Carbon;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Compose campaign')] class extends Component {
    public ?int $campaignId = null;

    /** @var array<int> */
    public array $listIds = [];

    public string $subject = '';
    public string $body = '';
    public string $scheduled_at_local = '';

    public bool $showPreviewModal = false;
    public bool $showScheduleModal = false;

    public function mount(?EmailCampaign $campaign = null): void
    {
        if ($campaign) {
            $this->campaignId = $campaign->id;
            $this->subject = $campaign->subject;
            $this->body = $campaign->body;
            $this->listIds = $campaign->listIds();
        }
    }

    #[Computed]
    public function mailingLists()
    {
        return MailingList::query()->orderBy('name')->get();
    }

    #[Computed]
    public function recentCampaigns()
    {
        return EmailCampaign::query()
            ->latest()
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function audienceCount(): int
    {
        return app(CampaignAudience::class)->count($this->listIds);
    }

    protected function validateCampaign(bool $requireAudience = false): array
    {
        $rules = [
            'listIds' => ['required', 'array', 'min:1'],
            'listIds.*' => ['integer', 'exists:lists,id'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ];

        $validated = $this->validate($rules);

        if ($requireAudience && $this->audienceCount === 0) {
            $this->addError('listIds', __('At least one verified subscriber is required to send or schedule.'));

            throw \Illuminate\Validation\ValidationException::withMessages([
                'listIds' => __('At least one verified subscriber is required to send or schedule.'),
            ]);
        }

        return $validated;
    }

    protected function saveCampaign(string $status = 'draft'): EmailCampaign
    {
        $validated = $this->validateCampaign();

        if ($this->campaignId) {
            $campaign = EmailCampaign::query()->findOrFail($this->campaignId);
            $campaign->update([
                'subject' => $validated['subject'],
                'body' => $validated['body'],
                'status' => $status,
                'list_id' => $validated['listIds'][0],
            ]);
        } else {
            $campaign = EmailCampaign::query()->create([
                'subject' => $validated['subject'],
                'body' => $validated['body'],
                'status' => $status,
                'list_id' => $validated['listIds'][0],
            ]);
            $this->campaignId = $campaign->id;
        }

        $campaign->lists()->sync($validated['listIds']);

        unset($this->recentCampaigns);

        return $campaign;
    }

    public function saveDraft(): void
    {
        $this->saveCampaign('draft');

        Flux::toast(variant: 'success', text: __('Draft saved.'));
    }

    public function sendNow(): void
    {
        $this->validateCampaign(requireAudience: true);

        $campaign = $this->saveCampaign('sent');

        $send = CampaignSend::query()->create([
            'campaign_id' => $campaign->id,
            'list_ids' => $this->listIds,
            'status' => 'pending',
        ]);

        SendCampaignJob::dispatch($send->id);

        Flux::toast(variant: 'success', text: __('Campaign queued for sending.'));

        $this->redirect(route('campaigns.status', $campaign), navigate: true);
    }

    public function openSchedule(): void
    {
        $this->validateCampaign(requireAudience: true);
        $this->showScheduleModal = true;
    }

    public function schedule(SettingsService $settings): void
    {
        $this->validateCampaign(requireAudience: true);

        $this->validate([
            'scheduled_at_local' => ['required', 'date'],
        ]);

        $local = Carbon::parse($this->scheduled_at_local, $settings->timezone());

        if ($local->isPast()) {
            $this->addError('scheduled_at_local', __('Scheduled time must be in the future.'));

            return;
        }

        $campaign = $this->saveCampaign('scheduled');

        $send = CampaignSend::query()->create([
            'campaign_id' => $campaign->id,
            'list_ids' => $this->listIds,
            'status' => 'scheduled',
            'scheduled_at' => $local->utc(),
        ]);

        $this->showScheduleModal = false;

        Flux::toast(variant: 'success', text: __('Campaign scheduled.'));

        $this->redirect(route('campaigns.status', $campaign), navigate: true);
    }

    public function preview(): void
    {
        $this->validate([
            'subject' => ['required', 'string'],
            'body' => ['required', 'string'],
        ]);

        $this->showPreviewModal = true;
    }
}; ?>

<div class="flex flex-col gap-6 p-6 lg:p-8">
    <div class="flex flex-col gap-6 lg:flex-row">
        <div class="flex-1 space-y-6">
            <div>
                <flux:heading size="xl">{{ __('Compose campaign') }}</flux:heading>
                <flux:subheading>{{ __('Write and send email campaigns to your lists') }}</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:heading size="sm">{{ __('Lists') }}</flux:heading>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($this->mailingLists as $list)
                        <flux:checkbox wire:model="listIds" :value="$list->id" :label="$list->name" />
                    @endforeach
                </div>
                @error('listIds') <flux:text class="text-red-500">{{ $message }}</flux:text> @enderror
                <flux:text variant="subtle">{{ __('Audience: :count verified subscribers', ['count' => $this->audienceCount]) }}</flux:text>
            </div>

            <flux:input wire:model="subject" :label="__('Subject')" required />

            <div>
                <flux:label>{{ __('Body') }}</flux:label>
                <div wire:ignore id="quill-editor" class="mt-2 min-h-64 rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900"></div>
                @error('body') <flux:text class="mt-1 text-red-500">{{ $message }}</flux:text> @enderror
            </div>

            <div class="flex flex-wrap gap-3">
                <flux:button variant="ghost" wire:click="saveDraft">{{ __('Save draft') }}</flux:button>
                <flux:button variant="ghost" wire:click="preview">{{ __('Preview') }}</flux:button>
                <flux:button variant="primary" wire:click="sendNow">{{ __('Send now') }}</flux:button>
                <flux:button variant="filled" wire:click="openSchedule">{{ __('Schedule') }}</flux:button>
            </div>
        </div>

        <aside class="w-full shrink-0 lg:w-72">
            <flux:heading size="sm" class="mb-4">{{ __('Recent campaigns') }}</flux:heading>
            <div class="space-y-2">
                @foreach ($this->recentCampaigns as $recent)
                    <a
                        href="{{ $recent->status === 'draft' ? route('campaigns.compose', $recent) : route('campaigns.status', $recent) }}"
                        wire:navigate
                        class="block rounded-lg border border-zinc-200 p-3 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900"
                    >
                        <div class="font-medium truncate">{{ $recent->subject }}</div>
                        <flux:badge size="sm" class="mt-1">{{ ucfirst($recent->status) }}</flux:badge>
                    </a>
                @endforeach
            </div>
        </aside>
    </div>

    <flux:modal wire:model="showPreviewModal" class="max-w-3xl">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Preview') }}</flux:heading>
            <flux:subheading>{{ $subject }}</flux:subheading>
            <div class="prose max-w-none dark:prose-invert rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                {!! \App\Support\Html::sanitizeCampaign($body) !!}
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model="showScheduleModal" class="max-w-md">
        <form wire:submit="schedule" class="space-y-6">
            <flux:heading size="lg">{{ __('Schedule campaign') }}</flux:heading>
            <flux:input wire:model="scheduled_at_local" :label="__('Send at')" type="datetime-local" required />
            <flux:text variant="subtle">{{ __('Times use your configured app timezone.') }}</flux:text>
            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showScheduleModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Schedule') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>

@assets
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
@endassets

@script
<script>
    const editorEl = document.getElementById('quill-editor');
    let quill = null;

    const initQuill = () => {
        if (!editorEl || quill) return;

        quill = new Quill(editorEl, {
            theme: 'snow',
            modules: {
                toolbar: {
                    container: [
                        [{ header: [1, 2, 3, false] }],
                        ['bold', 'italic', 'underline', 'link'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        ['image'],
                        ['clean'],
                    ],
                    handlers: {
                        image: imageHandler,
                    },
                },
            },
        });

        const initial = $wire.get('body');
        if (initial) {
            quill.root.innerHTML = initial;
        }

        quill.on('text-change', () => {
            $wire.set('body', quill.root.innerHTML);
        });
    };

    const imageHandler = () => {
        const input = document.createElement('input');
        input.setAttribute('type', 'file');
        input.setAttribute('accept', 'image/*');
        input.click();

        input.onchange = async () => {
            const file = input.files?.[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('image', file);

            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            const response = await fetch(@js(route('uploads.images')), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: formData,
            });

            if (!response.ok) return;

            const data = await response.json();
            const range = quill.getSelection(true);
            quill.insertEmbed(range.index, 'image', data.url);
            quill.setSelection(range.index + 1);
        };
    };

    initQuill();

    $wire.on('body-updated', (value) => {
        if (quill && value !== quill.root.innerHTML) {
            quill.root.innerHTML = value;
        }
    });
</script>
@endscript
