<?php

namespace App\Jobs;

use App\Models\CampaignSend;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessScheduledCampaigns implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        CampaignSend::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->each(function (CampaignSend $send): void {
                $send->forceFill(['status' => 'pending'])->save();
                SendCampaignJob::dispatch($send->id);
            });
    }
}
