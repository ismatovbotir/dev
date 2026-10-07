<?php

namespace App\Enums;

enum ConversationState: string
{
    case Idle = 'idle';
    case AwaitingLanguage = 'awaiting_language';
    case AwaitingStep = 'awaiting_step';
    case AwaitingConfirmation = 'awaiting_confirmation';
}
