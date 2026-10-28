<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\InvitationController;
use App\Http\Controllers\Web\IssueController;
use App\Http\Controllers\Web\LabelController;
use App\Http\Controllers\Web\ProjectController;
use App\Http\Controllers\Web\ProjectMemberController;
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

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/issues/{key}', [IssueController::class, 'show'])->name('issues.show');
    Route::patch('/issues/{key}', [IssueController::class, 'update'])->name('issues.update');
    Route::delete('/issues/{key}', [IssueController::class, 'destroy'])->name('issues.destroy');
    Route::post('/issues/{key}/comments', [IssueController::class, 'comment'])->name('issues.comments.store');
    Route::patch('/comments/{comment}', [IssueController::class, 'updateComment'])->name('comments.update');
    Route::delete('/comments/{comment}', [IssueController::class, 'destroyComment'])->name('comments.destroy');
    Route::post('/invitations/{invitation}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');

    Route::prefix('/projects/{project:key}')->name('projects.')->group(function () {
        Route::get('/', [ProjectController::class, 'show'])->name('show');
        Route::get('/settings', [ProjectController::class, 'settings'])->name('settings');
        Route::post('/issues', [IssueController::class, 'store'])->name('issues.store');
        Route::post('/labels', [LabelController::class, 'store'])->name('labels.store');
        Route::patch('/labels/{label}', [LabelController::class, 'update'])->name('labels.update')->scopeBindings();
        Route::delete('/labels/{label}', [LabelController::class, 'destroy'])->name('labels.destroy')->scopeBindings();
        Route::patch('/', [ProjectController::class, 'update'])->name('update');
        Route::delete('/', [ProjectController::class, 'destroy'])->name('destroy');
        Route::post('/invitations', [ProjectMemberController::class, 'invite'])->name('invitations.store');
        Route::delete('/invitations/{invitation}', [ProjectMemberController::class, 'revoke'])->name('invitations.destroy')->scopeBindings();
        Route::patch('/members/{user}', [ProjectMemberController::class, 'update'])->name('members.update');
        Route::delete('/members/{user}', [ProjectMemberController::class, 'destroy'])->name('members.destroy');
    });
});

require __DIR__.'/auth.php';
