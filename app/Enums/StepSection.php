<?php

namespace App\Enums;

enum StepSection: string
{
    case Registration = 'registration';
    case Conversation = 'conversation';

    public function label(): string
    {
        return match ($this) {
            self::Registration => 'Registration',
            self::Conversation => 'Conversation',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Registration => 'Asked once, the first time a client uses the bot. The answers are remembered.',
            self::Conversation => 'Asked for every new request.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Registration => 'user',
            self::Conversation => 'inbox',
        };
    }
}
