<?php

namespace Tests\Feature;

use App\Enums\ConversationState;
use App\Enums\Language;
use App\Enums\RequestStatus;
use App\Models\RegistrationStep;
use App\Models\Setting;
use App\Models\ShopRequest;
use App\Models\TelegramUser;
use App\Services\Telegram\BotHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BotConversationTest extends TestCase
{
    use RefreshDatabase;

    private const CHAT_ID = 555001;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['services.telegram.token' => 'test-token', 'services.telegram.admin_chat_id' => null]);
        $this->fakeTelegram();
    }

    public function test_new_client_is_asked_for_a_language_first(): void
    {
        $this->send('/start');

        $this->assertSame(ConversationState::AwaitingLanguage, $this->client()->state);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'sendMessage')
            && count($request['reply_markup']['inline_keyboard']) === 3);
    }

    public function test_start_always_begins_with_the_language_choice_even_for_returning_clients(): void
    {
        $this->client(ConversationState::Idle, null, Language::Ru);

        $this->send('/start');

        $this->assertSame(ConversationState::AwaitingLanguage, $this->client()->state);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'sendMessage')
            && count($request['reply_markup']['inline_keyboard'] ?? []) === 3);
        Http::assertNotSent(fn (Request $request) => str_contains((string) ($request['text'] ?? ''), 'Добро пожаловать'));
    }

    public function test_any_first_message_from_an_unknown_client_shows_the_language_choice(): void
    {
        $this->send('hello');

        $this->assertSame(ConversationState::AwaitingLanguage, $this->client()->state);
        Http::assertSent(fn (Request $request) => count($request['reply_markup']['inline_keyboard'] ?? []) === 3);
    }

    public function test_after_choosing_a_language_the_bot_greets_and_asks_for_the_name(): void
    {
        $this->send('/start');
        $this->tap('lang:ru');

        $client = $this->client();
        $this->assertSame(Language::Ru, $client->language);
        $this->assertSame(ConversationState::AwaitingStep, $client->state);
        $this->assertSame('name', $client->draft['_step']);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'sendMessage')
            && str_contains($request['text'], 'Добро пожаловать'));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'sendMessage')
            && str_contains($request['text'], 'Шаг 1 из 6')
            && str_contains($request['text'], 'Ваше имя и фамилия'));
    }

    public function test_full_conversation_collects_everything_and_saves_the_plan(): void
    {
        $this->send('/start');
        $this->tap('lang:en');

        $this->send('Botir Ismatov');
        $this->send('+998 90 123-45-67');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'sendMessage')
            && ($request['reply_markup']['keyboard'][0][0]['text'] ?? null) === 'Small shop'
            && str_contains($request['text'], 'retail point planning'));

        $this->send('Supermarket');
        $this->send('Korzinka');
        $this->handleUpdate(['message' => $this->message(['location' => ['latitude' => 41.31, 'longitude' => 69.24]])]);
        $this->handleUpdate(['message' => $this->message(['photo' => [['file_id' => 'small'], ['file_id' => 'big']]])]);

        $this->assertSame(ConversationState::AwaitingConfirmation, $this->client()->state);
        $this->assertSame(0, ShopRequest::count());
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'sendMessage')
            && str_contains($request['text'], '+998 90 123 45 67')
            && str_contains($request['text'], 'Supermarket')
            && str_contains($request['text'], 'File received'));

        $this->tap('confirm:yes');

        $shopRequest = ShopRequest::sole();
        $this->assertSame('Botir Ismatov', $shopRequest->name);
        $this->assertSame('+998901234567', $shopRequest->phone);
        $this->assertSame('Korzinka', $shopRequest->brand);
        $this->assertEqualsWithDelta(41.31, $shopRequest->latitude, 0.0001);
        $this->assertSame(RequestStatus::New, $shopRequest->status);
        $this->assertSame(ConversationState::Idle, $this->client()->state);

        $this->assertSame('Supermarket', $shopRequest->answers[0]['value']);
        $this->assertSame("plans/{$shopRequest->id}/plan.jpg", $shopRequest->answers[1]['file']);
        Storage::disk('local')->assertExists("plans/{$shopRequest->id}/plan.jpg");

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'getFile') && $request['file_id'] === 'big');
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'sendMessage')
            && str_contains($request['text'], 'we will send it to you right here'));
    }

    public function test_point_type_buttons_use_the_clients_language_and_store_english(): void
    {
        $this->client(ConversationState::AwaitingStep, ['name' => 'Ali', '_step' => 'phone'], Language::Uz);

        $this->send('+998901234567');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'sendMessage')
            && ($request['reply_markup']['keyboard'][0][0]['text'] ?? null) === "Kichik do'kon");

        $this->send('Ombor');
        $this->send('Makro');
        $this->send('⏭ O‘tkazib yuborish');
        $this->handleUpdate(['message' => $this->message(['document' => [
            'file_id' => 'doc1', 'file_name' => 'plan.pdf', 'mime_type' => 'application/pdf', 'file_size' => 1000,
        ]])]);
        $this->tap('confirm:yes');

        $answers = ShopRequest::sole()->answers;
        $this->assertSame('Warehouse', $answers[0]['value']);
        $this->assertSame('plan.pdf', $answers[1]['value']);
        $this->assertNull(ShopRequest::sole()->location_text);
    }

    public function test_point_type_rejects_text_that_is_not_a_button(): void
    {
        $this->client(ConversationState::AwaitingStep, ['name' => 'Ali', 'phone' => '+998901234567', '_step' => 'point_type']);

        $this->send('spaceship');

        $this->assertSame('point_type', $this->client()->draft['_step']);
        $this->assertArrayNotHasKey('point_type', $this->client()->draft);
    }

    public function test_location_is_optional_and_can_be_skipped(): void
    {
        $this->client(ConversationState::AwaitingStep, [...$this->fullDraft(), '_step' => 'location']);

        $this->send('⏭ Skip');

        $this->assertSame('plan', $this->client()->draft['_step']);
    }

    public function test_the_plan_is_required_and_must_be_a_photo_or_pdf(): void
    {
        $this->client(ConversationState::AwaitingStep, [...Arr::except($this->fullDraft(), ['plan']), '_step' => 'plan']);

        $this->send('no plan, sorry');
        $this->handleUpdate(['message' => $this->message(['document' => [
            'file_id' => 'z', 'file_name' => 'x.zip', 'mime_type' => 'application/zip', 'file_size' => 10,
        ]])]);

        $this->assertSame(ConversationState::AwaitingStep, $this->client()->state);
        $this->assertArrayNotHasKey('plan', $this->client()->draft);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'sendMessage')
            && str_contains($request['text'], 'photo of the plan'));
    }

    public function test_request_is_still_saved_when_the_plan_download_fails(): void
    {
        Http::swap(new HttpFactory);
        Http::fake([
            'api.telegram.org/*/getFile' => Http::response(['ok' => false, 'description' => 'file is too big'], 400),
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []]),
        ]);
        $this->client(ConversationState::AwaitingConfirmation, $this->fullDraft());

        $this->tap('confirm:yes');

        $answer = ShopRequest::sole()->answers[1];
        $this->assertNull($answer['file']);
        $this->assertSame('F1', $answer['file_id']);
    }

    public function test_double_tapping_confirm_creates_only_one_request(): void
    {
        $this->client(ConversationState::AwaitingConfirmation, $this->fullDraft());

        $this->tap('confirm:yes');
        $this->tap('confirm:yes');

        $this->assertSame(1, ShopRequest::count());
    }

    public function test_start_over_clears_the_draft_and_asks_the_first_step_again(): void
    {
        $this->client(ConversationState::AwaitingConfirmation, $this->fullDraft());

        $this->tap('confirm:no');

        $client = $this->client();
        $this->assertSame(ConversationState::AwaitingStep, $client->state);
        $this->assertSame(['_step' => 'name'], $client->draft);
        $this->assertSame(0, ShopRequest::count());
    }

    public function test_back_button_returns_to_the_previous_step(): void
    {
        $this->client(ConversationState::AwaitingStep, [...$this->fullDraft(), '_step' => 'brand']);

        $this->send('⬅️ Back');
        $this->assertSame('point_type', $this->client()->draft['_step']);

        $this->send('⬅️ Back');
        $this->assertSame('phone', $this->client()->draft['_step']);

        $this->send('⬅️ Back');
        $this->assertSame('name', $this->client()->draft['_step']);
    }

    public function test_invalid_phone_is_rejected_and_the_step_is_kept(): void
    {
        $this->client(ConversationState::AwaitingStep, ['name' => 'Ali', '_step' => 'phone']);

        $this->send('abc');

        $this->assertSame('phone', $this->client()->draft['_step']);
        $this->assertArrayNotHasKey('phone', $this->client()->draft);
    }

    public function test_cancel_resets_the_draft(): void
    {
        $this->client(ConversationState::AwaitingStep, ['name' => 'Ali', '_step' => 'phone']);

        $this->send('/cancel');

        $client = $this->client();
        $this->assertSame(ConversationState::Idle, $client->state);
        $this->assertNull($client->draft);
    }

    public function test_group_chats_are_ignored(): void
    {
        $this->handleUpdate(['message' => ['text' => '/start', 'chat' => ['id' => -100, 'type' => 'group'], 'from' => []]]);

        $this->assertSame(0, TelegramUser::count());
    }

    public function test_user_input_is_html_escaped_in_the_summary(): void
    {
        $this->client(ConversationState::AwaitingConfirmation, [...$this->fullDraft(), 'name' => '<b>Evil</b>']);

        $this->send('anything');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'sendMessage')
            && str_contains($request['text'], '&lt;b&gt;Evil&lt;/b&gt;'));
    }

    public function test_added_steps_extend_the_progress_header(): void
    {
        RegistrationStep::factory()->create(['position' => 10]);
        $this->client(ConversationState::Idle);

        $this->send('/new');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'sendMessage')
            && str_contains($request['text'], 'Step 1 of 7'));
    }

    public function test_disabled_steps_are_not_asked(): void
    {
        RegistrationStep::whereIn('key', ['brand', 'location'])->update(['is_active' => false]);
        $this->client(ConversationState::AwaitingStep, [...$this->fullDraft(), '_step' => 'point_type']);

        $this->send('Hypermarket');

        $this->assertSame('plan', $this->client()->draft['_step']);
    }

    public function test_finishing_a_request_offers_the_new_request_button(): void
    {
        $this->client(ConversationState::AwaitingConfirmation, $this->fullDraft());

        $this->tap('confirm:yes');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'sendMessage')
            && str_contains($request['text'], 'we will send it to you right here')
            && ($request['reply_markup']['keyboard'][0][0]['text'] ?? null) === '➕ New request');
    }

    public function test_new_request_button_skips_language_name_and_phone_for_a_returning_client(): void
    {
        $client = $this->client(ConversationState::Idle);
        ShopRequest::factory()->for($client)->create(['name' => 'Ali Valiyev', 'phone' => '+998901234567']);

        $this->send('➕ New request');

        $client->refresh();
        $this->assertSame(ConversationState::AwaitingStep, $client->state);
        $this->assertSame('point_type', $client->draft['_step']);
        $this->assertSame('Ali Valiyev', $client->draft['name']);
        $this->assertSame(['name', 'phone'], $client->draft['_skip']);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'sendMessage')
            && str_contains($request['text'], 'Welcome back, <b>Ali Valiyev</b>'));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'sendMessage')
            && str_contains($request['text'], 'Step 1 of 4')
            && str_contains($request['text'], 'Type of your point'));
        Http::assertNotSent(fn (Request $request) => isset($request['reply_markup']['inline_keyboard']));
        Http::assertNotSent(fn (Request $request) => str_contains((string) ($request['text'] ?? ''), 'Your name and surname'));
    }

    public function test_second_request_reuses_the_name_and_phone(): void
    {
        $client = $this->client(ConversationState::Idle);
        ShopRequest::factory()->for($client)->create(['name' => 'Ali Valiyev', 'phone' => '+998901234567']);

        $this->send('/new');
        $this->send('Hypermarket');
        $this->send('Evos Mart');
        $this->send('⏭ Skip');
        $this->handleUpdate(['message' => $this->message(['photo' => [['file_id' => 'p1']]])]);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'sendMessage')
            && str_contains($request['text'], 'Ali Valiyev')
            && str_contains($request['text'], '+998 90 123 45 67'));

        $this->tap('confirm:yes');

        $this->assertSame(2, ShopRequest::count());
        $latest = ShopRequest::latest('id')->first();
        $this->assertSame('Ali Valiyev', $latest->name);
        $this->assertSame('+998901234567', $latest->phone);
        $this->assertSame('Evos Mart', $latest->brand);
    }

    public function test_new_request_button_works_in_the_clients_own_language(): void
    {
        $client = $this->client(ConversationState::Idle, null, Language::Ru);
        ShopRequest::factory()->for($client)->create();

        $this->send('➕ Новая заявка');

        $this->assertSame('point_type', $this->client()->draft['_step']);
    }

    public function test_start_over_keeps_skipping_known_answers(): void
    {
        $client = $this->client(ConversationState::AwaitingConfirmation, [...$this->fullDraft(), '_skip' => ['name', 'phone']]);
        ShopRequest::factory()->for($client)->create(['name' => 'Ali', 'phone' => '+998901234567']);

        $this->tap('confirm:no');

        $draft = $this->client()->draft;
        $this->assertSame('point_type', $draft['_step']);
        $this->assertSame(['name', 'phone'], $draft['_skip']);
    }

    public function test_client_without_a_previous_request_still_gets_the_full_flow(): void
    {
        $this->client(ConversationState::Idle);

        $this->send('➕ New request');

        $this->assertSame('name', $this->client()->draft['_step']);
    }

    public function test_back_does_nothing_on_the_first_unskipped_step(): void
    {
        $this->client(ConversationState::AwaitingStep, ['name' => 'Ali', 'phone' => '+998901234567', '_skip' => ['name', 'phone'], '_step' => 'point_type']);

        $this->send('⬅️ Back');

        $this->assertSame('point_type', $this->client()->draft['_step']);
    }

    public function test_registration_answers_are_saved_to_the_clients_profile(): void
    {
        $this->client(ConversationState::AwaitingConfirmation, $this->fullDraft());

        $this->tap('confirm:yes');

        $this->assertSame(['name' => 'Ali', 'phone' => '+998901234567'], $this->client()->profile);
    }

    public function test_start_for_a_registered_client_asks_language_then_skips_registration(): void
    {
        $this->client(ConversationState::Idle, null, Language::Ru)->update(['profile' => ['name' => 'Ali', 'phone' => '+998901234567']]);

        $this->send('/start');
        $this->tap('lang:en');

        $draft = $this->client()->draft;
        $this->assertSame('point_type', $draft['_step']);
        $this->assertSame(['name', 'phone'], $draft['_skip']);
        Http::assertSent(fn (Request $request) => str_contains((string) ($request['text'] ?? ''), 'Welcome back, <b>Ali</b>'));
    }

    public function test_a_newly_added_registration_step_is_asked_once_even_to_registered_clients(): void
    {
        RegistrationStep::factory()->registration()->create(['key' => 'company']);
        $this->client(ConversationState::Idle)->update(['profile' => ['name' => 'Ali', 'phone' => '+998901234567']]);

        $this->send('➕ New request');

        $this->assertSame('company', $this->client()->draft['_step']);
        $this->assertSame(['name', 'phone'], $this->client()->draft['_skip']);
    }

    public function test_the_stored_profile_is_used_even_without_a_previous_request(): void
    {
        $this->client(ConversationState::Idle)->update(['profile' => ['name' => 'Ali', 'phone' => '+998901234567']]);

        $this->send('/new');

        $this->assertSame('point_type', $this->client()->draft['_step']);
    }

    public function test_the_plan_step_sends_both_example_images_before_the_question(): void
    {
        $this->storeExamples(2);
        $this->client(ConversationState::AwaitingStep, [...Arr::except($this->fullDraft(), ['plan', 'location']), '_step' => 'location']);

        $this->send('⏭ Skip');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'sendMediaGroup')
            && str_contains($request->body(), (string) self::CHAT_ID)
            && str_contains($request->body(), 'name="file0"')
            && str_contains($request->body(), 'name="file1"')
            && str_contains($request->body(), 'Examples of a good plan'));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'sendMessage')
            && str_contains($request['text'], 'Plan of the shop'));
    }

    public function test_many_examples_are_all_sent_in_one_album(): void
    {
        $this->storeExamples(5);
        $this->client(ConversationState::AwaitingStep, [...Arr::except($this->fullDraft(), ['plan', 'location']), '_step' => 'location']);

        $this->send('⏭ Skip');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'sendMediaGroup')
            && str_contains($request->body(), 'name="file4"')
            && ! str_contains($request->body(), 'name="file5"'));
    }

    public function test_an_album_never_exceeds_telegrams_limit_of_ten(): void
    {
        $this->storeExamples(12);
        $this->client(ConversationState::AwaitingStep, [...Arr::except($this->fullDraft(), ['plan', 'location']), '_step' => 'location']);

        $this->send('⏭ Skip');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'sendMediaGroup')
            && str_contains($request->body(), 'name="file9"')
            && ! str_contains($request->body(), 'name="file10"'));
    }

    public function test_a_single_example_is_sent_as_one_photo(): void
    {
        $this->storeExamples(1);
        $this->client(ConversationState::AwaitingStep, [...Arr::except($this->fullDraft(), ['plan', 'location']), '_step' => 'location']);

        $this->send('⏭ Skip');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'sendPhoto'));
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'sendMediaGroup'));
    }

    public function test_no_images_are_sent_when_no_examples_are_configured(): void
    {
        $this->client(ConversationState::AwaitingStep, [...Arr::except($this->fullDraft(), ['plan', 'location']), '_step' => 'location']);

        $this->send('⏭ Skip');

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'sendMediaGroup') || str_contains($request->url(), 'sendPhoto'));
        $this->assertSame('plan', $this->client()->draft['_step']);
    }

    public function test_a_failing_example_upload_never_blocks_the_question(): void
    {
        $this->storeExamples(2);
        Http::swap(new HttpFactory);
        Http::fake([
            'api.telegram.org/*/sendMediaGroup' => Http::response(['ok' => false, 'description' => 'wrong file'], 400),
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []]),
        ]);
        $this->client(ConversationState::AwaitingStep, [...Arr::except($this->fullDraft(), ['plan', 'location']), '_step' => 'location']);

        $this->send('⏭ Skip');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'sendMessage') && str_contains($request['text'], 'Plan of the shop'));
        $this->assertSame('plan', $this->client()->draft['_step']);
    }

    public function test_examples_are_not_sent_on_other_steps(): void
    {
        $this->storeExamples(2);
        $this->client(ConversationState::AwaitingStep, ['name' => 'Ali', '_step' => 'phone']);

        $this->send('+998901234567');

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'sendMediaGroup'));
    }

    private function storeExamples(int $count): void
    {
        $examples = [];

        foreach (range(1, $count) as $slot) {
            $path = Storage::disk('local')->putFileAs('plan-examples', UploadedFile::fake()->image("example{$slot}.jpg"), "example{$slot}.jpg");
            $examples[$slot] = ['path' => $path, 'name' => "example{$slot}.jpg"];
        }

        Setting::write(Setting::PLAN_EXAMPLES, $examples);
    }

    private function fakeTelegram(): void
    {
        Http::fake([
            'api.telegram.org/*/getFile' => Http::response(['ok' => true, 'result' => ['file_path' => 'photos/file_1.jpg']]),
            'api.telegram.org/file/*' => Http::response('IMAGE-BYTES'),
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []]),
        ]);
    }

    private function send(string $text): void
    {
        $this->handleUpdate(['message' => $this->message(['text' => $text])]);
    }

    private function tap(string $callbackData): void
    {
        $this->handleUpdate(['callback_query' => [
            'id' => '1',
            'data' => $callbackData,
            'message' => ['message_id' => 10, 'chat' => ['id' => self::CHAT_ID]],
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fullDraft(): array
    {
        return [
            'name' => 'Ali',
            'phone' => '+998901234567',
            'point_type' => 1,
            'brand' => 'Makro',
            'location' => ['text' => 'Tashkent', 'lat' => null, 'lng' => null],
            'plan' => ['file_id' => 'F1', 'kind' => 'photo', 'name' => 'plan.jpg'],
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function message(array $extra): array
    {
        return [
            'chat' => ['id' => self::CHAT_ID, 'type' => 'private'],
            'from' => ['id' => self::CHAT_ID, 'first_name' => 'Botir'],
            ...$extra,
        ];
    }

    /**
     * @param  array<string, mixed>  $update
     */
    private function handleUpdate(array $update): void
    {
        app(BotHandler::class)->handle($update);
    }

    /**
     * @param  array<string, mixed>|null  $draft
     */
    private function client(?ConversationState $state = null, ?array $draft = null, Language $language = Language::En): TelegramUser
    {
        if ($state !== null) {
            return TelegramUser::factory()->create([
                'chat_id' => self::CHAT_ID,
                'state' => $state,
                'draft' => $draft,
                'language' => $language,
            ]);
        }

        return TelegramUser::where('chat_id', self::CHAT_ID)->firstOrFail();
    }
}
