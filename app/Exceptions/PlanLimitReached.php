<?php

namespace App\Exceptions;

use App\Enums\Entitlement;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Spec 7.0c: an action would go past a plan limit. An AuthorizationException, so MCP tools return its message as a
 * tool error. render() runs before Laravel turns authorization errors into a plain 403.
 */
class PlanLimitReached extends AuthorizationException
{
    public function __construct(public readonly Entitlement $limit, public readonly int $allowed, public readonly int $current)
    {
        parent::__construct("Your plan allows {$limit->count($allowed)}. Upgrade to add more.");
    }

    /**
     * API: the plan_limit 403. Web: back, with the data the plan-limit dialog shows, and as a form error so the
     * form keeps what was typed (Inertia only resets forms on success).
     */
    public function render(Request $request): Response
    {
        return $request->is('api/*') || $request->expectsJson()
            ? response()->json($this->toArray(), 403)
            : back()->with('planLimit', $this->toArray())->withErrors(['plan_limit' => $this->getMessage()]);
    }

    public function toArray(): array
    {
        return [
            'error' => 'plan_limit',
            'limit' => $this->limit->value,
            'allowed' => $this->allowed,
            'current' => $this->current,
            'message' => $this->getMessage(),
            'upgrade_url' => config('pm.billing_url') ?: null,
        ];
    }
}
