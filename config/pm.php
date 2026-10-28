<?php

return [
    // Requests per minute per API token (or per user/IP without a token). Applies to /api/v1 and the MCP web endpoint.
    'api_rate_limit' => (int) env('PM_API_RATE_LIMIT', 120),

    // How long a replayable Idempotency-Key response is kept.
    'idempotency_ttl_hours' => (int) env('PM_IDEMPOTENCY_TTL_HOURS', 24),
];
