<?php

namespace App\Console\Commands;

use App\Models\Comment;
use App\Models\Issue;
use App\Services\AuditLogger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('issues:purge')]
#[Description('Delete for good the issues and comments that were soft-deleted more than 30 days ago')]
class PurgeDeletedIssues extends Command
{
    public const DAYS = 30;

    public function handle(AuditLogger $audit): int
    {
        $before = now()->subDays(self::DAYS);
        $issues = 0;

        // eachById, not each: rows are deleted while paging, and offset paging would skip every other page.
        // Sub-tasks go with their parent at the DB level, so a row may already be gone when its turn comes.
        Issue::onlyTrashed()->where('deleted_at', '<', $before)->with('project')->eachById(function (Issue $issue) use (&$issues, $audit) {
            // E8: the audit log by key only; the title goes with the issue.
            $audit->system('issue.purged', $issue, $issue->project->workspace()->first(), ['deleted_at' => $issue->deleted_at->toIso8601String()]);
            $issues += (int) $issue->forceDelete();
        });
        $comments = Comment::onlyTrashed()->where('deleted_at', '<', $before)->forceDelete();

        $this->info("Purged {$issues} issues and {$comments} comments deleted before {$before->toDateString()}.");

        return self::SUCCESS;
    }
}
