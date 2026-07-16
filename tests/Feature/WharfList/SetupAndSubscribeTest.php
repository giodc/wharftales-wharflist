<?php

namespace Tests\Feature\WharfList;

use App\Models\MailingList;
use App\Models\Site;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SetupAndSubscribeTest extends TestCase
{
    use RefreshDatabase;

    protected bool $installApp = false;

    public function test_setup_wizard_installs_the_app(): void
    {
        $this->get(route('setup'))->assertOk();

        Livewire::test('pages::setup.wizard')
            ->set('name', 'Admin User')
            ->set('username', 'admin')
            ->set('email', 'admin@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('site_name', 'WharfList Test')
            ->set('site_url', 'http://localhost:8000')
            ->set('timezone', 'UTC')
            ->call('install')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $this->assertTrue(app(SettingsService::class)->isInstalled());
        $this->assertDatabaseHas('users', ['username' => 'admin', 'email' => 'admin@example.com']);
        $this->assertDatabaseHas('lists', ['name' => 'Default', 'is_default' => true]);
    }

    public function test_subscribe_api_creates_pending_subscriber(): void
    {
        $this->markAppInstalled();

        $list = MailingList::query()->where('is_default', true)->firstOrFail();

        $site = Site::query()->create([
            'name' => 'Main Site',
            'domain' => 'example.com',
            'list_id' => $list->id,
        ]);

        $response = $this->postJson('/api/subscribe', [
            'api_key' => $site->api_key,
            'email' => 'reader@example.com',
            '_hp' => '',
            '_t' => (int) round(microtime(true) * 1000) - 2000,
        ], [
            'Referer' => 'https://example.com/newsletter',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Please check your email to verify your subscription',
            ]);

        $this->assertDatabaseHas('subscribers', [
            'email' => 'reader@example.com',
            'verified' => false,
            'site_id' => $site->id,
        ]);

        $subscriber = Subscriber::query()->where('email', 'reader@example.com')->firstOrFail();
        $this->assertTrue($subscriber->lists()->where('lists.id', $list->id)->exists());

        $again = $this->postJson('/api/subscribe', [
            'api_key' => $site->api_key,
            'email' => 'reader@example.com',
            '_hp' => '',
            '_t' => (int) round(microtime(true) * 1000) - 2000,
        ], [
            'Referer' => 'https://example.com/newsletter',
        ]);

        $again->assertOk()
            ->assertJson([
                'success' => false,
                'message' => 'Pending verification. Please check your email.',
            ]);
    }

    public function test_login_accepts_username(): void
    {
        $this->markAppInstalled();

        $user = User::factory()->create([
            'username' => 'captain',
            'email' => 'captain@example.com',
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'captain',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }
}
