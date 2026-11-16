<?php

use App\Enums\ProjectRole;
use App\Models\Issue;
use App\Models\User;
use App\Services\IssueService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create(['name' => 'Ada Admin']);
    $this->project = makeProject($this->admin, 'WEB');
    makeIssue($this->project, $this->admin, ['title' => 'Still here']);
    app(IssueService::class)->delete(makeIssue($this->project, $this->admin, ['title' => 'Thrown away']), $this->admin);
});

test('a project admin sees deleted issues in project settings, newest first, with who deleted them', function () {
    $this->actingAs($this->admin)->get(w('/projects/WEB/settings'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('deletedIssues', 1)
        ->where('deletedIssues.0.key', 'WEB-2')
        ->where('deletedIssues.0.title', 'Thrown away')
        ->where('deletedIssues.0.deleted_by', 'Ada Admin')
        ->has('deletedIssues.0.deleted_at'));
});

test('members do not get the deleted issues list', function () {
    $member = addMember($this->project, User::factory()->create(), ProjectRole::Member);

    $this->actingAs($member)->get(w('/projects/WEB/settings'))->assertOk()
        ->assertDontSee('Thrown away')
        ->assertInertia(fn (Assert $page) => $page->where('deletedIssues', []));
});

test('an admin restores an issue from the list', function () {
    $this->actingAs($this->admin)->post(w('/issues/WEB-2/restore'))->assertRedirect();

    expect(Issue::count())->toBe(2);
    $this->get(w('/projects/WEB/settings'))->assertInertia(fn (Assert $page) => $page->where('deletedIssues', []));
});
