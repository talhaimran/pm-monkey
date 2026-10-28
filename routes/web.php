<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\BoardController;
use App\Http\Controllers\Web\InvitationController;
use App\Http\Controllers\Web\IssueController;
use App\Http\Controllers\Web\IssueListController;
use App\Http\Controllers\Web\LabelController;
use App\Http\Controllers\Web\ProjectController;
use App\Http\Controllers\Web\ProjectMemberController;
use App\Http\Controllers\Web\SprintController;
use App\Http\Controllers\Web\TokenController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', [IssueListController::class, 'dashboard'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/settings/tokens', [TokenController::class, 'index'])->name('tokens.index');
    Route::post('/settings/tokens', [TokenController::class, 'store'])->name('tokens.store');
    Route::delete('/settings/tokens/{token}', [TokenController::class, 'destroy'])->name('tokens.destroy');

    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/search', [IssueListController::class, 'search'])->name('search');
    Route::get('/issues/{key}', [IssueController::class, 'show'])->name('issues.show');
    Route::patch('/issues/{key}', [IssueController::class, 'update'])->name('issues.update');
    Route::delete('/issues/{key}', [IssueController::class, 'destroy'])->name('issues.destroy');
    Route::post('/issues/{key}/move', [BoardController::class, 'move'])->name('issues.move');
    Route::post('/issues/{key}/rank', [SprintController::class, 'rank'])->name('issues.rank');
    Route::post('/issues/{key}/comments', [IssueController::class, 'comment'])->name('issues.comments.store');
    Route::patch('/comments/{comment}', [IssueController::class, 'updateComment'])->name('comments.update');
    Route::delete('/comments/{comment}', [IssueController::class, 'destroyComment'])->name('comments.destroy');
    Route::post('/invitations/{invitation}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');

    Route::prefix('/projects/{project:key}')->name('projects.')->group(function () {
        Route::get('/', [BoardController::class, 'show'])->name('show');
        Route::post('/columns', [BoardController::class, 'storeColumn'])->name('columns.store');
        Route::patch('/columns/{column}', [BoardController::class, 'updateColumn'])->name('columns.update')->scopeBindings();
        Route::delete('/columns/{column}', [BoardController::class, 'destroyColumn'])->name('columns.destroy')->scopeBindings();
        Route::get('/backlog', [SprintController::class, 'backlog'])->name('backlog');
        Route::get('/issues', [IssueListController::class, 'index'])->name('issues.index');
        Route::post('/sprints', [SprintController::class, 'store'])->name('sprints.store');
        Route::patch('/sprints/{sprint}', [SprintController::class, 'update'])->name('sprints.update')->scopeBindings();
        Route::delete('/sprints/{sprint}', [SprintController::class, 'destroy'])->name('sprints.destroy')->scopeBindings();
        Route::post('/sprints/{sprint}/start', [SprintController::class, 'start'])->name('sprints.start')->scopeBindings();
        Route::post('/sprints/{sprint}/complete', [SprintController::class, 'complete'])->name('sprints.complete')->scopeBindings();
        Route::get('/settings', [ProjectController::class, 'settings'])->name('settings');
        Route::post('/issues', [IssueController::class, 'store'])->name('issues.store');
        Route::post('/labels', [LabelController::class, 'store'])->name('labels.store');
        Route::patch('/labels/{label}', [LabelController::class, 'update'])->name('labels.update')->scopeBindings();
        Route::delete('/labels/{label}', [LabelController::class, 'destroy'])->name('labels.destroy')->scopeBindings();
        Route::patch('/', [ProjectController::class, 'update'])->name('update');
        Route::delete('/', [ProjectController::class, 'destroy'])->name('destroy');
        Route::post('/invitations', [ProjectMemberController::class, 'invite'])->name('invitations.store')->middleware('throttle:invites');
        Route::delete('/invitations/{invitation}', [ProjectMemberController::class, 'revoke'])->name('invitations.destroy')->scopeBindings();
        Route::patch('/members/{user}', [ProjectMemberController::class, 'update'])->name('members.update');
        Route::delete('/members/{user}', [ProjectMemberController::class, 'destroy'])->name('members.destroy');
    });
});

require __DIR__.'/auth.php';
