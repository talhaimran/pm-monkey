<?php

namespace App\Enums;

enum IssueType: string
{
    case Task = 'task';
    case Bug = 'bug';
    case Story = 'story';
    case Epic = 'epic';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
