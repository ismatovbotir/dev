<?php

namespace Tests\Feature;

use App\Models\TelegramUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.telegram.token' => 'test-token', 'services.telegram.webhook_secret' => 'shh']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);
    }

    public function test_request_without_secret_is_forbidden(): void
    {
        $this->postJson(route('telegram.webhook'), $this->update())->assertForbidden();
        $this->assertSame(0, TelegramUser::count());
    }

    public function test_request_with_wrong_secret_is_forbidden(): void
    {
        $this->postJson(route('telegram.webhook'), $this->update(), ['X-Telegram-Bot-Api-Secret-Token' => 'wrong'])
            ->assertForbidden();
    }

    public function test_valid_update_is_processed_without_csrf_token(): void
    {
        $this->postJson(route('telegram.webhook'), $this->update(), ['X-Telegram-Bot-Api-Secret-Token' => 'shh'])
            ->assertOk();

        $this->assertSame(1, TelegramUser::count());
    }

    /**
     * @return array<string, mixed>
     */
    private function update(): array
    {
        return ['update_id' => 1, 'message' => [
            'text' => '/start',
            'chat' => ['id' => 42, 'type' => 'private'],
            'from' => ['id' => 42, 'first_name' => 'Test'],
        ]];
    }
}
