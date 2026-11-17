<?php

use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Services\TokenService;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    Mail::fake();
    $this->freezeSecond();
    $this->owner = User::factory()->create(['email' => 'owner@example.com']);
    $this->ws = makeWorkspace($this->owner, 'acme');
    $this->project = makeProject($this->owner, 'WEB');
    makeIssue($this->project, $this->owner);
    $this->member = addMember($this->project, User::factory()->create(), ProjectRole::Member);
    $this->token = app(TokenService::class)->create($this->member, 'agent', true, null)->plainTextToken;
    makeInvitation($this->project, $this->owner, 'new@example.com', 'member');
});

test('only the Owner can delete, and only by typing the slug', function () {
    $admin = User::factory()->create();
    $this->ws->addMember($admin, WorkspaceRole::Admin);

    $this->actingAs($admin)->delete('/acme/settings/general', ['confirm_slug' => 'acme'])->assertForbidden();
    $this->actingAs($this->owner)->delete('/acme/settings/general', ['confirm_slug' => 'Acme'])
        ->assertSessionHasErrors(['confirm_slug' => 'Type acme to confirm.']);
    expect($this->ws->fresh()->deletion_scheduled_at)->toBeNull();
});

test('deleting schedules it for 7 days; members are locked out and the Owner sees Undo', function () {
    $this->actingAs($this->owner)->delete('/acme/settings/general', ['confirm_slug' => 'acme'])->assertRedirect('/acme/dashboard');
    expect($this->ws->fresh()->deletion_scheduled_at->equalTo(now()->addDays(7)))->toBeTrue();
    $date = now()->addDays(7)->format('j F Y');

    $this->actingAs($this->member)->get('/acme/projects/WEB')->assertForbidden()->assertInertia(fn (Assert $page) => $page
        ->component('Workspaces/NoAccess')
        ->where('message', "Acme is scheduled for deletion on {$date}. Ask its Owner to undo this if it's a mistake.")
        ->where('restoreUrl', null));
    $this->actingAs($this->member)->post('/acme/projects/WEB/issues', ['title' => 'Late'])->assertForbidden();
    $this->actingAs($this->owner)->get('/acme/dashboard')->assertForbidden()->assertInertia(fn (Assert $page) => $page
        ->where('message', "Acme is scheduled for deletion on {$date}. You can undo this until then.")
        ->where('restoreUrl', route('workspaces.restore', 'acme')));

    app('auth')->forgetGuards();
    $this->withToken($this->token)->getJson('/api/v1/projects')->assertForbidden()
        ->assertJsonPath('message', "Acme is scheduled for deletion on {$date}. Ask its Owner to undo this if it's a mistake.");
});

test('a member of another workspace lands there instead', function () {
    $other = makeWorkspace($this->member, 'bravo');
    useWorkspace($this->ws, $this->member);
    $this->ws->forceFill(['deletion_scheduled_at' => now()->addDays(7)])->save();

    $this->actingAs($this->member->fresh())->get('/dashboard')->assertRedirect('/bravo/dashboard');
    $this->actingAs($this->member->fresh())->get('/workspaces/no-access')->assertRedirect('/bravo/dashboard');
    expect($other->exists)->toBeTrue();
});

test('with no other workspace, the no-access page says when it will be deleted', function () {
    $this->ws->forceFill(['deletion_scheduled_at' => now()->addDays(7)])->save();

    $this->actingAs($this->member)->get('/dashboard')->assertRedirect('/workspaces/no-access');
    $this->actingAs($this->member)->get('/workspaces/no-access')->assertInertia(fn (Assert $page) => $page
        ->where('message', 'Acme is scheduled for deletion on '.now()->addDays(7)->format('j F Y').". Ask its Owner to undo this if it's a mistake."));
});

test('the Owner can undo; nobody else can', function () {
    $this->ws->forceFill(['deletion_scheduled_at' => now()->addDays(7)])->save();

    $this->actingAs($this->member)->post('/workspaces/acme/restore')->assertForbidden();
    $this->actingAs(User::factory()->create())->post('/workspaces/acme/restore')->assertNotFound();
    expect($this->ws->fresh()->deletion_scheduled_at)->not->toBeNull();

    $this->actingAs($this->owner)->post('/workspaces/acme/restore')->assertRedirect('/acme/dashboard');
    expect($this->ws->fresh()->deletion_scheduled_at)->toBeNull();
    $this->actingAs($this->member)->get('/acme/projects/WEB')->assertOk();
});

test('workspaces:purge deletes it after 7 days and not before, leaving other workspaces alone', function () {
    $keep = makeWorkspace(User::factory()->create(), 'keep');
    $keptProject = makeProject($keep->members()->first(), 'KEEP');
    useWorkspace($this->ws);
    $this->actingAs($this->owner)->delete('/acme/settings/general', ['confirm_slug' => 'acme']);

    $this->travelTo(now()->addDays(7)->subSecond());
    $this->artisan('workspaces:purge')->assertSuccessful();
    expect(Workspace::find($this->ws->id))->not->toBeNull();

    $this->travel(1)->second();
    $this->artisan('workspaces:purge')->assertSuccessful();

    expect(Workspace::find($this->ws->id))->toBeNull()
        ->and(Project::withoutGlobalScopes()->where('workspace_id', $this->ws->id)->count())->toBe(0)
        ->and(Issue::withTrashed()->where('project_id', $this->project->id)->count())->toBe(0)
        ->and(PersonalAccessToken::where('workspace_id', $this->ws->id)->count())->toBe(0)
        ->and(WorkspaceInvitation::where('workspace_id', $this->ws->id)->count())->toBe(0)
        ->and($this->member->fresh())->not->toBeNull()
        ->and($this->owner->fresh()->current_workspace_id)->toBeNull()
        ->and(Project::find($keptProject->id))->not->toBeNull();
});
