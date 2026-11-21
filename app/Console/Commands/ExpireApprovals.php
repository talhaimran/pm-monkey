<?php

namespace App\Console\Commands;

use App\Services\ApprovalService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('approvals:expire')]
#[Description('Mark approval requests that have waited 7 days as expired')]
class ExpireApprovals extends Command
{
    public function handle(ApprovalService $approvals): int
    {
        $count = $approvals->expireDue();
        $this->info("Expired {$count} approval ".Str::plural('request', $count).'.');

        return self::SUCCESS;
    }
}
