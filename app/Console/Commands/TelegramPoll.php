<?php

namespace App\Console\Commands;

use App\Services\Telegram\BotHandler;
use App\Services\Telegram\TelegramClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('telegram:poll')]
#[Description('Run the bot with long polling (for local development, no public URL needed)')]
class TelegramPoll extends Command
{
    public function handle(TelegramClient $telegram, BotHandler $bot): int
    {
        $telegram->deleteWebhook();
        $this->info('Polling Telegram for updates. Press Ctrl+C to stop.');

        $offset = 0;

        while (true) {
            try {
                foreach ($telegram->getUpdates($offset) as $update) {
                    $offset = $update['update_id'] + 1;

                    try {
                        $bot->handle($update);
                    } catch (Throwable $exception) {
                        report($exception);
                        $this->error($exception->getMessage());
                    }
                }
            } catch (Throwable $exception) {
                report($exception);
                $this->error($exception->getMessage());
                sleep(3);
            }
        }
    }
}
