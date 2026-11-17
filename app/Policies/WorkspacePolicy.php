<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\Response;

/** Spec section 4: the workspace abilities step 0 covers. Later steps add theirs. */
class WorkspacePolicy
{
    public function openBilling(User $user, Workspace $workspace): Response
    {
        return $this->owner($user, $workspace);
    }

    public function delete(User $user, Workspace $workspace): Response
    {
        return $this->owner($user, $workspace);
    }

    public function manageOwners(User $user, Workspace $workspace): Response
    {
        return $this->owner($user, $workspace);
    }

    public function update(User $user, Workspace $workspace): Response
    {
        return $this->admin($user, $workspace);
    }

    public function manageMembers(User $user, Workspace $workspace): Response
    {
        return $this->admin($user, $workspace);
    }

    public function manageSecurity(User $user, Workspace $workspace): Response
    {
        return $this->admin($user, $workspace);
    }

    public function viewAuditLog(User $user, Workspace $workspace): Response
    {
        return $this->admin($user, $workspace);
    }

    public function viewAllProjects(User $user, Workspace $workspace): Response
    {
        return $this->admin($user, $workspace);
    }

    public function manageProjectCreation(User $user, Workspace $workspace): Response
    {
        return $this->admin($user, $workspace);
    }

    /** Members see their own usage only; the page narrows what it shows. */
    public function viewUsage(User $user, Workspace $workspace): Response
    {
        return $user->workspaceRole($workspace) ? Response::allow() : $this->notMember();
    }

    public function createProject(User $user, Workspace $workspace): Response
    {
        $role = $user->workspaceRole($workspace);

        return match (true) {
            ! $role => $this->notMember(),
            $role !== WorkspaceRole::Member, (bool) $workspace->setting('members_can_create_projects') => Response::allow(),
            default => Response::deny("Only Owners and Admins can create projects in {$workspace->name}."),
        };
    }

    private function owner(User $user, Workspace $workspace): Response
    {
        return match ($user->workspaceRole($workspace)) {
            WorkspaceRole::Owner => Response::allow(),
            null => $this->notMember(),
            default => Response::deny("Only Owners of {$workspace->name} can do this."),
        };
    }

    private function admin(User $user, Workspace $workspace): Response
    {
        return match ($user->workspaceRole($workspace)) {
            WorkspaceRole::Owner, WorkspaceRole::Admin => Response::allow(),
            null => $this->notMember(),
            default => Response::deny("Only Owners and Admins of {$workspace->name} can do this."),
        };
    }

    private function notMember(): Response
    {
        return Response::deny('You are not a member of this workspace.');
    }
}
