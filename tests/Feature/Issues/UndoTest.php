<?php

use App\Enums\ProjectRole;
use App\Models\Activity;
use App\Models\Comment;
use App\Models\Issue;
use App\Models\User;
use App\Services\ColumnService;
use App\Services\CommentService;
use App\Services\IssueService;
use App\Services\LabelService;

beforeEach(function () {
    $this->admin = User::factory()->create(['name' => 'Ada Admin', 'email' => 'admin@example.com']);
    $this->sam = User::factory()->create(['name' => 'Sam Member', 'email' => 'sam@example.com']);
    $this->project = makeProject($this->admin, 'WEB');
    addMember($this->project, $this->sam, ProjectRole::Member);
    $this->actingAs($this->admin);
});

/** Send the flashed undo back, the way the toast does. */
function sendUndo($test)
{
    $undo = session('undo');
    expect($undo)->toBeArray()->toHaveKeys(['message', 'method', 'url', 'data']);

    return $test->{$undo['method']}($undo['url'], $undo['data']);
}

$order = fn (string $column) => Issue::whereHas('column', fn ($q) => $q->where('name', $column))->orderBy('rank')->pluck('number')->all();

test('undo of a board move returns the issue to its column and position', function () use ($order) {
    foreach (range(1, 3) as $i) {
        makeIssue($this->project, $this->admin);
    }

    $this->post('/issues/WEB-2/move', ['status' => 'Done', 'position' => 0])->assertRedirect();
    expect(session('undo.message'))->toBe('WEB-2 moved to Done');
    expect($order('To Do'))->toBe([1, 3]);

    sendUndo($this)->assertSessionHasNoErrors();

    expect($order('To Do'))->toBe([1, 2, 3])
        ->and(session('undo'))->toBeNull()
        ->and(Activity::latest('id')->first())->action->toBe('undid')->changes->toEqual(['status' => ['from' => 'Done', 'to' => 'To Do']]);
});

test('undo of a reorder inside a column puts the issue back first', function () use ($order) {
    foreach (range(1, 3) as $i) {
        makeIssue($this->project, $this->admin);
    }

    $this->post('/issues/WEB-1/move', ['after' => 'WEB-3'])->assertRedirect();
    expect($order('To Do'))->toBe([2, 3, 1]);

    sendUndo($this)->assertSessionHasNoErrors();
    expect($order('To Do'))->toBe([1, 2, 3]);
});

test('undo of a status change from the issue page', function () {
    makeIssue($this->project, $this->admin);

    $this->patch('/issues/WEB-1', ['status' => 'In Review'])->assertRedirect();
    expect(session('undo.message'))->toBe('WEB-1 moved to In Review');

    sendUndo($this)->assertSessionHasNoErrors();
    expect(Issue::sole()->column->name)->toBe('To Do');
});

test('undo of field edits restores the exact previous values', function () {
    $long = trim(str_repeat('Long description. ', 30)); // HTTP input is trimmed
    app(LabelService::class)->create($this->project, ['name' => 'ux', 'color' => '#111111']);
    makeIssue($this->project, $this->admin, ['title' => 'Before', 'description' => $long, 'assignee' => 'sam@example.com', 'labels' => ['ux'], 'story_points' => 3]);

    $this->patch('/issues/WEB-1', ['title' => 'After', 'description' => 'Short', 'assignee' => null, 'labels' => [], 'story_points' => 8])->assertRedirect();
    expect(session('undo.message'))->toBe('WEB-1 updated');

    sendUndo($this)->assertSessionHasNoErrors();

    $issue = Issue::with('assignee', 'labels')->sole();
    expect($issue->title)->toBe('Before')
        ->and($issue->description)->toBe($long)
        ->and($issue->assignee->email)->toBe('sam@example.com')
        ->and($issue->labels->pluck('name')->all())->toBe(['ux'])
        ->and($issue->story_points)->toBe(3);
});

test('an update that changes nothing offers no undo', function () {
    makeIssue($this->project, $this->admin, ['title' => 'Same']);

    $this->patch('/issues/WEB-1', ['title' => 'Same'])->assertRedirect();

    expect(session('undo'))->toBeNull();
});

test('undo of a delete restores the issue', function () {
    makeIssue($this->project, $this->admin);

    $this->delete('/issues/WEB-1', ['confirm' => true])->assertRedirect();
    expect(session('undo.message'))->toBe('WEB-1 deleted')->and(Issue::count())->toBe(0);

    sendUndo($this)->assertSessionHasNoErrors();
    expect(Issue::count())->toBe(1);
});

test('undo of a comment delete restores the comment', function () {
    $comment = app(CommentService::class)->add(makeIssue($this->project, $this->admin), $this->admin, 'Hello');

    $this->delete("/comments/{$comment->id}")->assertRedirect();
    expect(session('undo.message'))->toBe('Comment deleted')->and(Comment::count())->toBe(0);

    sendUndo($this)->assertSessionHasNoErrors();
    expect(Comment::count())->toBe(1);
});

test('undo stops with a message when someone else changed the issue meanwhile', function () {
    $issue = makeIssue($this->project, $this->admin);
    $this->post('/issues/WEB-1/move', ['status' => 'In Progress'])->assertRedirect();
    $undo = session('undo');

    app(IssueService::class)->update($issue->fresh(), $this->sam, ['status' => 'Done']);

    $this->post($undo['url'], $undo['data'])->assertSessionHasErrors(['undo' => "Can't undo: Sam Member moved WEB-1 to Done."]);
    expect(Issue::sole()->column->name)->toBe('Done');
});

test('a comment by someone else does not block undo', function () {
    $issue = makeIssue($this->project, $this->admin);
    $this->patch('/issues/WEB-1', ['title' => 'Renamed'])->assertRedirect();
    $undo = session('undo');

    app(CommentService::class)->add($issue, $this->sam, 'Looks good');

    $this->patch($undo['url'], $undo['data'])->assertSessionHasNoErrors();
    expect(Issue::sole()->title)->toBe('Issue');
});

test('undo into a column that was deleted meanwhile fails with the valid options', function () {
    makeIssue($this->project, $this->admin, ['status' => 'In Review']);
    $this->post('/issues/WEB-1/move', ['status' => 'Done'])->assertRedirect();
    $undo = session('undo');

    app(ColumnService::class)->delete($this->project->columns()->where('name', 'In Review')->first(), null);

    $this->post($undo['url'], $undo['data'])->assertSessionHasErrors('status');
    expect(session('errors')->first('status'))->toContain('To Do, In Progress, Done');
    expect(Issue::sole()->column->name)->toBe('Done');
});

test('the same undo cannot run twice', function () {
    makeIssue($this->project, $this->admin);
    $this->patch('/issues/WEB-1', ['title' => 'Renamed'])->assertRedirect();
    $undo = session('undo');

    $this->patch($undo['url'], $undo['data'])->assertSessionHasNoErrors();
    $this->patch($undo['url'], $undo['data'])->assertSessionHasErrors('undo');
    expect(Activity::where('action', 'undid')->count())->toBe(1);
});

test('undo_of must be an activity of that issue', function () {
    makeIssue($this->project, $this->admin);
    makeIssue($this->project, $this->admin);
    $this->patch('/issues/WEB-2', ['title' => 'Other'])->assertRedirect();
    $other = session('undo.data.undo_of');

    $this->patch('/issues/WEB-1', ['title' => 'Sneaky', 'undo_of' => $other])->assertSessionHasErrors('undo');
    $this->patch('/issues/WEB-1', ['title' => 'Sneaky', 'undo_of' => 'abc'])->assertSessionHasErrors('undo_of');
    expect(Issue::where('number', 1)->sole()->title)->toBe('Issue');
});

test('the API input shape is unchanged: undo_of is ignored there', function () {
    makeIssue($this->project, $this->admin);
    apiAs($this->admin);

    $this->patchJson('/api/v1/issues/WEB-1', ['title' => 'Via API', 'undo_of' => 999])->assertOk();

    expect(Activity::latest('id')->first()->action)->toBe('updated');
});

test('a viewer cannot use an undo payload', function () {
    makeIssue($this->project, $this->admin);
    $this->patch('/issues/WEB-1', ['title' => 'Renamed'])->assertRedirect();
    $undo = session('undo');
    $viewer = addMember($this->project, User::factory()->create(), ProjectRole::Viewer);

    $this->actingAs($viewer)->patch($undo['url'], $undo['data'])->assertForbidden();
    expect(Issue::sole()->title)->toBe('Renamed');
});
