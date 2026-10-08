<?php

namespace Tests\Feature;

use App\Console\Commands\TelegramPoll;
use App\Models\Setting;
use App\Models\TelegramUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramPollCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.telegram.token' => 'test-token', 'services.telegram.admin_chat_id' => null]);
    }

    public function test_the_last_handled_update_is_remembered_and_polling_resumes_after_it(): void
    {
        Http::fake([
            'api.telegram.org/*/getUpdates' => Http::sequence()
                ->push(['ok' => true, 'result' => [$this->update(10), $this->update(11)]])
                ->push(['ok' => true, 'result' => []]),
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $this->artisan('telegram:poll', ['--once' => true])->assertSuccessful();

        $this->assertSame(11, Setting::read(TelegramPoll::LAST_UPDATE_SETTING));
        $this->assertSame(1, TelegramUser::count());

        // A restart (for example after changing code) must not fetch the already handled updates again.
        $this->artisan('telegram:poll', ['--once' => true])->assertSuccessful();

        $offsets = Http::recorded(fn (Request $request) => str_contains($request->url(), 'getUpdates'))
            ->map(fn (array $pair) => $pair[0]['offset'])
            ->values()
            ->all();

        $this->assertSame([0, 12], $offsets);
    }

    public function test_a_failing_handler_does_not_stop_the_poller_from_remembering_progress(): void
    {
        Http::fake([
            'api.telegram.org/*/getUpdates' => Http::response(['ok' => true, 'result' => [$this->update(20)]]),
            'api.telegram.org/*/sendMessage' => Http::response(['ok' => false, 'description' => 'chat not found'], 400),
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $this->artisan('telegram:poll', ['--once' => true])->assertSuccessful();

        $this->assertSame(20, Setting::read(TelegramPoll::LAST_UPDATE_SETTING));
    }

    /**
     * @return array<string, mixed>
     */
    private function update(int $id): array
    {
        return ['update_id' => $id, 'message' => [
            'text' => '/start',
            'chat' => ['id' => 4242, 'type' => 'private'],
            'from' => ['id' => 4242, 'first_name' => 'Test'],
        ]];
    }
}
