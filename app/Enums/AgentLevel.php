<?php

namespace App\Enums;

/** Spec 8 (A1): what an agent may do, never more than its owner's project role allows. */
enum AgentLevel: string
{
    case Read = 'read';
    case Write = 'write';
    case Delete = 'delete';

    public function canWrite(): bool
    {
        return $this !== self::Read;
    }

    public function canDelete(): bool
    {
        return $this === self::Delete;
    }
}
