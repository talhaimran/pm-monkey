<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Services\WorkspaceService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('workspaces:purge')]
#[Description('Delete for good the workspaces whose 7-day deletion period has ended')]
class PurgeDeletedWorkspaces extends Command
{
    public function handle(WorkspaceService $workspaces): int
    {
        $purged = 0;
        // eachById: rows are deleted while paging.
        Workspace::where('deletion_scheduled_at', '<=', now())->eachById(function (Workspace $workspace) use ($workspaces, &$purged) {
            $workspaces->purge($workspace);
            $purged++;
        });

        $this->info("Purged {$purged} workspaces.");

        return self::SUCCESS;
    }
}
