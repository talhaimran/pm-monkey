<?php

use App\Http\Controllers\Api\V1\BacklogController;
use App\Http\Controllers\Api\V1\BoardController;
use App\Http\Controllers\Api\V1\ColumnController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\IssueController;
use App\Http\Controllers\Api\V1\IssueListController;
use App\Http\Controllers\Api\V1\LabelController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ProjectMemberController;
use App\Http\Controllers\Api\V1\SprintController;
use App\Http\Middleware\Idempotent;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:api', Idempotent::class])->group(function () {
    Route::get('projects', [ProjectController::class, 'index']);
    Route::post('projects', [ProjectController::class, 'store']);
    Route::get('projects/{key}', [ProjectController::class, 'show']);
    Route::patch('projects/{key}', [ProjectController::class, 'update']);
    Route::delete('projects/{key}', [ProjectController::class, 'destroy']);

    Route::get('projects/{key}/members', [ProjectMemberController::class, 'index']);
    Route::post('projects/{key}/invitations', [ProjectMemberController::class, 'invite'])->middleware('throttle:invites');
    Route::patch('projects/{key}/members/{email}', [ProjectMemberController::class, 'update']);
    Route::delete('projects/{key}/members/{email}', [ProjectMemberController::class, 'destroy']);

    Route::get('projects/{key}/labels', [LabelController::class, 'index']);
    Route::post('projects/{key}/labels', [LabelController::class, 'store']);
    Route::patch('projects/{key}/labels/{name}', [LabelController::class, 'update']);
    Route::delete('projects/{key}/labels/{name}', [LabelController::class, 'destroy']);

    Route::get('projects/{key}/board', [BoardController::class, 'show']);
    Route::get('projects/{key}/columns', [ColumnController::class, 'index']);
    Route::post('projects/{key}/columns', [ColumnController::class, 'store']);
    Route::patch('projects/{key}/columns/{name}', [ColumnController::class, 'update']);
    Route::delete('projects/{key}/columns/{name}', [ColumnController::class, 'destroy']);

    Route::get('projects/{key}/backlog', [BacklogController::class, 'show']);
    Route::get('projects/{key}/sprints', [SprintController::class, 'index']);
    Route::post('projects/{key}/sprints', [SprintController::class, 'store']);
    Route::get('projects/{key}/sprints/{sprint}', [SprintController::class, 'show']);
    Route::patch('projects/{key}/sprints/{sprint}', [SprintController::class, 'update']);
    Route::delete('projects/{key}/sprints/{sprint}', [SprintController::class, 'destroy']);
    Route::post('projects/{key}/sprints/{sprint}/start', [SprintController::class, 'start']);
    Route::post('projects/{key}/sprints/{sprint}/complete', [SprintController::class, 'complete']);

    Route::get('projects/{key}/issues', [IssueListController::class, 'index']);
    Route::get('projects/{key}/summary', [IssueListController::class, 'summary']);
    Route::get('issues', [IssueListController::class, 'search']);
    Route::get('me/issues', [IssueListController::class, 'mine']);

    Route::post('projects/{key}/issues', [IssueController::class, 'store']);
    Route::post('projects/{key}/issues/bulk', [IssueController::class, 'bulkStore']);
    // Before issues/{key} so "bulk" isn't read as an issue key.
    Route::patch('issues/bulk', [IssueController::class, 'bulkUpdate']);
    Route::get('issues/{key}', [IssueController::class, 'show']);
    Route::patch('issues/{key}', [IssueController::class, 'update']);
    Route::delete('issues/{key}', [IssueController::class, 'destroy']);
    Route::get('issues/{key}/activity', [IssueController::class, 'activity']);
    Route::post('issues/{key}/move', [BoardController::class, 'move']);
    Route::post('issues/{key}/rank', [BacklogController::class, 'rank']);

    Route::post('issues/{key}/comments', [CommentController::class, 'store']);
    Route::patch('comments/{comment}', [CommentController::class, 'update']);
    Route::delete('comments/{comment}', [CommentController::class, 'destroy']);
});
