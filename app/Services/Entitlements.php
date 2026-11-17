<?php

namespace App\Services;

use App\Enums\Entitlement;
use App\Exceptions\PlanLimitReached;
use App\Models\Workspace;
use Illuminate\Validation\ValidationException;

/** Spec 7.0c: per-workspace limits. No row means unlimited; gates use these, never the edition. */
class Entitlements
{
    public function __construct(private WorkspaceUsage $usage, private AuditLogger $audit) {}

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
     * accepted after the limit dropped). Logs plan_limit.reached.
     *
     * shortcut: checked before the write's transaction, so two simultaneous adds can both pass; lock the workspace row if that shows up.
     */
    public function ensure(Workspace $workspace, Entitlement $key, int $adding = 1): void
    {
        $allowed = $this->get($workspace, $key);
        if ($allowed === null) {
            return;
        }
        $current = $this->usage->current($workspace, $key);
        if ($current + $adding > $allowed) {
            $this->audit->log('plan_limit.reached', null, ['limit' => $key->value, 'allowed' => $allowed, 'current' => $current], $workspace);

            throw new PlanLimitReached($key, $allowed, $current);
        }
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
