<?php

namespace App\Services\Telegram;

use App\Models\ShopRequest;

class RequestReplySender
{
    public function __construct(private TelegramClient $telegram) {}

    /**
     * Deliver the admin's ready file and optional comment to the client in their own language.
     *
     * @throws \RuntimeException When Telegram rejects the message.
     */
    public function send(ShopRequest $shopRequest): void
    {
        $client = $shopRequest->telegramUser;
        $locale = $client->locale();

        $comment = filled($shopRequest->admin_comment)
            ? __('bot.reply_comment', ['comment' => e($shopRequest->admin_comment)], $locale)
            : '';

        $caption = trim(__('bot.reply_caption', [
            'id' => $shopRequest->id,
            'comment' => $comment,
        ], $locale));

        if ($shopRequest->drawing_path === null) {
            $this->telegram->sendMessage($client->chat_id, $caption);

            return;
        }

        $this->telegram->sendFile(
            $client->chat_id,
            'local',
            $shopRequest->drawing_path,
            $shopRequest->drawing_name ?? basename($shopRequest->drawing_path),
            $caption,
        );
    }
}
