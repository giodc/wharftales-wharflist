<?php

namespace App\Jobs;

use App\Mail\CampaignMail;
use App\Models\CampaignSend;
use App\Models\EmailCampaign;
use App\Models\Subscriber;
use App\Services\CampaignAudience;
use App\Services\DynamicMailConfig;
use App\Services\SettingsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class SendCampaignJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $campaignSendId) {}

    public function handle(
        CampaignAudience $audience,
        SettingsService $settings,
        DynamicMailConfig $mailConfig,
    ): void {
        $send = CampaignSend::query()->with('campaign.lists')->find($this->campaignSendId);

        if (! $send || ! $send->campaign) {
            return;
        }

        if ($send->status === 'cancelled') {
            return;
        }

        if ($send->status === 'scheduled' && $send->scheduled_at && $send->scheduled_at->isFuture()) {
            return;
        }

        $campaign = $send->campaign;
        $listIds = $send->list_ids ?: $campaign->listIds();
        $recipients = $audience->forLists($listIds);

        $send->forceFill([
            'status' => 'processing',
            'started_at' => $send->started_at ?: now(),
            'total' => $recipients->count(),
            'progress' => 0,
            'error' => null,
        ])->save();

        $campaign->forceFill(['status' => 'sent'])->save();

        $mailConfig->apply();

        $batchSize = max(1, (int) $settings->get('emails_per_batch', 50));
        $delayMicros = max(0, (int) $settings->get('delay_between_emails', 0)) * 1_000_000;
        $sent = 0;

        try {
            foreach ($recipients->chunk($batchSize) as $chunk) {
                $send->refresh();

                if ($send->status === 'cancelled') {
                    $campaign->forceFill(['status' => 'draft', 'sent_count' => $sent])->save();

                    return;
                }

                /** @var Subscriber $subscriber */
                foreach ($chunk as $subscriber) {
                    $send->refresh();

                    if ($send->status === 'cancelled') {
                        $campaign->forceFill(['status' => 'draft', 'sent_count' => $sent])->save();

                        return;
                    }

                    $unsubscribeUrl = URL::temporarySignedRoute(
                        'unsubscribe.show',
                        now()->addYear(),
                        [
                            'email' => $subscriber->email,
                            'list_id' => $listIds[0] ?? null,
                        ],
                    );

                    Mail::to($subscriber->email)->send(
                        new CampaignMail($campaign, $subscriber, $unsubscribeUrl)
                    );

                    $sent++;
                    $send->forceFill([
                        'progress' => $sent,
                    ])->save();

                    $campaign->forceFill([
                        'sent_count' => $sent,
                    ])->save();

                    if ($delayMicros > 0) {
                        usleep($delayMicros);
                    }
                }
            }

            $send->forceFill([
                'status' => 'completed',
                'progress' => $sent,
                'completed_at' => now(),
            ])->save();

            $campaign->forceFill([
                'status' => 'sent',
                'sent_count' => $sent,
                'sent_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            $send->forceFill([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'completed_at' => now(),
            ])->save();

            throw $e;
        }
    }
}
