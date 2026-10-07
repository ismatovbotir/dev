<?php

namespace App\Enums;

enum RequestStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Answered = 'answered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::InProgress => 'In progress',
            self::Answered => 'Answered',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::New => 'bg-amber-50 text-amber-700 ring-amber-600/20',
            self::InProgress => 'bg-sky-50 text-sky-700 ring-sky-600/20',
            self::Answered => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            self::Cancelled => 'bg-slate-100 text-slate-600 ring-slate-500/20',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::New => 'inbox',
            self::InProgress => 'clock',
            self::Answered => 'check-circle',
            self::Cancelled => 'x-circle',
        };
    }
}
