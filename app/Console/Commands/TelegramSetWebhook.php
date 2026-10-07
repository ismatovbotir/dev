<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('telegram:webhook {--delete : Remove the webhook instead of setting it}')]
#[Description('Register this app\'s public /telegram/webhook URL with Telegram (production)')]
class TelegramSetWebhook extends Command
{
    public function handle(TelegramClient $telegram): int
    {
        if ($this->option('delete')) {
            $telegram->deleteWebhook();
            $this->info('Webhook removed.');

            return self::SUCCESS;
        }

        if (blank(config('services.telegram.webhook_secret'))) {
            $this->error('Set TELEGRAM_WEBHOOK_SECRET in .env first.');

            return self::FAILURE;
        }

        $url = route('telegram.webhook');
        $telegram->setWebhook($url, config('services.telegram.webhook_secret'));
        $this->info("Webhook set to {$url}");

        return self::SUCCESS;
    }
}
