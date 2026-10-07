<?php

namespace App\Services\Telegram;

use App\Enums\ConversationState;
use App\Enums\Language;
use App\Enums\StepSection;
use App\Enums\StepType;
use App\Models\RegistrationStep;
use App\Models\Setting;
use App\Models\ShopRequest;
use App\Models\TelegramUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BotHandler
{
    private const CORE_FIELDS = ['phone', 'name', 'brand'];

    public function __construct(private TelegramClient $telegram) {}

    /**
     * Process one Telegram update.
     *
     * @param  array<string, mixed>  $update
     */
    public function handle(array $update): void
    {
        if (isset($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);

            return;
        }

        $message = $update['message'] ?? null;

        if ($message === null || ($message['chat']['type'] ?? null) !== 'private') {
            return;
        }

        $user = TelegramUser::firstOrCreate(
            ['chat_id' => $message['chat']['id']],
            [
                'username' => $message['from']['username'] ?? null,
                'first_name' => $message['from']['first_name'] ?? null,
                'state' => ConversationState::Idle,
            ],
        );

        $text = trim((string) ($message['text'] ?? ''));

        if (str_starts_with($text, '/')) {
            $this->handleCommand($user, $text);

            return;
        }

        if ($user->state === ConversationState::Idle && $this->isNewRequestButton($text)) {
            $user->language === null ? $this->askLanguage($user) : $this->startRequest($user, reuseProfile: true);

            return;
        }

        if ($text !== '' && $text === $this->t($user, 'back_button')) {
            $this->goBack($user);

            return;
        }

        match ($user->state) {
            ConversationState::AwaitingLanguage => $this->askLanguage($user),
            ConversationState::AwaitingStep => $this->receiveAnswer($user, $message, $text),
            ConversationState::AwaitingConfirmation => $this->showSummary($user),
            ConversationState::Idle => $user->language === null
                ? $this->askLanguage($user)
                : $this->reply($user, 'idle', keyboard: $this->newRequestKeyboard($user)),
        };
    }

    /**
     * @param  array<string, mixed>  $callback
     */
    private function handleCallback(array $callback): void
    {
        $chatId = $callback['message']['chat']['id'] ?? null;
        $data = (string) ($callback['data'] ?? '');

        $this->telegram->call('answerCallbackQuery', ['callback_query_id' => $callback['id']]);

        $user = $chatId === null ? null : TelegramUser::where('chat_id', $chatId)->first();

        if ($user === null) {
            return;
        }

        match (true) {
            str_starts_with($data, 'lang:') => $this->chooseLanguage($user, substr($data, 5)),
            $data === 'confirm:yes' => $this->confirm($user, $callback['message']),
            $data === 'confirm:no' => $this->restart($user, $callback['message']),
            default => null,
        };
    }

    private function chooseLanguage(TelegramUser $user, string $code): void
    {
        $language = Language::tryFrom($code);

        if ($language === null) {
            return;
        }

        $user->update(['language' => $language]);
        $this->reply($user, 'language_saved');

        $this->startRequest($user, withWelcome: true, reuseProfile: true);
    }

    private function handleCommand(TelegramUser $user, string $text): void
    {
        $command = strtolower(explode('@', explode(' ', $text)[0])[0]);

        match ($command) {
            '/start' => $this->askLanguage($user),
            '/new' => $user->language === null ? $this->askLanguage($user) : $this->startRequest($user, reuseProfile: true),
            '/language' => $this->askLanguage($user),
            '/cancel' => $this->cancel($user),
            default => $this->reply($user, 'help'),
        };
    }

    private function askLanguage(TelegramUser $user): void
    {
        $user->update(['state' => ConversationState::AwaitingLanguage]);

        $buttons = array_map(
            fn (Language $language) => [['text' => $language->label(), 'callback_data' => 'lang:'.$language->value]],
            Language::cases(),
        );

        $this->telegram->sendMessage($user->chat_id, __('bot.choose_language', [], 'uz'), [
            'reply_markup' => ['inline_keyboard' => $buttons],
        ]);
    }

    /**
     * Begin the questionnaire. A returning client (reuseProfile) skips the questions we already have answers for.
     */
    private function startRequest(TelegramUser $user, bool $withWelcome = false, bool $reuseProfile = false): void
    {
        $known = $reuseProfile ? $this->knownProfile($user) : [];

        $user->update(['draft' => $known === [] ? [] : [...$known, '_skip' => array_keys($known)]]);

        if (isset($known['name'])) {
            $this->reply($user, 'welcome_back', ['name' => e($known['name'])]);
        } elseif ($withWelcome) {
            $this->reply($user, 'welcome', ['company' => e(config('app.name'))]);
        }

        $steps = $this->steps($user);

        if ($steps->isEmpty()) {
            if ($this->allSteps()->isEmpty()) {
                $user->update(['state' => ConversationState::Idle]);
                $this->reply($user, 'no_steps');
            } else {
                $this->showSummary($user);
            }

            return;
        }

        $this->askStep($user, $steps, 0);
    }

    /**
     * Ask the configured step at the given position with a progress header and a fitting keyboard.
     *
     * @param  Collection<int, RegistrationStep>  $steps
     */
    private function askStep(TelegramUser $user, Collection $steps, int $index): void
    {
        $step = $steps->values()[$index];

        $user->update([
            'state' => ConversationState::AwaitingStep,
            'draft' => [...($user->draft ?? []), '_step' => $step->key],
        ]);

        $header = $this->t($user, 'step', [
            'current' => $index + 1,
            'total' => $steps->count(),
            'bar' => str_repeat('▰', $index + 1).str_repeat('▱', $steps->count() - $index - 1),
        ]);

        $rows = match ($step->type) {
            StepType::Phone => [[['text' => $this->t($user, 'share_phone_button'), 'request_contact' => true]]],
            StepType::Location => [[['text' => $this->t($user, 'share_location_button'), 'request_location' => true]]],
            StepType::Choice => array_map(
                fn (array $chunk) => array_map(fn (string $option) => ['text' => $option], $chunk),
                array_chunk($step->optionsFor($user->locale()), 2),
            ),
            StepType::Text, StepType::File => [],
        };

        if (! $step->is_required) {
            $rows[] = [['text' => $this->t($user, 'skip_button')]];
        }

        if ($index > 0) {
            $rows[] = [['text' => $this->t($user, 'back_button')]];
        }

        if ($step->type === StepType::File && $step->key === 'plan') {
            $this->sendPlanExamples($user);
        }

        $this->telegram->sendMessage(
            $user->chat_id,
            $header."\n\n".$step->questionFor($user->locale()),
            ['reply_markup' => $rows === [] ? ['remove_keyboard' => true] : ['keyboard' => $rows, 'resize_keyboard' => true]],
        );
    }

    /**
     * Show the client what a good plan looks like. A failure here must never block the questionnaire.
     */
    private function sendPlanExamples(TelegramUser $user): void
    {
        $paths = Setting::planExamplePaths();

        if ($paths === []) {
            return;
        }

        try {
            $this->telegram->sendPhotoAlbum($user->chat_id, 'local', $paths, $this->t($user, 'plan_examples_caption'));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function receiveAnswer(TelegramUser $user, array $message, string $text): void
    {
        $steps = $this->steps($user);

        if ($steps->isEmpty()) {
            $this->cancel($user);

            return;
        }

        $index = $this->currentIndex($user, $steps);
        $step = $steps->values()[$index];

        if (! $step->is_required && $text === $this->t($user, 'skip_button')) {
            $value = null;
        } else {
            $value = $this->parseAnswer($user, $step, $message, $text);

            if ($value === false) {
                $this->reply($user, 'invalid_'.$step->type->value);

                return;
            }
        }

        $user->update(['draft' => [...($user->draft ?? []), $step->key => $value]]);

        $index + 1 < $steps->count() ? $this->askStep($user, $steps, $index + 1) : $this->showSummary($user);
    }

    /**
     * Validate the raw answer for a step. Returns false when it is not acceptable.
     *
     * @param  array<string, mixed>  $message
     * @return string|int|array<string, mixed>|false
     */
    private function parseAnswer(TelegramUser $user, RegistrationStep $step, array $message, string $text): string|int|array|false
    {
        switch ($step->type) {
            case StepType::Phone:
                $digits = preg_replace('/\D+/', '', (string) ($message['contact']['phone_number'] ?? $text));

                return strlen($digits) >= 9 && strlen($digits) <= 15 ? '+'.$digits : false;

            case StepType::Location:
                if (isset($message['location'])) {
                    return [
                        'text' => $this->t($user, 'location_received'),
                        'lat' => $message['location']['latitude'],
                        'lng' => $message['location']['longitude'],
                    ];
                }

                return mb_strlen($text) >= 3 && mb_strlen($text) <= 255 ? ['text' => $text, 'lat' => null, 'lng' => null] : false;

            case StepType::Choice:
                foreach ($step->optionsFor($user->locale()) as $position => $option) {
                    if (mb_strtolower($option) === mb_strtolower($text)) {
                        return $position;
                    }
                }

                return false;

            case StepType::File:
                $photo = $message['photo'] ?? [];

                if ($photo !== []) {
                    return ['file_id' => end($photo)['file_id'], 'kind' => 'photo', 'name' => 'plan.jpg'];
                }

                $document = $message['document'] ?? null;
                $mime = (string) ($document['mime_type'] ?? '');
                $acceptable = str_starts_with($mime, 'image/') || $mime === 'application/pdf';

                if ($document !== null && $acceptable && ($document['file_size'] ?? 0) <= 20 * 1024 * 1024) {
                    return ['file_id' => $document['file_id'], 'kind' => 'document', 'name' => $document['file_name'] ?? 'plan'];
                }

                return false;

            case StepType::Text:
                return mb_strlen($text) >= 1 && mb_strlen($text) <= 255 ? $text : false;
        }
    }

    private function showSummary(TelegramUser $user): void
    {
        $steps = $this->allSteps();
        $flow = $this->steps($user);
        $draft = $user->draft ?? [];

        $missing = $flow->search(fn (RegistrationStep $step) => $step->is_required && blank($draft[$step->key] ?? null));

        if ($missing !== false) {
            $this->askStep($user, $flow, $missing);

            return;
        }

        $user->update(['state' => ConversationState::AwaitingConfirmation]);

        $lines = $steps->map(fn (RegistrationStep $step) => sprintf(
            '%s <b>%s:</b> %s',
            '▪️',
            e($step->labelFor($user->locale())),
            e($this->displayValue($step, $draft[$step->key] ?? null, $user->locale())),
        ))->implode("\n");

        $this->telegram->sendMessage(
            $user->chat_id,
            $this->t($user, 'summary_title')."\n\n".$lines."\n\n".$this->t($user, 'summary_question'),
            ['reply_markup' => ['inline_keyboard' => [
                [['text' => $this->t($user, 'confirm_button'), 'callback_data' => 'confirm:yes']],
                [['text' => $this->t($user, 'restart_button'), 'callback_data' => 'confirm:no']],
            ]]],
        );
    }

    /**
     * @param  array<string, mixed>  $sourceMessage
     */
    private function confirm(TelegramUser $user, array $sourceMessage): void
    {
        if ($user->state !== ConversationState::AwaitingConfirmation) {
            return;
        }

        $steps = $this->allSteps();
        $draft = $user->draft ?? [];

        $attributes = [];
        $answers = [];

        foreach ($steps as $step) {
            $value = $draft[$step->key] ?? null;

            if (in_array($step->key, self::CORE_FIELDS, true)) {
                $attributes[$step->key] = $value;
            } elseif ($step->key === 'location' && is_array($value)) {
                $attributes['location_text'] = $value['text'];
                $attributes['latitude'] = $value['lat'];
                $attributes['longitude'] = $value['lng'];
            } elseif ($value !== null) {
                $isFile = $step->type === StepType::File && is_array($value);

                $answers[] = [
                    'key' => $step->key,
                    'label' => $step->labelFor('en'),
                    'value' => $isFile ? $value['name'] : $this->displayValue($step, $value, 'en'),
                    'file' => null,
                    'file_id' => $isFile ? $value['file_id'] : null,
                ];
            }
        }

        $shopRequest = $user->shopRequests()->create([...$attributes, 'answers' => $answers]);

        foreach ($answers as $position => $answer) {
            if ($answer['file_id'] !== null) {
                $step = $steps->firstWhere('key', $answer['key']);
                $answers[$position]['file'] = $this->storeAttachment($shopRequest, $step, [
                    'file_id' => $answer['file_id'], 'kind' => 'file', 'name' => $answer['value'],
                ]);
            }
        }

        $shopRequest->update(['answers' => $answers]);

        $profile = $steps
            ->filter(fn (RegistrationStep $step) => $step->section === StepSection::Registration)
            ->mapWithKeys(fn (RegistrationStep $step) => [$step->key => $draft[$step->key] ?? null])
            ->filter(fn (mixed $value) => filled($value))
            ->all();

        $user->update([
            'state' => ConversationState::Idle,
            'draft' => null,
            'profile' => [...($user->profile ?? []), ...$profile],
        ]);

        $this->removeInlineButtons($user, $sourceMessage);
        $this->reply($user, 'request_received', [
            'id' => $shopRequest->id,
            'name' => e($shopRequest->name ?? $user->first_name ?? ''),
        ], $this->newRequestKeyboard($user));

        $this->notifyAdmin($shopRequest);
    }

    /**
     * @param  array<string, mixed>  $sourceMessage
     */
    private function restart(TelegramUser $user, array $sourceMessage): void
    {
        if ($user->state !== ConversationState::AwaitingConfirmation) {
            return;
        }

        $this->removeInlineButtons($user, $sourceMessage);
        $this->startRequest($user, reuseProfile: ! empty($user->draft['_skip']));
    }

    private function goBack(TelegramUser $user): void
    {
        $steps = $this->steps($user);

        if ($steps->isEmpty()) {
            return;
        }

        if ($user->state === ConversationState::AwaitingConfirmation) {
            $this->askStep($user, $steps, $steps->count() - 1);
        } elseif ($user->state === ConversationState::AwaitingStep) {
            $index = $this->currentIndex($user, $steps);

            if ($index > 0) {
                $this->askStep($user, $steps, $index - 1);
            }
        }
    }

    private function cancel(TelegramUser $user): void
    {
        $user->update(['state' => ConversationState::Idle, 'draft' => null]);
        $this->reply($user, 'cancelled', keyboard: $this->newRequestKeyboard($user));
    }

    /**
     * @param  array<string, mixed>  $sourceMessage
     */
    private function removeInlineButtons(TelegramUser $user, array $sourceMessage): void
    {
        try {
            $this->telegram->call('editMessageReplyMarkup', [
                'chat_id' => $user->chat_id,
                'message_id' => $sourceMessage['message_id'],
                'reply_markup' => ['inline_keyboard' => []],
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function notifyAdmin(ShopRequest $shopRequest): void
    {
        $chatId = config('services.telegram.admin_chat_id');

        if (blank($chatId)) {
            return;
        }

        try {
            $this->telegram->sendMessage((int) $chatId, sprintf(
                "🆕 New request #%d\n%s · %s\n%s",
                $shopRequest->id,
                e($shopRequest->name ?? '—'),
                e($shopRequest->phone ?? '—'),
                route('admin.requests.show', $shopRequest),
            ));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Every active step, in order.
     *
     * @return Collection<int, RegistrationStep>
     */
    private function allSteps(): Collection
    {
        return RegistrationStep::active()->ordered()->get();
    }

    /**
     * The steps this client still has to answer (steps already known from a previous request are skipped).
     *
     * @return Collection<int, RegistrationStep>
     */
    private function steps(TelegramUser $user): Collection
    {
        $skipped = $user->draft['_skip'] ?? [];

        return $this->allSteps()->reject(fn (RegistrationStep $step) => in_array($step->key, $skipped, true))->values();
    }

    /**
     * Registration answers already given by this client, limited to registration steps that are still active.
     * Clients registered before profiles existed fall back to the name and phone of their latest request.
     *
     * @return array<string, mixed>
     */
    private function knownProfile(TelegramUser $user): array
    {
        $latest = $user->shopRequests()->latest('id')->first();
        $registrationKeys = $this->allSteps()
            ->filter(fn (RegistrationStep $step) => $step->section === StepSection::Registration)
            ->pluck('key');

        return collect([...['name' => $latest?->name, 'phone' => $latest?->phone], ...($user->profile ?? [])])
            ->filter(fn (mixed $value, string $key) => filled($value) && $registrationKeys->contains($key))
            ->all();
    }

    private function isNewRequestButton(string $text): bool
    {
        return $text !== '' && collect(Language::cases())
            ->contains(fn (Language $language) => $text === __('bot.new_request_button', [], $language->value));
    }

    /**
     * @return array{keyboard: list<list<array{text: string}>>, resize_keyboard: true}
     */
    private function newRequestKeyboard(TelegramUser $user): array
    {
        return ['keyboard' => [[['text' => $this->t($user, 'new_request_button')]]], 'resize_keyboard' => true];
    }

    /**
     * @param  Collection<int, RegistrationStep>  $steps
     */
    private function currentIndex(TelegramUser $user, Collection $steps): int
    {
        $found = $steps->values()->search(fn (RegistrationStep $step) => $step->key === ($user->draft['_step'] ?? null));

        return $found === false ? 0 : $found;
    }

    private function displayValue(RegistrationStep $step, mixed $value, string $locale): string
    {
        return match (true) {
            $value === null || $value === '' => '—',
            $step->type === StepType::Phone => $this->formatPhone((string) $value),
            $step->type === StepType::Choice => $step->optionsFor($locale)[$value] ?? '—',
            $step->type === StepType::File => __('bot.file_received', [], $locale),
            is_array($value) => (string) ($value['text'] ?? '—'),
            default => (string) $value,
        };
    }

    /**
     * Download a client's plan from Telegram and keep it on the local disk.
     *
     * @param  array{file_id: string, kind: string, name: string}  $file
     */
    private function storeAttachment(ShopRequest $shopRequest, RegistrationStep $step, array $file): ?string
    {
        try {
            $download = $this->telegram->downloadFile($file['file_id']);
            $path = "plans/{$shopRequest->id}/{$step->key}.{$download['extension']}";
            Storage::put($path, $download['contents']);

            return $path;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function formatPhone(string $phone): string
    {
        if (preg_match('/^\+998(\d{2})(\d{3})(\d{2})(\d{2})$/', $phone, $parts)) {
            return "+998 {$parts[1]} {$parts[2]} {$parts[3]} {$parts[4]}";
        }

        return $phone;
    }

    /**
     * @param  array<string, string|int>  $replace
     * @param  array<string, mixed>|null  $keyboard
     */
    private function reply(TelegramUser $user, string $key, array $replace = [], ?array $keyboard = null): void
    {
        $this->telegram->sendMessage(
            $user->chat_id,
            $this->t($user, $key, $replace),
            $keyboard === null ? [] : ['reply_markup' => $keyboard],
        );
    }

    /**
     * @param  array<string, string|int>  $replace
     */
    private function t(TelegramUser $user, string $key, array $replace = []): string
    {
        return __("bot.{$key}", $replace, $user->locale());
    }
}
