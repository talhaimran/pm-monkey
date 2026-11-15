<?php

use App\Enums\ProjectRole;
use App\Models\Activity;
use App\Models\Comment;
use App\Models\Issue;
use App\Models\User;
use App\Services\CommentService;
use App\Services\IssueService;
use App\Services\TokenService;
use Inertia\Testing\AssertableInertia as Assert;

// Security review of step U (soft delete, Undo, peek drawer). The claude-security plugin scan was unavailable,
// so these tests are the manual review's probes; each one states the attack it rules out.

beforeEach(function () {
    $this->admin = User::factory()->create(['name' => 'Ada Admin']);
    $this->member = User::factory()->create(['name' => 'Mel Member']);
    $this->viewer = User::factory()->create();
    $this->outsider = User::factory()->create();
    $this->project = makeProject($this->admin, 'WEB');
    addMember($this->project, $this->member, ProjectRole::Member);
    addMember($this->project, $this->viewer, ProjectRole::Viewer);
    $this->other = makeProject($this->outsider, 'OPS');
    $this->issues = app(IssueService::class);
    $this->comments = app(CommentService::class);
});

test('peek renders Markdown without script or javascript: links', function () {
    makeIssue($this->project, $this->admin, ['description' => "<script>alert(1)</script>\n\n<img src=x onerror=alert(1)>\n\n[click](javascript:alert(1))"]);

    $this->actingAs($this->admin)->get('/projects/WEB?issue=WEB-1')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('peek.issue.description_html', fn (string $html) => ! str_contains($html, '<script') && ! str_contains($html, 'onerror') && ! str_contains($html, 'javascript:')));
});

test('peek never crosses projects, whatever shape ?issue= takes', function () {
    makeIssue($this->other, $this->outsider, ['title' => 'Secret plan']);

    foreach (['issue=OPS-1', 'issue[]=OPS-1', 'issue[key]=OPS-1', 'issue=ops-1', 'issue=OPS-1%00'] as $query) {
        $this->actingAs($this->member)->get("/projects/WEB?{$query}")->assertOk()->assertDontSee('Secret plan')
            ->assertInertia(fn (Assert $page) => $page->where('peek', null));
    }
});

test('an undo payload is useless to someone who may not write, and cannot reach another project', function () {
    makeIssue($this->project, $this->admin);
    makeIssue($this->other, $this->outsider, ['title' => 'Untouched']);
    $this->actingAs($this->admin)->post('/issues/WEB-1/rank', ['position' => 0])->assertRedirect();
    $undo = session('undo');

    // A viewer replaying it is refused by the policy before the undo guard runs.
    $this->actingAs($this->viewer)->post($undo['url'], $undo['data'])->assertForbidden();
    $this->actingAs($this->viewer)->post('/issues/WEB-1/move', $undo['data'])->assertForbidden();
    // An outsider gets the ordinary not-found error, and pointing the token at their own issue does nothing.
    $this->actingAs($this->outsider)->post($undo['url'], $undo['data'])->assertSessionHasErrors('project');
    $this->actingAs($this->outsider)->patch('/issues/OPS-1', ['title' => 'Hijacked', 'undo_of' => $undo['data']['undo_of']])->assertSessionHasErrors('undo');
    expect(Issue::where('title', 'Untouched')->exists())->toBeTrue()
        ->and(Activity::where('action', 'undid')->count())->toBe(0);
});

test('the flashed undo only ever points at this app\'s own issue and comment routes', function () {
    $issue = makeIssue($this->project, $this->admin, ['title' => 'https://evil.example/x']);
    $comment = $this->comments->add($issue, $this->admin, 'c');
    $this->actingAs($this->admin);
    $base = url('/');

    $this->patch('/issues/WEB-1', ['title' => '//evil.example'])->assertRedirect();
    expect(session('undo.url'))->toBe("{$base}/issues/WEB-1")->and(session('undo.method'))->toBe('patch');
    $this->delete("/comments/{$comment->id}")->assertRedirect();
    expect(session('undo.url'))->toBe("{$base}/comments/{$comment->id}/restore")->and(session('undo.method'))->toBe('post');
    $this->delete('/issues/WEB-1', ['confirm' => true])->assertRedirect();
    expect(session('undo.url'))->toBe("{$base}/issues/WEB-1/restore")->and(session('undo.data'))->toBe([]);
});

test('only the author or a project admin can restore a deleted comment', function () {
    $comment = $this->comments->add(makeIssue($this->project, $this->admin), $this->admin, 'Admin note');
    $this->comments->delete($comment);

    $this->actingAs($this->member)->post("/comments/{$comment->id}/restore")->assertForbidden();
    $this->actingAs($this->viewer)->post("/comments/{$comment->id}/restore")->assertForbidden();
    expect(Comment::count())->toBe(0);
});

test('comments of a deleted issue cannot be read, edited, deleted or restored', function () {
    $issue = makeIssue($this->project, $this->admin);
    $comment = $this->comments->add($issue, $this->admin, 'Buried');
    $this->issues->delete($issue, $this->admin);

    $this->actingAs($this->admin)->post("/comments/{$comment->id}/restore")->assertSessionHasErrors('comment');
    $this->patch("/comments/{$comment->id}", ['body' => 'Edited'])->assertSessionHasErrors('comment');
    apiAs($this->admin);
    $this->patchJson("/api/v1/comments/{$comment->id}", ['body' => 'Edited'])->assertNotFound();
    $this->deleteJson("/api/v1/comments/{$comment->id}")->assertNotFound();
    $this->getJson('/api/v1/issues/WEB-1/activity')->assertNotFound();
    expect(Comment::withTrashed()->sole()->body)->toBe('Buried');
});

test('a deleted issue cannot be written to through any API or web route', function () {
    $this->issues->delete(makeIssue($this->project, $this->admin), $this->admin);
    apiAs($this->admin);

    $this->patchJson('/api/v1/issues/WEB-1', ['title' => 'Zombie'])->assertNotFound();
    $this->postJson('/api/v1/issues/WEB-1/move', ['status' => 'Done'])->assertNotFound();
    $this->postJson('/api/v1/issues/WEB-1/rank', ['position' => 0])->assertNotFound();
    $this->postJson('/api/v1/issues/WEB-1/comments', ['body' => 'Hi'])->assertNotFound();
    $this->patchJson('/api/v1/issues/bulk', ['issues' => [['key' => 'WEB-1', 'title' => 'Zombie']]])->assertStatus(422);
    $this->postJson('/api/v1/projects/WEB/issues', ['title' => 'Child', 'parent' => 'WEB-1'])->assertStatus(422);
    expect(Issue::withTrashed()->where('number', 1)->sole()->title)->toBe('Issue');
});

test('a token scoped to another project cannot delete, restore-by-recreate or see deleted issues', function () {
    makeIssue($this->project, $this->admin, ['title' => 'Scoped away']);
    makeProject($this->admin, 'DOC');
    $plain = app(TokenService::class)->create($this->admin, 'doc only', true, ['DOC'])->plainTextToken;

    $this->withToken($plain)->deleteJson('/api/v1/issues/WEB-1', ['confirm' => true])->assertNotFound();
    $this->withToken($plain)->getJson('/api/v1/issues?q=Scoped')->assertOk()->assertDontSee('Scoped away');
    expect(Issue::count())->toBe(1);
});

test('the deleted issues list is for admins only, and restore from it needs write access', function () {
    $this->issues->delete(makeIssue($this->project, $this->admin, ['title' => 'Binned']), $this->admin);

    foreach ([$this->member, $this->viewer] as $user) {
        $this->actingAs($user)->get('/projects/WEB/settings')->assertOk()->assertDontSee('Binned')
            ->assertInertia(fn (Assert $page) => $page->where('deletedIssues', []));
    }
    $this->actingAs($this->viewer)->post('/issues/WEB-1/restore')->assertForbidden();
    $this->actingAs($this->outsider)->get('/projects/WEB/settings')->assertForbidden();
    expect(Issue::count())->toBe(0);
});
