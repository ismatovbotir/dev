<?php

namespace App\Enums;

enum StepType: string
{
    case Text = 'text';
    case Phone = 'phone';
    case Location = 'location';
    case Choice = 'choice';
    case File = 'file';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Free text',
            self::Phone => 'Phone number',
            self::Location => 'Location or address',
            self::Choice => 'Choice buttons',
            self::File => 'Photo or file',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Text => 'file',
            self::Phone => 'phone',
            self::Location => 'map-pin',
            self::Choice => 'tag',
            self::File => 'paperclip',
        };
    }
}
