<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\AuditLogFilterRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AuditLogger;
use App\Services\CurrentWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** /{workspace}/settings/audit-log (spec 7.0d): Owners and Admins only. */
class AuditLogController extends Controller
{
    /** E9: the export stops here and says so. */
    public const EXPORT_CAP = 10_000;

    public function __construct(private CurrentWorkspace $current) {}

    public function index(AuditLogFilterRequest $request): Response
    {
        $workspace = $this->current->get();
        Gate::authorize('viewAuditLog', $workspace);
        $filters = $request->validated();

        return Inertia::render('Workspaces/Settings/AuditLog', [
            'entries' => AuditLogResource::collection($this->query($workspace, $filters)->orderByDesc('id')->paginate(50)->withQueryString())->response()->getData(true),
            'filters' => (object) array_filter($filters),
            // Current members plus anyone with entries here, so former members' history stays filterable.
            'actors' => User::whereIn('id', $workspace->members()->select('users.id'))
                ->orWhereIn('id', AuditLog::where('workspace_id', $workspace->id)->whereNotNull('actor_user_id')->select('actor_user_id'))
                ->orderBy('name')->get(['id', 'name'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->all(),
            'groups' => AuditLog::where('workspace_id', $workspace->id)->selectRaw("distinct substring_index(action, '.', 1) as g")->orderBy('g')->pluck('g')->all(),
        ]);
    }

    public function export(AuditLogFilterRequest $request, AuditLogger $audit): StreamedResponse
    {
        $workspace = $this->current->get();
        Gate::authorize('viewAuditLog', $workspace);
        $filters = array_filter($request->validated());
        $total = $this->query($workspace, $filters)->count();
        $audit->log('audit_log.exported', null, ['filters' => $filters, 'rows' => min($total, self::EXPORT_CAP)], $workspace);

        $rows = $this->query($workspace, $filters)->lazyByIdDesc(1000, 'audit_logs.id', 'id')->take(self::EXPORT_CAP);

        return response()->streamDownload(function () use ($rows, $total) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['When', 'Actor', 'Action', 'Target', 'Via', 'IP'], escape: '');
            foreach ($rows as $row) {
                fputcsv($out, array_map($this->cell(...), [
                    $row->created_at->toIso8601String(), $row->actor?->name ?? ($row->actor_type === 'system' ? 'System' : ''),
                    $row->action, $row->target_label, $row->via, $row->ip,
                ]), escape: '');
            }
            if ($total > self::EXPORT_CAP) {
                fputcsv($out, ['Cut off at 10,000 rows. Narrow the filter to export the rest.'], escape: '');
            }
            fclose($out);
        }, "audit-log-{$workspace->slug}-".now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** This workspace's rows only; callers order them newest first. */
    private function query(Workspace $workspace, array $filters): Builder
    {
        return AuditLog::with(['actor:id,name', 'agent.owner:id,name'])
            ->where('workspace_id', $workspace->id)
            ->when($filters['actor'] ?? null, fn ($q, $id) => $q->where('actor_user_id', $id))
            ->when($filters['group'] ?? null, fn ($q, $g) => $q->where('action', 'like', "{$g}.%"))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', now()->parse($d)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', now()->parse($d)->endOfDay()));
    }

    /** Names, labels and emails are user input: a leading =, +, - or @ would run as a spreadsheet formula. */
    private function cell(?string $value): string
    {
        return $value !== null && preg_match('/^[=+\-@\t\r]/', $value) ? "'{$value}" : (string) $value;
    }
}
