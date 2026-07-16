<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Scheduler')] class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Scheduler')" :subheading="__('Run background jobs and scheduled campaigns')">
        <div class="prose prose-sm max-w-none dark:prose-invert space-y-6">
            <p>{{ __('WharfList uses the Laravel scheduler to process scheduled campaigns and queue jobs. Choose one of the options below for your environment.') }}</p>

            <div>
                <flux:heading size="sm">{{ __('Local development') }}</flux:heading>
                <p>{{ __('Run the scheduler in a dedicated terminal alongside your app and queue worker:') }}</p>
                <pre class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-sm dark:border-zinc-700 dark:bg-zinc-900"><code>php artisan schedule:work</code></pre>
                <p class="text-sm text-zinc-500">{{ __('Also run a queue worker:') }} <code>php artisan queue:work</code></p>
            </div>

            <div>
                <flux:heading size="sm">{{ __('Production (cron)') }}</flux:heading>
                <p>{{ __('Add this cron entry on your server (runs every minute):') }}</p>
                <pre class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-sm dark:border-zinc-700 dark:bg-zinc-900"><code>* * * * * cd {{ base_path() }} && php artisan schedule:run >> /dev/null 2>&1</code></pre>
            </div>

            <flux:text variant="subtle">
                {{ __('Scheduled campaigns are picked up by the scheduler and sent via the queue. Ensure QUEUE_CONNECTION is configured and a queue worker is running.') }}
            </flux:text>
        </div>
    </x-pages::settings.layout>
</section>
