<?php

use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\IssueController;
use App\Http\Controllers\Api\V1\LabelController;
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

    Route::get('projects/{key}/labels', [LabelController::class, 'index']);
    Route::post('projects/{key}/labels', [LabelController::class, 'store']);
    Route::patch('projects/{key}/labels/{name}', [LabelController::class, 'update']);
    Route::delete('projects/{key}/labels/{name}', [LabelController::class, 'destroy']);

    Route::post('projects/{key}/issues', [IssueController::class, 'store']);
    Route::get('issues/{key}', [IssueController::class, 'show']);
    Route::patch('issues/{key}', [IssueController::class, 'update']);
    Route::delete('issues/{key}', [IssueController::class, 'destroy']);
    Route::get('issues/{key}/activity', [IssueController::class, 'activity']);

    Route::post('issues/{key}/comments', [CommentController::class, 'store']);
    Route::patch('comments/{comment}', [CommentController::class, 'update']);
    Route::delete('comments/{comment}', [CommentController::class, 'destroy']);
});
