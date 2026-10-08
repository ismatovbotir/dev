<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Telegram\BotHandler;
use App\Services\Telegram\TelegramClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('telegram:poll {--once : Fetch and process a single batch of updates, then exit}')]
#[Description('Run the bot with long polling (for local development, no public URL needed)')]
class TelegramPoll extends Command
{
    public const LAST_UPDATE_SETTING = 'telegram_last_update_id';

    public function handle(TelegramClient $telegram, BotHandler $bot): int
    {
        $telegram->deleteWebhook();
        $this->info('Polling Telegram for updates. Press Ctrl+C to stop.');

        // Resume after the last update we handled, so a restart never replays messages.
        $lastHandled = (int) Setting::read(self::LAST_UPDATE_SETTING, 0);
        $offset = $lastHandled > 0 ? $lastHandled + 1 : 0;

        while (true) {
            try {
                foreach ($telegram->getUpdates($offset, $this->option('once') ? 0 : 25) as $update) {
                    $offset = $update['update_id'] + 1;

                    try {
                        $bot->handle($update);
                    } catch (Throwable $exception) {
                        report($exception);
                        $this->error($exception->getMessage());
                    }

                    Setting::write(self::LAST_UPDATE_SETTING, $update['update_id']);
                }
            } catch (Throwable $exception) {
                report($exception);
                $this->error($exception->getMessage());

                if ($this->option('once')) {
                    return self::FAILURE;
                }

                sleep(3);
            }

            if ($this->option('once')) {
                return self::SUCCESS;
            }
        }
    }
}
