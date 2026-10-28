<?php

use App\Mcp\Servers\LocalPmServer;
use App\Mcp\Servers\PmServer;
use Laravel\Mcp\Facades\Mcp;

// HTTP transport: same Sanctum tokens, scopes and rate limit as the REST API.
Mcp::web('/mcp', PmServer::class)->middleware(['auth:sanctum', 'throttle:api'])->name('mcp');

// stdio transport: `php artisan mcp:start pm`, authenticated by the PM_MCP_TOKEN environment variable.
Mcp::local('pm', LocalPmServer::class);
