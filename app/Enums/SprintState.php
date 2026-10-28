<?php

namespace App\Enums;

enum SprintState: string
{
    case Planned = 'planned';
    case Active = 'active';
    case Completed = 'completed';
}
