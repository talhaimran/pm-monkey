<?php

namespace App\Services;

use App\Enums\Entitlement;
use App\Exceptions\PlanLimitReached;
use App\Models\Workspace;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Spec 7.0c: per-workspace limits. No row means unlimited; gates use these, never the edition. */
class Entitlements
{
    /** Inside locked(), a refusal is logged after the rollback so the audit row survives. */
    private int $locking = 0;

    public function __construct(private WorkspaceUsage $usage, private AuditLogger $audit) {}

    /**
     * Runs $write in a transaction holding the workspace row, so the limit checks it makes and the write they guard
     * can't interleave with another request's (two simultaneous adds both passing).
     */
    public function locked(Workspace $workspace, Closure $write): mixed
    {
        $this->locking++;
        try {
            return DB::transaction(function () use ($workspace, $write) {
                Workspace::whereKey($workspace->id)->lockForUpdate()->first();

                return $write();
            });
        } catch (PlanLimitReached $e) {
            $this->logReached($workspace, $e);

            throw $e;
        } finally {
            $this->locking--;
        }
    }

    /** The cast value, or null when the workspace has no row (unlimited / enabled). */
    public function get(Workspace $workspace, Entitlement $key): int|bool|string|null
    {
        $value = $workspace->entitlements()->where('key', $key->value)->value('value');

        return match (true) {
            $value === null => null,
            $key->type() === 'int' => (int) $value,
            $key->type() === 'bool' => $value === '1',
            default => $value,
        };
    }

    /**
     * Refuses an action that would take $key past its limit; $adding = 0 refuses only when already over (an invitation
     * accepted after the limit dropped). Logs plan_limit.reached. Call it inside locked() with the write it guards.
     */
    public function ensure(Workspace $workspace, Entitlement $key, int $adding = 1): void
    {
        // Only count when there is a limit: counting people takes three queries.
        if ($this->get($workspace, $key) !== null) {
            $this->ensureCount($workspace, $key, $this->usage->current($workspace, $key), $adding);
        }
    }

    /** ensure() for a count the caller makes, like a person's agents (agents_per_user). */
    public function ensureCount(Workspace $workspace, Entitlement $key, int $current, int $adding = 1): void
    {
        $allowed = $this->get($workspace, $key);
        if ($allowed === null) {
            return;
        }
        if ($current + $adding > $allowed) {
            $e = new PlanLimitReached($key, $allowed, $current);
            if (! $this->locking) {
                $this->logReached($workspace, $e);
            }

            throw $e;
        }
    }

    private function logReached(Workspace $workspace, PlanLimitReached $e): void
    {
        $this->audit->log('plan_limit.reached', null, ['limit' => $e->limit->value, 'allowed' => $e->allowed, 'current' => $e->current], $workspace);
    }

    /** A person moving from $now (billed, Viewer, or not counted: null) to billed or Viewer needs room there. */
    public function ensurePerson(Workspace $workspace, ?bool $now, bool $billed): void
    {
        if ($billed && $now !== true) {
            $this->ensure($workspace, Entitlement::BilledUsers);
        } elseif (! $billed && $now === null) {
            $this->ensure($workspace, Entitlement::Viewers);
        }
    }

    /** Stores a value (validated for the key's type) and logs entitlement.changed when it changes. */
    public function set(Workspace $workspace, Entitlement $key, string $value, string $source): void
    {
        [$normalized, $error] = $key->normalize($value);
        if ($error) {
            throw ValidationException::withMessages(['value' => $error]);
        }
        $before = $workspace->entitlements()->where('key', $key->value)->value('value');
        $workspace->entitlements()->updateOrCreate(['key' => $key->value], ['value' => $normalized, 'source' => $source]);
        if ($before !== $normalized) {
            $this->audit->system('entitlement.changed', null, $workspace, ['key' => $key->value, 'before' => $before, 'after' => $normalized, 'source' => $source]);
        }
    }

    public function unset(Workspace $workspace, Entitlement $key): void
    {
        $before = $workspace->entitlements()->where('key', $key->value)->value('value');
        if ($before !== null) {
            $workspace->entitlements()->where('key', $key->value)->delete();
            $this->audit->system('entitlement.changed', null, $workspace, ['key' => $key->value, 'before' => $before, 'after' => null, 'source' => null]);
        }
    }

    /** D6: a new Cloud workspace starts with the Free values. */
    public function applyFreeDefaults(Workspace $workspace): void
    {
        $workspace->entitlements()->createMany(collect(Entitlement::cases())
            ->filter(fn (Entitlement $key) => $key->free() !== null)
            ->map(fn (Entitlement $key) => ['key' => $key->value, 'value' => $key->free(), 'source' => 'default'])
            ->values()->all());
    }
}
