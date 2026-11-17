<?php

namespace App\Services;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WorkspaceService
{
    public const DELETION_DAYS = 7;

    public function __construct(private ProjectService $projects, private AuditLogger $audit, private Entitlements $entitlements) {}

    /** Creates a workspace with $owner as its Owner; a user with no current workspace switches to it. */
    public function create(User $owner, string $name, ?string $slug = null): Workspace
    {
        if (! $this->canCreate()) {
            throw ValidationException::withMessages(['name' => 'This install has one workspace. Ask an admin of '.Workspace::value('name').' to invite you.']);
        }
        $slug = blank($slug) ? $this->slugFor($name) : strtolower(trim($slug));
        $this->ensureSlugAvailable($slug);

        return DB::transaction(function () use ($owner, $name, $slug) {
            $this->claimRedirect($slug);
            $workspace = Workspace::create(['name' => trim($name), 'slug' => $slug]);
            $workspace->addMember($owner, WorkspaceRole::Owner);
            if (config('pm.edition') === 'cloud') {
                $this->entitlements->applyFreeDefaults($workspace);
            }
            $this->audit->log('workspace.created', $workspace, ['slug' => $slug], $workspace, $owner);

            if (! $owner->current_workspace_id) {
                $owner->forceFill(['current_workspace_id' => $workspace->id])->save();
            }

            return $workspace;
        });
    }

    /** Spec 7.0b: a new slug keeps the old one as a permanent redirect, until some workspace claims it. */
    public function rename(Workspace $workspace, string $name, string $slug): Workspace
    {
        $slug = strtolower(trim($slug));

        return DB::transaction(function () use ($workspace, $name, $slug) {
            if ($slug !== $workspace->slug) {
                $this->ensureSlugAvailable($slug);
                $this->claimRedirect($slug);
                DB::table('workspace_slug_redirects')->insert(['old_slug' => $workspace->slug, 'workspace_id' => $workspace->id, 'created_at' => now()]);
            }
            $before = $workspace->only('name', 'slug');
            $workspace->update(['name' => trim($name), 'slug' => $slug]);
            $this->logChanges('workspace.updated', $workspace, $before, $workspace->only('name', 'slug'));

            return $workspace;
        });
    }

    /** @param array<string, mixed> $changes settings keys to change; workspace.settings_changed keeps before and after of the changed ones. */
    public function updateSettings(Workspace $workspace, array $changes): void
    {
        $before = collect($changes)->mapWithKeys(fn ($v, $key) => [$key => $workspace->setting($key)])->all();
        $workspace->forceFill(['settings' => [...($workspace->settings ?? []), ...$changes]])->save();
        $this->logChanges('workspace.settings_changed', $workspace, $before, $changes);
    }

    /** D2: the Owner types the slug; members are locked out for 7 days, then workspaces:purge deletes it. */
    public function scheduleDeletion(Workspace $workspace, User $owner, string $typedSlug): void
    {
        if ($typedSlug !== $workspace->slug) {
            throw ValidationException::withMessages(['confirm_slug' => "Type {$workspace->slug} to confirm."]);
        }
        $workspace->forceFill(['deletion_scheduled_at' => now()->addDays(self::DELETION_DAYS)])->save();
        $this->audit->log('workspace.deletion_scheduled', $workspace, ['delete_after' => $workspace->deletion_scheduled_at->toIso8601String()], $workspace, $owner);
    }

    public function cancelDeletion(Workspace $workspace, User $owner): void
    {
        $workspace->forceFill(['deletion_scheduled_at' => null])->save();
        $this->audit->log('workspace.deletion_cancelled', $workspace, [], $workspace, $owner);
    }

    /**
     * Deletes the workspace for good: projects with their issues, then (by cascade) tokens, invitations, members,
     * redirects and its audit log. One unscoped workspace.deleted row stays.
     */
    public function purge(Workspace $workspace): void
    {
        DB::transaction(function () use ($workspace) {
            $workspace->projects()->each(fn ($project) => $this->projects->delete($project));
            $workspace->delete();
            $this->audit->system('workspace.deleted', $workspace, null, ['slug' => $workspace->slug]);
        });
    }

    /** What members see while it waits to be deleted; the Owner can undo it. */
    public function lockedMessage(Workspace $workspace, ?User $user): string
    {
        $when = "{$workspace->name} is scheduled for deletion on {$workspace->deletion_scheduled_at->format('j F Y')}.";

        return $user?->workspaceRole($workspace) === WorkspaceRole::Owner
            ? "{$when} You can undo this until then."
            : "{$when} Ask its Owner to undo this if it's a mistake.";
    }

    /**
     * Self-hosted allows exactly one workspace per install; Cloud allows many (spec 3).
     * shortcut: not locked, so two first sign-ups in the same instant could both create one; add a lock if that shows up.
     */
    public function canCreate(): bool
    {
        return config('pm.edition') === 'cloud' || ! Workspace::exists();
    }

    /** E2: a slug suggestion for $name that fits 3–40 characters, isn't reserved and isn't taken. */
    public function slugFor(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        if (strlen($base) < 3 || in_array($base, Workspace::RESERVED_SLUGS, true)) {
            $base .= '-workspace';
        }
        $base = trim(substr($base, 0, 40), '-');

        for ($slug = $base, $n = 2; $this->taken($slug); $n++) {
            $slug = trim(substr($base, 0, 40 - strlen("-{$n}")), '-')."-{$n}";
        }

        return $slug;
    }

    /**
     * The workspace a web request acts in: the user's current one if they still belong to it, else their first.
     * A workspace scheduled for deletion is skipped (D2).
     * With none left, current_workspace_id is kept so the no-access page can name the workspace they left.
     */
    public function currentFor(User $user): ?Workspace
    {
        $active = fn () => $user->workspaces()->whereNull('deletion_scheduled_at');
        $workspace = $user->current_workspace_id ? $active()->find($user->current_workspace_id) : null;
        $workspace ??= $active()->orderBy('workspaces.id')->first();

        if ($workspace && $workspace->id !== $user->current_workspace_id) {
            $user->forceFill(['current_workspace_id' => $workspace->id])->save();
        }

        return $workspace;
    }

    private function ensureSlugAvailable(string $slug): void
    {
        $error = match (true) {
            ! preg_match('/^[a-z0-9-]{3,40}$/', $slug) => 'The address must be 3–40 lowercase letters, digits or hyphens.',
            in_array($slug, Workspace::RESERVED_SLUGS, true) => "\"{$slug}\" is reserved. Choose another address.",
            Workspace::where('slug', $slug)->exists() => "\"{$slug}\" is already taken. Choose another address.",
            default => null,
        };

        if ($error) {
            throw ValidationException::withMessages(['slug' => $error]);
        }
    }

    private function logChanges(string $action, Workspace $workspace, array $before, array $after): void
    {
        $changed = array_keys(array_filter($after, fn ($v, $key) => $before[$key] !== $v, ARRAY_FILTER_USE_BOTH));
        if ($changed) {
            $this->audit->log($action, $workspace, ['before' => array_intersect_key($before, array_flip($changed)), 'after' => array_intersect_key($after, array_flip($changed))], $workspace);
        }
    }

    /** Another workspace's old slug may be claimed (spec 7.0b), which ends that redirect. */
    private function claimRedirect(string $slug): void
    {
        DB::table('workspace_slug_redirects')->where('old_slug', $slug)->delete();
    }

    /** Suggestions avoid old slugs too, so a redirect isn't ended by accident. */
    private function taken(string $slug): bool
    {
        return Workspace::where('slug', $slug)->exists()
            || DB::table('workspace_slug_redirects')->where('old_slug', $slug)->exists();
    }
}
