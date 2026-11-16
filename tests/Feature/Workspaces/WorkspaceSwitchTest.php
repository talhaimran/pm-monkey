<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->ann = User::factory()->create();
    $this->a = makeWorkspace($this->ann, 'alpha');
    makeProject($this->ann, 'AAA');
    $this->b = makeWorkspace($this->ann, 'bravo');
    makeProject($this->ann, 'BBB');
});

test('switching workspace changes what the projects page lists, and current_workspace_id follows', function () {
    $this->actingAs($this->ann)->get('/alpha/projects')
        ->assertInertia(fn (Assert $page) => $page->has('projects', 1)->where('projects.0.key', 'AAA')
            ->where('workspace.slug', 'alpha')->where('workspace.role', 'owner')
            ->where('workspaces', [['name' => 'Alpha', 'slug' => 'alpha'], ['name' => 'Bravo', 'slug' => 'bravo']]));
    expect($this->ann->fresh()->current_workspace_id)->toBe($this->a->id);

    $this->actingAs($this->ann)->get('/bravo/projects')
        ->assertInertia(fn (Assert $page) => $page->has('projects', 1)->where('projects.0.key', 'BBB')->where('workspace.slug', 'bravo'));
    expect($this->ann->fresh()->current_workspace_id)->toBe($this->b->id);

    $this->actingAs($this->ann)->get('/dashboard')->assertRedirect('/bravo/dashboard');
});

test('the same project key in another workspace is a different project', function () {
    useWorkspace($this->a, $this->ann);
    makeProject($this->ann, 'SHR', 'Alpha shared');
    useWorkspace($this->b, $this->ann);
    makeProject($this->ann, 'SHR', 'Bravo shared');

    $this->actingAs($this->ann)->get('/alpha/projects/SHR/settings')->assertInertia(fn (Assert $page) => $page->where('project.name', 'Alpha shared'));
    $this->actingAs($this->ann)->get('/bravo/projects/SHR/settings')->assertInertia(fn (Assert $page) => $page->where('project.name', 'Bravo shared'));
});

test('last active is written at most every five minutes', function () {
    $lastActive = fn () => DB::table('workspace_user')->where(['workspace_id' => $this->a->id, 'user_id' => $this->ann->id])->value('last_active_at');

    $this->travelTo(now()->startOfMinute());
    $this->actingAs($this->ann)->get('/alpha/projects');
    $first = $lastActive();
    expect($first)->not->toBeNull();

    $this->travel(4)->minutes();
    $this->actingAs($this->ann)->get('/alpha/projects');
    expect($lastActive())->toBe($first);

    $this->travel(2)->minutes();
    $this->actingAs($this->ann)->get('/alpha/projects');
    expect($lastActive())->not->toBe($first);
});

test('someone with no workspace left sees the no-access page', function () {
    $sam = User::factory()->create();
    $this->a->addMember($sam, WorkspaceRole::Member);
    $sam->forceFill(['current_workspace_id' => $this->a->id])->save();
    $this->a->members()->detach($sam->id);

    $this->actingAs($sam)->get('/dashboard')->assertRedirect(route('workspaces.no-access'));
    $this->actingAs($sam)->get(route('workspaces.no-access'))
        ->assertInertia(fn (Assert $page) => $page->component('Workspaces/NoAccess')
            ->where('message', 'You are no longer a member of Alpha. Ask an admin to invite you again.')
            ->where('canCreate', true));
    $this->actingAs($sam)->get('/alpha/projects')->assertNotFound();
});

test('the no-access page sends members back to their workspace', function () {
    $this->actingAs($this->ann)->get(route('workspaces.no-access'))->assertRedirect('/bravo/dashboard');
});
