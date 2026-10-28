<?php

use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ProjectMemberController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('projects', [ProjectController::class, 'index']);
    Route::post('projects', [ProjectController::class, 'store']);
    Route::get('projects/{key}', [ProjectController::class, 'show']);
    Route::patch('projects/{key}', [ProjectController::class, 'update']);
    Route::delete('projects/{key}', [ProjectController::class, 'destroy']);

    Route::get('projects/{key}/members', [ProjectMemberController::class, 'index']);
    Route::post('projects/{key}/invitations', [ProjectMemberController::class, 'invite']);
    Route::patch('projects/{key}/members/{email}', [ProjectMemberController::class, 'update']);
    Route::delete('projects/{key}/members/{email}', [ProjectMemberController::class, 'destroy']);
});
