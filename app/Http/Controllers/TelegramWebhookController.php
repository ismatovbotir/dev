<?php

namespace App\Http\Controllers;

use App\Services\Telegram\BotHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, BotHandler $bot): JsonResponse
    {
        $secret = config('services.telegram.webhook_secret');

        abort_if(
            blank($secret) || ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token')),
            403,
        );

        $bot->handle($request->all());

        return response()->json(['ok' => true]);
    }
}
