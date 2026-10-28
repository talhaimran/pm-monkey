<?php

namespace App\Enums;

enum IssuePriority: string
{
    case Lowest = 'lowest';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Highest = 'highest';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
