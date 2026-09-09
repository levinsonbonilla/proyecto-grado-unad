<?php

namespace App\Enum;

enum SlideEnum: string
{
    case HOME = 'Home';

    public function label(): string
    {
        return match($this) {
            self::HOME => 'Home'
        };
    }

    public static function choices(): array
    {
        return [
            self::HOME->label() => self::HOME->value
        ];
    }
}
