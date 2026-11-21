<?php

namespace App\Enums;

/** Spec 8 (A2): where an approval request stands. Only pending ones can be decided. */
enum ApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Stale = 'stale';
}
