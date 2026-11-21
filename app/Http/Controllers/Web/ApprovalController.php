<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\ApprovalService;
use App\Services\UndoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Spec 8 (A2): a person approves or rejects an agent's request, from the dashboard or the issue's AI box. */
class ApprovalController extends Controller
{
    public function __construct(private ApprovalService $approvals, private UndoService $undo) {}

    public function approve(Request $request, int $approval): RedirectResponse
    {
        $request->validate(['confirm_changed' => ['sometimes', 'boolean']]);
        $approval = $this->approvals->resolve($request->user(), $approval)->load('issue.project');
        $before = $approval->issue ? $this->undo->forMove($approval->issue) : [];

        $issue = $this->approvals->approve($approval, $request->user(), $request->boolean('confirm_changed'))->issue;

        return back()->with('undo', [
            'message' => "{$issue->key} moved to {$issue->column->name}",
            'method' => 'post',
            'url' => route('issues.move', $issue->key),
            'data' => [...$before, 'undo_of' => $this->undo->token($issue)],
        ]);
    }

    public function reject(Request $request, int $approval): RedirectResponse
    {
        $note = $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null;
        $this->approvals->reject($this->approvals->resolve($request->user(), $approval), $request->user(), $note);

        return back();
    }
}
