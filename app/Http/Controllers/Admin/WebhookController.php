<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Telegram\TelegramClient;
use Illuminate\Http\JsonResponse;
use Throwable;

class WebhookController extends Controller
{
    public function __construct(private TelegramClient $telegram) {}

    public function set(): JsonResponse
    {
        $url = route('telegram.webhook');
        $secret = config('services.telegram.webhook_secret');

        if ($problem = $this->tokenProblem()) {
            return $problem;
        }

        if (blank($secret)) {
            return $this->failure('Webhook not set', 'The webhook secret is missing.', 'Set TELEGRAM_WEBHOOK_SECRET in .env to a long random string, then try again.');
        }

        if (! $this->isPublicHttpsUrl($url)) {
            return $this->failure(
                'Webhook not set',
                'Telegram only delivers updates to a public HTTPS address, and this one is not.',
                "This app is reachable at {$url}. For local development run `php artisan telegram:poll` instead. In production, open the panel over your public HTTPS domain (check APP_URL) and press this button again.",
            );
        }

        try {
            $this->telegram->setWebhook($url, $secret);
            $info = $this->telegram->call('getWebhookInfo');
        } catch (Throwable $exception) {
            return $this->failure('Webhook not set', $exception->getMessage());
        }

        return $this->success(
            'Webhook set',
            'Telegram will now send every client message to this app.',
            $this->webhookRows($info),
            'If `php artisan telegram:poll` is running, stop it: polling and a webhook cannot be used together.',
        );
    }

    public function info(): JsonResponse
    {
        if ($problem = $this->tokenProblem()) {
            return $problem;
        }

        try {
            $bot = $this->telegram->call('getMe');
            $info = $this->telegram->call('getWebhookInfo');
        } catch (Throwable $exception) {
            return $this->failure('Could not reach Telegram', $exception->getMessage(), 'Check that TELEGRAM_BOT_TOKEN in .env is correct.');
        }

        $mode = filled($info['url'] ?? null)
            ? 'This bot receives updates through a webhook.'
            : 'No webhook is set, so the bot only works while `php artisan telegram:poll` is running.';

        return $this->success('Bot connection', $mode, [
            ['label' => 'Bot', 'value' => '@'.($bot['username'] ?? '?').' ('.($bot['first_name'] ?? '').')'],
            ...$this->webhookRows($info),
        ]);
    }

    public function remove(): JsonResponse
    {
        if ($problem = $this->tokenProblem()) {
            return $problem;
        }

        try {
            $this->telegram->deleteWebhook();
        } catch (Throwable $exception) {
            return $this->failure('Webhook not removed', $exception->getMessage());
        }

        return $this->success('Webhook removed', 'Telegram no longer sends updates to this app.', [], 'For local development run `php artisan telegram:poll` to receive messages.');
    }

    private function tokenProblem(): ?JsonResponse
    {
        return blank(config('services.telegram.token'))
            ? $this->failure('Bot token missing', 'TELEGRAM_BOT_TOKEN is not set.', 'Create a bot with @BotFather, put its token in .env and try again.')
            : null;
    }

    /**
     * @param  array<string, mixed>  $info
     * @return list<array{label: string, value: string}>
     */
    private function webhookRows(array $info): array
    {
        $lastError = filled($info['last_error_message'] ?? null)
            ? $info['last_error_message'].' ('.date('d.m.Y H:i', (int) ($info['last_error_date'] ?? 0)).')'
            : 'None';

        return [
            ['label' => 'Webhook URL', 'value' => filled($info['url'] ?? null) ? $info['url'] : 'Not set'],
            ['label' => 'Pending updates', 'value' => (string) ($info['pending_update_count'] ?? 0)],
            ['label' => 'Last error', 'value' => $lastError],
            ['label' => 'Server IP', 'value' => $info['ip_address'] ?? '—'],
            ['label' => 'Max connections', 'value' => (string) ($info['max_connections'] ?? '—')],
        ];
    }

    /**
     * @param  list<array{label: string, value: string}>  $rows
     */
    private function success(string $title, string $message, array $rows = [], ?string $hint = null): JsonResponse
    {
        return response()->json(['ok' => true, 'title' => $title, 'message' => $message, 'hint' => $hint, 'rows' => $rows]);
    }

    private function failure(string $title, string $message, ?string $hint = null): JsonResponse
    {
        return response()->json(['ok' => false, 'title' => $title, 'message' => $message, 'hint' => $hint, 'rows' => []]);
    }

    /**
     * Telegram refuses plain HTTP and cannot reach private or local hostnames.
     */
    private function isPublicHttpsUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');

        if (($parts['scheme'] ?? '') !== 'https' || $host === '') {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        return str_contains($host, '.') && ! preg_match('/\.(local|localhost|test|internal|lan|home)$/', $host);
    }
}
