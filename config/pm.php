<?php

return [
    // Requests per minute per API token (or per user/IP without a token). Applies to /api/v1 and the MCP web endpoint.
    'api_rate_limit' => (int) env('PM_API_RATE_LIMIT', 120),

    // Invitation emails per hour per user (web and API), so the server can't be used to spam.
    'invite_rate_limit' => (int) env('PM_INVITE_RATE_LIMIT', 30),

    // API token the stdio MCP server (`php artisan mcp:start pm`) acts as. Create one at /settings/tokens.
    'mcp_token' => env('PM_MCP_TOKEN'),

    // How long a replayable Idempotency-Key response is kept.
    'idempotency_ttl_hours' => (int) env('PM_IDEMPOTENCY_TTL_HOURS', 24),
];
