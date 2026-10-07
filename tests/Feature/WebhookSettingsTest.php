<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WebhookSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.telegram.token' => 'test-token', 'services.telegram.webhook_secret' => 'shh-secret']);
        URL::forceRootUrl('https://bot.example.com');
        URL::forceScheme('https');

        Http::fake([
            'api.telegram.org/*/getMe' => Http::response(['ok' => true, 'result' => ['username' => 'rsg_leads_bot', 'first_name' => 'RSG Leads']]),
            'api.telegram.org/*/getWebhookInfo' => Http::response(['ok' => true, 'result' => [
                'url' => 'https://bot.example.com/api/telegram/webhook', 'pending_update_count' => 3, 'max_connections' => 40, 'ip_address' => '203.0.113.9',
            ]]),
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);
    }

    public function test_guests_cannot_use_the_webhook_endpoints(): void
    {
        $this->postJson(route('admin.settings.webhook.set'))->assertUnauthorized();
        $this->postJson(route('admin.settings.webhook.info'))->assertUnauthorized();
        $this->deleteJson(route('admin.settings.webhook.remove'))->assertUnauthorized();
    }

    public function test_settings_page_has_the_buttons_and_the_modal(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSeeText('Set webhook')
            ->assertSeeText('Webhook info')
            ->assertSeeText('Remove webhook')
            ->assertSee('id="webhook-modal"', false)
            ->assertSee('name="csrf-token"', false);
    }

    public function test_setting_the_webhook_registers_the_public_url_with_the_secret(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.settings.webhook.set'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('title', 'Webhook set')
            ->assertJsonFragment(['label' => 'Pending updates', 'value' => '3']);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'setWebhook')
            && $request['url'] === 'https://bot.example.com/api/telegram/webhook'
            && $request['secret_token'] === 'shh-secret');
    }

    public function test_setting_the_webhook_needs_a_secret(): void
    {
        config(['services.telegram.webhook_secret' => null]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.settings.webhook.set'))
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'The webhook secret is missing.');

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'setWebhook'));
    }

    public function test_every_action_needs_a_bot_token(): void
    {
        config(['services.telegram.token' => null]);
        $admin = User::factory()->create();

        $this->actingAs($admin)->postJson(route('admin.settings.webhook.set'))->assertJsonPath('title', 'Bot token missing');
        $this->actingAs($admin)->postJson(route('admin.settings.webhook.info'))->assertJsonPath('ok', false);
        $this->actingAs($admin)->deleteJson(route('admin.settings.webhook.remove'))->assertJsonPath('ok', false);

        Http::assertNothingSent();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unreachableRoots(): array
    {
        return [
            'plain http' => ['http://bot.example.com'],
            'no dot in host' => ['https://rsg-dev'],
            'localhost' => ['https://localhost'],
            '.test domain' => ['https://rsg-dev.test'],
            '.local domain' => ['https://panel.local'],
            'private ip' => ['https://192.168.1.37'],
            'loopback ip' => ['https://127.0.0.1'],
        ];
    }

    #[DataProvider('unreachableRoots')]
    public function test_local_and_non_https_addresses_are_explained_instead_of_sent(string $root): void
    {
        URL::forceRootUrl($root);
        URL::forceScheme(parse_url($root, PHP_URL_SCHEME));

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.settings.webhook.set'))
            ->assertJsonPath('ok', false)
            ->assertJsonPath('title', 'Webhook not set')
            ->assertJsonPath('message', 'Telegram only delivers updates to a public HTTPS address, and this one is not.')
            ->assertJsonFragment(['hint' => "This app is reachable at {$root}/api/telegram/webhook. For local development run `php artisan telegram:poll` instead. In production, open the panel over your public HTTPS domain (check APP_URL) and press this button again."]);

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'setWebhook'));
    }

    public function test_telegram_errors_are_shown_in_the_result(): void
    {
        Http::swap(new Factory);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Bad Request: bad webhook: certificate verify failed'], 400)]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.settings.webhook.set'))
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'Telegram setWebhook failed: Bad Request: bad webhook: certificate verify failed');
    }

    public function test_info_reports_the_bot_and_the_webhook_state(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.settings.webhook.info'))
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'This bot receives updates through a webhook.')
            ->assertJsonFragment(['label' => 'Bot', 'value' => '@rsg_leads_bot (RSG Leads)'])
            ->assertJsonFragment(['label' => 'Server IP', 'value' => '203.0.113.9']);
    }

    public function test_info_explains_that_no_webhook_means_polling(): void
    {
        Http::swap(new Factory);
        Http::fake([
            'api.telegram.org/*/getMe' => Http::response(['ok' => true, 'result' => ['username' => 'rsg_leads_bot', 'first_name' => 'RSG']]),
            'api.telegram.org/*/getWebhookInfo' => Http::response(['ok' => true, 'result' => ['url' => '', 'pending_update_count' => 0]]),
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.settings.webhook.info'))
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'No webhook is set, so the bot only works while `php artisan telegram:poll` is running.')
            ->assertJsonFragment(['label' => 'Webhook URL', 'value' => 'Not set']);
    }

    public function test_removing_the_webhook_calls_telegram(): void
    {
        $this->actingAs(User::factory()->create())
            ->deleteJson(route('admin.settings.webhook.remove'))
            ->assertJsonPath('ok', true)
            ->assertJsonPath('title', 'Webhook removed');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'deleteWebhook'));
    }
}
