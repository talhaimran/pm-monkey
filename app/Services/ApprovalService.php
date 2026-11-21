<?php

namespace App\Services;

use App\Enums\AgentStatus;
use App\Enums\ApprovalStatus;
use App\Enums\ProjectRole;
use App\Models\Agent;
use App\Models\ApprovalRequest;
use App\Models\Column;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Spec 8 (A2): an agent can't move an issue to Done on its own. Its attempt becomes an approval request that the
 * agent's owner or a project Admin decides. Before step 1, "Done" is the project's last column.
 *
 * Locks: deciding takes the request row, then the project row, then the target column (shared), then the issue,
 * the same order BoardService::move uses after the request; an agent's attempt never waits on a request row.
 */
class ApprovalService
{
    public function __construct(private CurrentWorkspace $current, private ActivityLogger $activity) {}

    /** The one rule: an agent moving an issue into its project's last column from anywhere else needs a person. */
    public function needsApproval(Issue $issue, Column $to, ?Agent $agent): bool
    {
        return $agent !== null
            && $to->id !== $issue->column_id
            && $to->id === Column::where('project_id', $to->project_id)->orderByDesc('position')->value('id');
    }

    /** Open a request for $agent's move of $issue to $to, or return the open one for the same issue and target. */
    public function requestDone(Issue $issue, User $owner, Agent $agent, Column $to, ?string $reason): ApprovalRequest
    {
        return DB::transaction(function () use ($issue, $owner, $agent, $to, $reason) {
            Issue::whereKey($issue->id)->lockForUpdate()->first();
            // A locking read sees requests committed after this transaction's snapshot; SKIP LOCKED never waits on a
            // request a person is deciding (they hold it and then wait for the project row this move may hold).
            $open = ApprovalRequest::open()->where('issue_id', $issue->id)->where('action->to_column_id', $to->id)
                ->lock('for update skip locked')->first();

            $approval = $open ?? ApprovalRequest::create([
                'workspace_id' => $issue->project->workspace_id,
                'issue_id' => $issue->id,
                'agent_id' => $agent->id,
                'owner_user_id' => $owner->id,
                'action' => ['to_column_id' => $to->id, 'to_column' => $to->name, 'from_column_id' => $issue->column_id, 'reason' => $reason],
                'status' => ApprovalStatus::Pending,
                'expires_at' => now()->addDays(ApprovalRequest::DAYS),
            ]);

            return $approval->setRelation('issue', $issue)->setRelation('agent', $agent->setRelation('owner', $owner));
        });
    }

    /**
     * Make the move as $decider, re-checking permissions and whether the issue moved since. A move that is no longer
     * possible marks the request stale; a moved issue needs $confirmChanged. Never moves silently.
     */
    public function approve(ApprovalRequest $approval, User $decider, bool $confirmChanged = false): ApprovalRequest
    {
        $approval = DB::transaction(function () use ($approval, $decider, $confirmChanged) {
            $approval = $this->lockPending($approval);
            if ($approval->status !== ApprovalStatus::Pending) {
                return $approval;
            }

            $projectId = Issue::withTrashed()->whereKey($approval->issue_id)->value('project_id');
            $project = Project::whereKey($projectId)->lockForUpdate()->firstOrFail();
            $column = Column::whereKey($approval->action['to_column_id'])->sharedLock()->first();
            $issue = Issue::withTrashed()->with('column')->whereKey($approval->issue_id)->lockForUpdate()->firstOrFail()->setRelation('project', $project);
            $this->authorize($approval->setRelation('issue', $issue), $decider);

            $stale = match (true) {
                $issue->trashed() => "{$issue->key} was deleted.",
                ! $column => "The column {$approval->action['to_column']} was deleted.",
                $approval->agent->status === AgentStatus::Revoked => "{$approval->agent->label()} was revoked.",
                $issue->column_id === $column->id => "{$issue->key} is already in {$column->name}.",
                default => null,
            };
            if ($stale) {
                $approval->update(['status' => ApprovalStatus::Stale, 'note' => $stale]);

                return $approval;
            }

            if ($issue->column_id !== $approval->action['from_column_id'] && ! $confirmChanged) {
                $from = Column::whereKey($approval->action['from_column_id'])->value('name') ?? 'a deleted column';
                throw ValidationException::withMessages([
                    'approval' => "{$issue->key} moved from {$from} to {$issue->column->name} since this request. Approve again to move it to {$column->name}.",
                ])->status(409);
            }

            ActivityLogger::forApproval($approval, fn () => app(BoardService::class)->move($issue, $decider, ['status' => $column->name], 'approved'));
            $approval->update(['status' => ApprovalStatus::Approved, 'decided_by' => $decider->id, 'decided_at' => now()]);

            return $approval;
        });

        return $this->settled($approval);
    }

    /** Turn the request down; the issue stays where it is. */
    public function reject(ApprovalRequest $approval, User $decider, ?string $note): ApprovalRequest
    {
        $approval = DB::transaction(function () use ($approval, $decider, $note) {
            $approval = $this->lockPending($approval);
            if ($approval->status !== ApprovalStatus::Pending) {
                return $approval;
            }

            $issue = Issue::withTrashed()->with('project')->whereKey($approval->issue_id)->firstOrFail();
            $this->authorize($approval->setRelation('issue', $issue), $decider);
            $approval->update(['status' => ApprovalStatus::Rejected, 'decided_by' => $decider->id, 'decided_at' => now(), 'note' => $note]);
            ActivityLogger::forApproval($approval, fn () => $this->activity->log($issue, $decider, 'rejected'));

            return $approval;
        });

        return $this->settled($approval);
    }

    /**
     * Open requests $viewer can decide in $workspace: their own agents' requests in projects where they can still
     * make the move, and every request in projects they administer.
     *
     * @return Collection<int, ApprovalRequest>
     */
    public function decidableBy(User $viewer, Workspace $workspace): Collection
    {
        $roles = DB::table('project_user')->where('user_id', $viewer->id)->pluck('role', 'project_id');
        $admin = $roles->filter(fn ($r) => $r === ProjectRole::Admin->value)->keys();
        $writer = $roles->filter(fn ($r) => ProjectRole::from($r)->canWrite())->keys();

        // shortcut: the 50 oldest; page it when the Pending approvals widget arrives (step 10).
        return ApprovalRequest::open()
            ->where('workspace_id', $workspace->id)
            ->where(fn ($q) => $q
                ->whereHas('issue', fn ($i) => $i->whereIn('project_id', $admin))
                ->orWhere(fn ($q) => $q->where('owner_user_id', $viewer->id)->whereHas('issue', fn ($i) => $i->whereIn('project_id', $writer))))
            ->with(['issue.project', 'issue.column', 'agent.owner'])
            ->oldest('id')
            ->limit(50)
            ->get();
    }

    /** A request in the current workspace on an issue $user can see; anything else is "not found". */
    public function resolve(User $user, int $id): ApprovalRequest
    {
        $approval = ApprovalRequest::whereKey($id)
            ->where('workspace_id', $this->current->id() ?? 0)
            ->whereHas('issue', fn ($q) => $q->withTrashed()->whereIn('project_id', $user->accessibleProjects()->select('projects.id')))
            ->first();

        if (! $approval) {
            throw ValidationException::withMessages([
                'approval' => "Approval request {$id} was not found. Open your dashboard to see the requests you can decide.",
            ])->status(404);
        }

        return $approval;
    }

    /** Mark pending requests past their expiry as expired; the agent can ask again. */
    public function expireDue(): int
    {
        return ApprovalRequest::where('status', ApprovalStatus::Pending)->where('expires_at', '<=', now())
            ->update(['status' => ApprovalStatus::Expired, 'updated_at' => now()]);
    }

    /** The request row locked; a pending one past its expiry is marked expired here. */
    private function lockPending(ApprovalRequest $approval): ApprovalRequest
    {
        $approval = ApprovalRequest::with('agent.owner')->whereKey($approval->id)->lockForUpdate()->firstOrFail();

        if ($approval->status !== ApprovalStatus::Pending) {
            throw ValidationException::withMessages(['approval' => "This request was already {$approval->status->value}."])->status(409);
        }
        if ($approval->expires_at->isPast()) {
            $approval->update(['status' => ApprovalStatus::Expired]);
        }

        return $approval;
    }

    private function authorize(ApprovalRequest $approval, User $decider): void
    {
        Gate::forUser($decider)->authorize('decide', $approval);
    }

    /** Expired or stale requests were saved as such; tell the decider why nothing moved. */
    private function settled(ApprovalRequest $approval): ApprovalRequest
    {
        $message = match ($approval->status) {
            ApprovalStatus::Expired => 'This request was already expired.',
            ApprovalStatus::Stale => "Can't approve: {$approval->note} The request is now stale.",
            default => null,
        };

        if ($message) {
            throw ValidationException::withMessages(['approval' => $message])->status(409);
        }

        return $approval;
    }
}
