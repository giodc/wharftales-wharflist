<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use App\Services\SettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class UnsubscribeController extends Controller
{
    public function show(Request $request, SettingsService $settings): View|RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return view('pages.public.unsubscribe', [
                'status' => 'invalid',
                'siteName' => $settings->siteName(),
            ]);
        }

        $subscriber = Subscriber::query()->where('email', $request->string('email'))->first();

        if (! $subscriber) {
            return view('pages.public.unsubscribe', [
                'status' => 'invalid',
                'siteName' => $settings->siteName(),
            ]);
        }

        return view('pages.public.unsubscribe', [
            'status' => 'confirm',
            'siteName' => $settings->siteName(),
            'email' => $subscriber->email,
            'listId' => $request->integer('list_id') ?: null,
            'actionUrl' => URL::temporarySignedRoute(
                'unsubscribe.store',
                now()->addDay(),
                array_filter([
                    'email' => $subscriber->email,
                    'list_id' => $request->integer('list_id') ?: null,
                ]),
            ),
        ]);
    }

    public function store(Request $request, SettingsService $settings): View
    {
        if (! $request->hasValidSignature()) {
            return view('pages.public.unsubscribe', [
                'status' => 'invalid',
                'siteName' => $settings->siteName(),
            ]);
        }

        $subscriber = Subscriber::query()->where('email', $request->string('email'))->first();

        if (! $subscriber) {
            return view('pages.public.unsubscribe', [
                'status' => 'invalid',
                'siteName' => $settings->siteName(),
            ]);
        }

        $listId = $request->integer('list_id') ?: null;

        if ($listId) {
            $subscriber->lists()->updateExistingPivot($listId, [
                'unsubscribed' => true,
            ]);
        } else {
            $subscriber->forceFill(['unsubscribed' => true])->save();
        }

        return view('pages.public.unsubscribe', [
            'status' => 'success',
            'siteName' => $settings->siteName(),
            'listSpecific' => (bool) $listId,
        ]);
    }
}
