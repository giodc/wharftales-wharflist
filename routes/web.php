<?php

use App\Http\Controllers\Api\SubscribeController;
use App\Http\Controllers\ImageUploadController;
use App\Http\Controllers\UnsubscribeController;
use App\Http\Controllers\VerifyController;
use App\Http\Controllers\WidgetController;
use Illuminate\Support\Facades\Route;

Route::livewire('/setup', 'pages::setup.wizard')->name('setup');

Route::get('/widget.js', WidgetController::class)->name('widget');
Route::get('/verify/{token}', VerifyController::class)->name('verify');
Route::get('/verify.php', function () {
    $token = request('token');

    abort_if(blank($token), 404);

    return redirect()->route('verify', ['token' => $token]);
});

Route::get('/unsubscribe', [UnsubscribeController::class, 'show'])->name('unsubscribe.show');
Route::post('/unsubscribe', [UnsubscribeController::class, 'store'])->name('unsubscribe.store');

Route::post('/api/subscribe', [SubscribeController::class, 'store'])->name('api.subscribe');
Route::options('/api/subscribe', [SubscribeController::class, 'options']);
Route::post('/api.php', [SubscribeController::class, 'store'])->name('api.php');
Route::options('/api.php', [SubscribeController::class, 'options']);

Route::get('/widget-demo', function () {
    $site = \App\Models\Site::query()->orderBy('id')->first();

    abort_if(! $site, 404, 'Create a site first, then open the widget demo.');

    return view('pages.public.widget-demo', ['site' => $site]);
})->name('widget.demo');

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::livewire('/dashboard', 'pages::dashboard')->name('dashboard');

    Route::livewire('/lists', 'pages::lists.index')->name('lists.index');
    Route::livewire('/sites', 'pages::sites.index')->name('sites.index');
    Route::livewire('/subscribers', 'pages::subscribers.index')->name('subscribers.index');
    Route::livewire('/import', 'pages::import.wizard')->name('import.wizard');

    Route::livewire('/campaigns', 'pages::campaigns.index')->name('campaigns.index');
    Route::livewire('/campaigns/compose/{campaign?}', 'pages::campaigns.compose')->name('campaigns.compose');
    Route::livewire('/campaigns/{campaign}/status', 'pages::campaigns.status')->name('campaigns.status');

    Route::post('/uploads/images', ImageUploadController::class)->name('uploads.images');

    Route::livewire('/settings/general', 'pages::settings.general')->name('settings.general');
    Route::livewire('/settings/smtp', 'pages::settings.smtp')->name('settings.smtp');
    Route::livewire('/settings/users', 'pages::settings.users')->name('settings.users');
    Route::livewire('/settings/scheduler', 'pages::settings.scheduler')->name('settings.scheduler');
});

require __DIR__.'/settings.php';
