<?php

namespace App\Services;

use App\Models\Workspace;
use LogicException;

/**
 * The workspace this request acts in (spec 5): from the user's current workspace on the web, from the token on
 * API and MCP. Registered as a scoped singleton, so it resets between requests and queued jobs.
 */
final class CurrentWorkspace
{
    private ?Workspace $workspace = null;

    public function set(Workspace $workspace): void
    {
        $this->workspace = $workspace;
    }

    public function get(): Workspace
    {
        return $this->workspace ?? throw new LogicException('No current workspace is set for this request.');
    }

    public function id(): ?int
    {
        return $this->workspace?->id;
    }

    public function forget(): void
    {
        $this->workspace = null;
    }
}
