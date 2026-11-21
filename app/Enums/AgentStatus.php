<?php

namespace App\Enums;

enum AgentStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Revoked = 'revoked';
}
