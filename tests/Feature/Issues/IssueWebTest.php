<?php

use App\Models\Activity;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->project = makeProject($this->admin, 'WEB');
});

test('project page lists recent issues and create options', function () {
    makeIssue($this->project, $this->admin, ['title' => 'Hello']);

    $this->actingAs($this->admin)->get('/projects/WEB')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Show')
            ->where('issues.0.key', 'WEB-1')
            ->has('options.columns', 4)
            ->has('options.types', 4));
});

test('creating from the web redirects to the issue', function () {
    $this->actingAs($this->admin)->post('/projects/WEB/issues', ['title' => 'Web issue'])
        ->assertRedirect('/issues/WEB-1');
});

test('issue page renders markdown safely', function () {
    makeIssue($this->project, $this->admin, ['description' => '**bold** <script>alert(1)</script> [x](javascript:alert(1))']);

    $this->actingAs($this->admin)->get('/issues/WEB-1')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Issues/Show')
            ->where('issue.key', 'WEB-1')
            ->where('issue.description_html', fn ($html) => str_contains($html, '<strong>bold</strong>')
                && ! str_contains($html, '<script>')
                && ! str_contains($html, 'javascript:')));
});

test('web edits are logged as via web without a token name', function () {
    makeIssue($this->project, $this->admin);

    $this->actingAs($this->admin)->patch('/issues/WEB-1', ['priority' => 'low'])->assertRedirect();

    $activity = Activity::where('action', 'updated')->sole();
    expect($activity->via)->toBe('web')->and($activity->token_name)->toBeNull();
});

test('web comments and deletes', function () {
    makeIssue($this->project, $this->admin);

    $this->actingAs($this->admin)->post('/issues/WEB-1/comments', ['body' => 'Nice'])->assertRedirect();
    $this->actingAs($this->admin)->delete('/issues/WEB-1', ['confirm' => true])->assertRedirect('/projects/WEB');
});
