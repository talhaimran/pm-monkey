<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Soft-deleted issues and comments are removed for good after 30 days (spec 5.1, leaning).
Schedule::command('issues:purge')->daily();

// D2: workspaces scheduled for deletion go for good after 7 days.
Schedule::command('workspaces:purge')->daily();

// A2: approval requests expire after 7 days; the agent can ask again.
Schedule::command('approvals:expire')->hourly();
