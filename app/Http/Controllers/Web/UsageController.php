<?php

namespace App\Http\Controllers\Web;

use App\Enums\Entitlement;
use App\Http\Controllers\Controller;
use App\Services\CurrentWorkspace;
use App\Services\Entitlements;
use App\Services\WorkspaceUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** /{workspace}/settings/usage (spec 7.0c): usage against each enforced limit; Members see only their own (API limits). */
class UsageController extends Controller
{
    public function __invoke(Request $request, CurrentWorkspace $current, WorkspaceUsage $usage, Entitlements $entitlements): Response
    {
        $workspace = $current->get();
        Gate::authorize('viewUsage', $workspace);
        $labels = [Entitlement::BilledUsers->value => 'Billed users', Entitlement::Viewers->value => 'Viewers', Entitlement::Projects->value => 'Projects'];

        return Inertia::render('Workspaces/Settings/Usage', [
            'workspaceLimits' => $request->user()->can('manageMembers', $workspace)
                ? array_map(fn ($key) => [
                    'key' => $key, 'label' => $labels[$key],
                    'current' => $usage->current($workspace, Entitlement::from($key)),
                    'allowed' => $entitlements->get($workspace, Entitlement::from($key)),
                ], array_keys($labels))
                : null,
            'tokenLimits' => [
                'per_minute' => $entitlements->get($workspace, Entitlement::ApiPerMinute) ?? (int) config('pm.api_rate_limit'),
                'per_day' => $entitlements->get($workspace, Entitlement::ApiPerDay),
            ],
        ]);
    }
}
