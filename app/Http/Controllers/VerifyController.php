<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use App\Services\SettingsService;
use Illuminate\Contracts\View\View;

class VerifyController extends Controller
{
    public function __invoke(string $token, SettingsService $settings): View
    {
        $subscriber = Subscriber::query()->where('verification_token', $token)->first();

        if (! $subscriber) {
            return view('pages.public.verify', [
                'status' => 'invalid',
                'siteName' => $settings->siteName(),
            ]);
        }

        if ($subscriber->verified) {
            return view('pages.public.verify', [
                'status' => 'already',
                'siteName' => $settings->siteName(),
            ]);
        }

        $subscriber->forceFill([
            'verified' => true,
            'verified_at' => now(),
        ])->save();

        return view('pages.public.verify', [
            'status' => 'success',
            'siteName' => $settings->siteName(),
        ]);
    }
}
