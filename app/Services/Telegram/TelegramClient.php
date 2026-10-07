<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class TelegramClient
{
    /**
     * Call a Bot API method with a JSON body.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function call(string $method, array $params = [], int $timeout = 15): array
    {
        $response = $this->http($timeout)->post($this->url($method), $params);

        return $this->unwrap($response->json(), $method);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function sendMessage(int $chatId, string $text, array $extra = []): array
    {
        return $this->call('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            ...$extra,
        ]);
    }

    /**
     * Send a file from a local disk. Images go out as photos, anything else as a document.
     *
     * @return array<string, mixed>
     */
    public function sendFile(int $chatId, string $disk, string $path, string $filename, string $caption): array
    {
        $isImage = str_starts_with((string) Storage::disk($disk)->mimeType($path), 'image/');
        $method = $isImage ? 'sendPhoto' : 'sendDocument';

        $response = $this->http(60)
            ->attach($isImage ? 'photo' : 'document', Storage::disk($disk)->get($path), $filename)
            ->post($this->url($method), [
                'chat_id' => $chatId,
                'caption' => $caption,
                'parse_mode' => 'HTML',
            ]);

        return $this->unwrap($response->json(), $method);
    }

    /**
     * Send one or more images from a local disk as a single album, with the caption on the first image.
     *
     * @param  list<string>  $paths
     */
    public function sendPhotoAlbum(int $chatId, string $disk, array $paths, string $caption): void
    {
        if (count($paths) === 1) {
            $this->sendFile($chatId, $disk, $paths[0], basename($paths[0]), $caption);

            return;
        }

        $request = $this->http(60);
        $media = [];

        foreach (array_values($paths) as $position => $path) {
            $request = $request->attach("file{$position}", Storage::disk($disk)->get($path), basename($path));
            $media[] = array_filter([
                'type' => 'photo',
                'media' => "attach://file{$position}",
                'caption' => $position === 0 ? $caption : null,
                'parse_mode' => 'HTML',
            ]);
        }

        $response = $request->post($this->url('sendMediaGroup'), [
            'chat_id' => $chatId,
            'media' => json_encode($media),
        ]);

        $this->unwrap($response->json(), 'sendMediaGroup');
    }

    /**
     * Download a file the client sent to the bot.
     *
     * @return array{contents: string, extension: string}
     */
    public function downloadFile(string $fileId): array
    {
        $info = $this->call('getFile', ['file_id' => $fileId]);
        $remotePath = (string) ($info['file_path'] ?? '');

        if ($remotePath === '') {
            throw new RuntimeException('Telegram getFile returned no file_path');
        }

        $response = $this->http(60)->get('https://api.telegram.org/file/bot'.config('services.telegram.token').'/'.$remotePath);

        if ($response->failed()) {
            throw new RuntimeException('Telegram file download failed with status '.$response->status());
        }

        return ['contents' => $response->body(), 'extension' => strtolower(pathinfo($remotePath, PATHINFO_EXTENSION)) ?: 'bin'];
    }

    /**
     * Long-poll for updates.
     *
     * @return list<array<string, mixed>>
     */
    public function getUpdates(int $offset, int $pollSeconds = 25): array
    {
        return $this->call('getUpdates', [
            'offset' => $offset,
            'timeout' => $pollSeconds,
            'allowed_updates' => ['message', 'callback_query'],
        ], $pollSeconds + 10);
    }

    /**
     * @return array<string, mixed>
     */
    public function setWebhook(string $url, ?string $secret = null): array
    {
        return $this->call('setWebhook', array_filter([
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => ['message', 'callback_query'],
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteWebhook(): array
    {
        return $this->call('deleteWebhook');
    }

    private function http(int $timeout): PendingRequest
    {
        return Http::timeout($timeout)->acceptJson();
    }

    private function url(string $method): string
    {
        $token = config('services.telegram.token');

        if (blank($token)) {
            throw new RuntimeException('TELEGRAM_BOT_TOKEN is not set in .env');
        }

        return "https://api.telegram.org/bot{$token}/{$method}";
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    private function unwrap(?array $payload, string $method): array
    {
        if (! ($payload['ok'] ?? false)) {
            throw new RuntimeException("Telegram {$method} failed: ".($payload['description'] ?? 'no response'));
        }

        return $payload['result'] ?? [];
    }
}
