<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Soft-deleted issues and comments are removed for good after 30 days (spec 5.1, leaning).
Schedule::command('issues:purge')->daily();
