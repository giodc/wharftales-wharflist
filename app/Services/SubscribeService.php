<?php

namespace App\Services;

use App\Mail\VerificationMail;
use App\Models\RateLimit;
use App\Models\Site;
use App\Models\Subscriber;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SubscribeService
{
    public function __construct(
        protected SettingsService $settings,
        protected DynamicMailConfig $mailConfig,
    ) {}

    /**
     * @param  array{api_key?: string, email?: string, custom_data?: array<string, mixed>, _hp?: string, _t?: int|string|null}  $payload
     * @return array{success: bool, message: string, data: array<string, mixed>, http?: int}
     */
    public function subscribe(array $payload, ?string $ip, ?string $referer): array
    {
        $honeypot = (string) ($payload['_hp'] ?? '');
        $timing = isset($payload['_t']) ? (int) $payload['_t'] : null;

        if ($honeypot !== '') {
            return $this->ok('Please check your email to verify your subscription');
        }

        if ($timing !== null && $timing > 0 && (((int) round(microtime(true) * 1000)) - $timing) < 1000) {
            return $this->ok('Please check your email to verify your subscription');
        }

        $apiKey = (string) ($payload['api_key'] ?? '');
        $email = Str::lower(trim((string) ($payload['email'] ?? '')));

        if ($apiKey === '' || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->fail('Invalid request', 422);
        }

        $site = Site::query()->where('api_key', $apiKey)->first();

        if (! $site) {
            return $this->fail('Invalid API key', 401);
        }

        if (! $site->matchesRefererDomain($referer)) {
            return $this->fail('Request origin not allowed', 403);
        }

        if (! $this->checkRateLimit($ip ?: '0.0.0.0', $site->id)) {
            return $this->fail('Too many requests. Please try again later.', 429);
        }

        $customData = is_array($payload['custom_data'] ?? null) ? $payload['custom_data'] : [];
        $subscriber = Subscriber::query()->where('email', $email)->first();

        if ($subscriber) {
            if ($subscriber->unsubscribed) {
                $subscriber->forceFill([
                    'unsubscribed' => false,
                    'site_id' => $subscriber->site_id ?: $site->id,
                    'custom_data' => $customData ?: $subscriber->custom_data,
                ])->save();
            }

            $pivot = $subscriber->lists()->where('lists.id', $site->list_id)->first();

            if ($pivot) {
                $wasListUnsubscribed = (bool) $pivot->pivot->unsubscribed;

                if ($wasListUnsubscribed) {
                    $subscriber->lists()->updateExistingPivot($site->list_id, [
                        'unsubscribed' => false,
                        'subscribed_at' => now(),
                    ]);

                    return $this->ok(
                        $subscriber->verified
                            ? 'Successfully subscribed'
                            : 'Please check your email to verify your subscription'
                    );
                }

                if ($subscriber->verified) {
                    return $this->fail('You are already subscribed', 200);
                }

                $this->sendVerification($subscriber);

                return $this->fail('Pending verification. Please check your email.', 200);
            }

            $subscriber->lists()->attach($site->list_id, [
                'unsubscribed' => false,
                'subscribed_at' => now(),
            ]);

            if ($subscriber->verified) {
                return $this->ok('Successfully subscribed');
            }

            $this->sendVerification($subscriber);

            return $this->ok('Please check your email to verify your subscription');
        }

        $subscriber = Subscriber::query()->create([
            'email' => $email,
            'site_id' => $site->id,
            'verified' => false,
            'unsubscribed' => false,
            'custom_data' => $customData,
            'subscribed_at' => now(),
        ]);

        $subscriber->lists()->attach($site->list_id, [
            'unsubscribed' => false,
            'subscribed_at' => now(),
        ]);

        $this->sendVerification($subscriber);

        return $this->ok('Please check your email to verify your subscription');
    }

    public function sendVerification(Subscriber $subscriber): bool
    {
        try {
            if (blank($subscriber->verification_token)) {
                $subscriber->regenerateVerificationToken();
            }

            $this->mailConfig->apply();

            Mail::to($subscriber->email)->send(new VerificationMail($subscriber));

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function checkRateLimit(string $ip, int $siteId): bool
    {
        $record = RateLimit::query()->firstOrCreate(
            ['ip_address' => $ip, 'site_id' => $siteId],
            ['attempts' => 0, 'last_attempt' => now()],
        );

        if ($record->last_attempt && $record->last_attempt->lt(now()->subHour())) {
            $record->forceFill([
                'attempts' => 0,
                'last_attempt' => now(),
            ])->save();
        }

        if ($record->attempts >= 5) {
            return false;
        }

        $record->forceFill([
            'attempts' => $record->attempts + 1,
            'last_attempt' => now(),
        ])->save();

        return true;
    }

    /**
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    protected function ok(string $message): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => [],
        ];
    }

    /**
     * @return array{success: bool, message: string, data: array<string, mixed>, http: int}
     */
    protected function fail(string $message, int $http = 400): array
    {
        return [
            'success' => false,
            'message' => $message,
            'data' => [],
            'http' => $http,
        ];
    }
}
