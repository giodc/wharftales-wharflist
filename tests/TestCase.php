<?php

namespace Tests;

use App\Models\MailingList;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected bool $installApp = true;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->installApp) {
            $this->markAppInstalled();
        }
    }

    protected function markAppInstalled(): void
    {
        $settings = app(SettingsService::class);

        $settings->setMany([
            'site_name' => 'WharfList',
            'site_url' => config('app.url'),
            'timezone' => 'UTC',
            'emails_per_batch' => 50,
            'delay_between_emails' => 0,
        ]);

        if (! MailingList::query()->where('is_default', true)->exists()) {
            MailingList::query()->create([
                'name' => 'Default',
                'description' => 'Default mailing list',
                'is_default' => true,
            ]);
        }

        $settings->markInstalled();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
