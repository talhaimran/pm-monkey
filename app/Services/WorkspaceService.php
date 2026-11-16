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
    /** Creates a workspace with $owner as its Owner; a user with no current workspace switches to it. */
    public function create(User $owner, string $name, ?string $slug = null): Workspace
    {
        $slug = $slug === null ? $this->slugFor($name) : strtolower(trim($slug));
        $this->ensureSlugAvailable($slug);

        return DB::transaction(function () use ($owner, $name, $slug) {
            $workspace = Workspace::create(['name' => trim($name), 'slug' => $slug]);
            $workspace->addMember($owner, WorkspaceRole::Owner);

            if (! $owner->current_workspace_id) {
                $owner->forceFill(['current_workspace_id' => $workspace->id])->save();
            }

            return $workspace;
        });
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

    /** The workspace a web request acts in: the user's current one if they still belong to it, else their first. */
    public function currentFor(User $user): ?Workspace
    {
        $workspace = $user->current_workspace_id ? $user->workspaces()->find($user->current_workspace_id) : null;
        $workspace ??= $user->workspaces()->orderBy('workspaces.id')->first();

        if ($workspace?->id !== $user->current_workspace_id) {
            $user->forceFill(['current_workspace_id' => $workspace?->id])->save();
        }

        return $workspace;
    }

    private function ensureSlugAvailable(string $slug): void
    {
        $error = match (true) {
            ! preg_match('/^[a-z0-9-]{3,40}$/', $slug) => 'The address must be 3–40 lowercase letters, digits or hyphens.',
            in_array($slug, Workspace::RESERVED_SLUGS, true) => "\"{$slug}\" is reserved. Choose another address.",
            $this->taken($slug) => "\"{$slug}\" is already taken. Choose another address.",
            default => null,
        };

        if ($error) {
            throw ValidationException::withMessages(['slug' => $error]);
        }
    }

    private function taken(string $slug): bool
    {
        return Workspace::where('slug', $slug)->exists()
            || DB::table('workspace_slug_redirects')->where('old_slug', $slug)->exists();
    }
}
