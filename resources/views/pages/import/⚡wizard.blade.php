<?php

use App\Models\Site;
use App\Models\Subscriber;
use App\Services\SubscribeService;
use Flux\Flux;
use League\Csv\Reader;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Title('Import subscribers')] class extends Component {
    use WithFileUploads;

    public int $step = 1;
    public $csvFile;
    public ?int $site_id = null;
    public bool $skip_verification = false;

    /** @var array<int, array<string, string>> */
    public array $previewRows = [];

    /** @var array<int, string> */
    public array $headers = [];

    public int $totalRows = 0;

    #[Computed]
    public function sites()
    {
        return Site::query()->with('list')->orderBy('name')->get();
    }

    public function downloadSample(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            echo "email,name,company\n";
            echo "user@example.com,Jane Doe,Acme Inc\n";
            echo "another@example.com,John Smith,\n";
        }, 'wharflist-import-sample.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function upload(): void
    {
        $this->validate([
            'csvFile' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'site_id' => ['required', 'exists:sites,id'],
        ]);

        $path = $this->csvFile->getRealPath();
        $reader = Reader::createFromPath($path);
        $reader->setHeaderOffset(0);

        $this->headers = $reader->getHeader();
        $records = iterator_to_array($reader->getRecords());

        if (! in_array('email', array_map('strtolower', $this->headers), true)) {
            $this->addError('csvFile', __('CSV must include an email column.'));

            return;
        }

        $this->totalRows = count($records);
        $this->previewRows = array_slice(array_map(fn ($row) => (array) $row, $records), 0, 10);
        $this->step = 2;
    }

    public function confirm(SubscribeService $subscribeService): void
    {
        if ($this->csvFile === null || ! $this->site_id) {
            return;
        }

        $site = Site::query()->findOrFail($this->site_id);
        $path = $this->csvFile->getRealPath();
        $reader = Reader::createFromPath($path);
        $reader->setHeaderOffset(0);

        $emailKey = collect($reader->getHeader())->first(fn ($h) => strtolower($h) === 'email');
        $imported = 0;
        $skipped = 0;

        foreach ($reader->getRecords() as $record) {
            $row = (array) $record;
            $email = strtolower(trim((string) ($row[$emailKey] ?? '')));

            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;

                continue;
            }

            $customData = collect($row)
                ->except([$emailKey])
                ->filter(fn ($v) => filled($v))
                ->all();

            $subscriber = Subscriber::query()->where('email', $email)->first();

            if ($subscriber) {
                $subscriber->forceFill([
                    'site_id' => $subscriber->site_id ?: $site->id,
                    'custom_data' => $customData ?: $subscriber->custom_data,
                ])->save();
            } else {
                $subscriber = Subscriber::query()->create([
                    'email' => $email,
                    'site_id' => $site->id,
                    'verified' => $this->skip_verification,
                    'verified_at' => $this->skip_verification ? now() : null,
                    'unsubscribed' => false,
                    'custom_data' => $customData ?: null,
                    'subscribed_at' => now(),
                ]);
            }

            if (! $subscriber->lists()->where('lists.id', $site->list_id)->exists()) {
                $subscriber->lists()->attach($site->list_id, [
                    'unsubscribed' => false,
                    'subscribed_at' => now(),
                ]);
            } else {
                $subscriber->lists()->updateExistingPivot($site->list_id, [
                    'unsubscribed' => false,
                    'subscribed_at' => now(),
                ]);
            }

            if ($this->skip_verification) {
                $subscriber->forceFill([
                    'verified' => true,
                    'verified_at' => $subscriber->verified_at ?: now(),
                ])->save();
            } elseif (! $subscriber->verified) {
                $subscribeService->sendVerification($subscriber);
            }

            $imported++;
        }

        $this->step = 3;

        Flux::toast(variant: 'success', text: __(':imported imported, :skipped skipped.', [
            'imported' => $imported,
            'skipped' => $skipped,
        ]));
    }

    public function resetWizard(): void
    {
        $this->reset('csvFile', 'previewRows', 'headers', 'totalRows', 'skip_verification');
        $this->step = 1;
    }
}; ?>

<div class="flex flex-col gap-6 p-6 lg:p-8">
    <div>
        <flux:heading size="xl">{{ __('Import subscribers') }}</flux:heading>
        <flux:subheading>{{ __('Upload a CSV file to add subscribers to a site list') }}</flux:subheading>
    </div>

    <div class="flex items-center gap-4 text-sm">
        <flux:badge :color="$step >= 1 ? 'green' : 'zinc'">1. {{ __('Upload') }}</flux:badge>
        <flux:badge :color="$step >= 2 ? 'green' : 'zinc'">2. {{ __('Preview') }}</flux:badge>
        <flux:badge :color="$step >= 3 ? 'green' : 'zinc'">3. {{ __('Done') }}</flux:badge>
    </div>

    @if ($step === 1)
        <div class="max-w-xl space-y-6">
            <flux:button variant="ghost" icon="arrow-down-tray" wire:click="downloadSample">
                {{ __('Download sample CSV') }}
            </flux:button>

            <form wire:submit="upload" class="space-y-4">
                <flux:select wire:model="site_id" :label="__('Target site')" required>
                    <flux:select.option value="">{{ __('Select a site') }}</flux:select.option>
                    @foreach ($this->sites as $site)
                        <flux:select.option :value="$site->id">{{ $site->name }} ({{ $site->list?->name }})</flux:select.option>
                    @endforeach
                </flux:select>

                <div>
                    <flux:label>{{ __('CSV file') }}</flux:label>
                    <input type="file" wire:model="csvFile" accept=".csv,text/csv" class="mt-2 block w-full text-sm" />
                    @error('csvFile') <flux:text class="mt-1 text-red-500">{{ $message }}</flux:text> @enderror
                </div>

                <flux:checkbox wire:model="skip_verification" :label="__('Skip verification (mark as verified)')" />

                <flux:button variant="primary" type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="upload,csvFile">{{ __('Upload and preview') }}</span>
                    <span wire:loading wire:target="upload,csvFile">{{ __('Processing…') }}</span>
                </flux:button>
            </form>
        </div>
    @elseif ($step === 2)
        <div class="space-y-6">
            <flux:text>{{ __(':total rows found. Showing first :shown.', ['total' => $totalRows, 'shown' => count($previewRows)]) }}</flux:text>

            <flux:table>
                <flux:table.columns>
                    @foreach ($headers as $header)
                        <flux:table.column>{{ $header }}</flux:table.column>
                    @endforeach
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($previewRows as $index => $row)
                        <flux:table.row :key="$index">
                            @foreach ($headers as $header)
                                <flux:table.cell>{{ $row[$header] ?? '' }}</flux:table.cell>
                            @endforeach
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="flex gap-3">
                <flux:button variant="ghost" wire:click="$set('step', 1)">{{ __('Back') }}</flux:button>
                <flux:button variant="primary" wire:click="confirm" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="confirm">{{ __('Confirm import') }}</span>
                    <span wire:loading wire:target="confirm">{{ __('Importing…') }}</span>
                </flux:button>
            </div>
        </div>
    @else
        <div class="max-w-xl space-y-4">
            <flux:heading size="lg">{{ __('Import complete') }}</flux:heading>
            <flux:text>{{ __('Your subscribers have been imported.') }}</flux:text>
            <div class="flex gap-3">
                <flux:button variant="primary" :href="route('subscribers.index')" wire:navigate>{{ __('View subscribers') }}</flux:button>
                <flux:button variant="ghost" wire:click="resetWizard">{{ __('Import another file') }}</flux:button>
            </div>
        </div>
    @endif
</div>
