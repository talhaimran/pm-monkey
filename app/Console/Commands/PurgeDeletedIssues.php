<?php

namespace App\Console\Commands;

use App\Models\Comment;
use App\Models\Issue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('issues:purge')]
#[Description('Delete for good the issues and comments that were soft-deleted more than 30 days ago')]
class PurgeDeletedIssues extends Command
{
    public const DAYS = 30;

    public function handle(): int
    {
        $before = now()->subDays(self::DAYS);
        $issues = 0;

        // Sub-tasks go with their parent at the DB level, so a row may already be gone when its turn comes.
        Issue::onlyTrashed()->where('deleted_at', '<', $before)->with('project')->each(function (Issue $issue) use (&$issues) {
            // shortcut: purges go to the application log, move to AuditLogger when the audit log lands (step 0).
            Log::info('issue.purged', ['key' => $issue->key, 'title' => $issue->title, 'deleted_at' => $issue->deleted_at->toIso8601String()]);
            $issues += (int) $issue->forceDelete();
        });
        $comments = Comment::onlyTrashed()->where('deleted_at', '<', $before)->forceDelete();

        $this->info("Purged {$issues} issues and {$comments} comments deleted before {$before->toDateString()}.");

        return self::SUCCESS;
    }
}
