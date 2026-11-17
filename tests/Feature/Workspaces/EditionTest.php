<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

$register = fn (array $extra = []) => ['name' => 'New Person', 'email' => 'new@example.com', 'password' => 'password', 'password_confirmation' => 'password', ...$extra];

describe('self-hosted', function () use ($register) {
    beforeEach(fn () => config(['pm.edition' => 'self_hosted']));

    test('the first person to register creates the workspace and owns it', function () use ($register) {
        $this->get('/register')->assertInertia(fn (Assert $page) => $page->where('createsWorkspace', false)->where('closedMessage', null));

        $this->post('/register', $register())->assertRedirect('/dashboard');

        $workspace = Workspace::sole();
        expect($workspace->name)->toBe(config('app.name'))
            ->and(User::sole()->workspaceRole($workspace))->toBe(WorkspaceRole::Owner);
    });

    test('after the first user, registering needs an invitation', function () use ($register) {
        $owner = User::factory()->create();
        makeWorkspace($owner, 'acme');
        auth()->logout();

        $this->get('/register')->assertInertia(fn (Assert $page) => $page->where('closedMessage', 'Ask an admin of Acme to invite you.'));
        $this->post('/register', $register())->assertSessionHasErrors(['email' => 'Ask an admin of Acme to invite you.']);
        $this->assertGuest();
        expect(User::count())->toBe(1);
    });

    test('a second workspace is refused', function () {
        $owner = User::factory()->create();
        app(WorkspaceService::class)->create($owner, 'First');

        expect(fn () => app(WorkspaceService::class)->create($owner, 'Second'))->toThrow(ValidationException::class);
        $this->actingAs($owner)->get('/workspaces/create')->assertNotFound();
        $this->actingAs($owner)->post('/workspaces', ['name' => 'Second'])->assertNotFound();
        expect(Workspace::count())->toBe(1);
    });
});

describe('cloud', function () use ($register) {
    beforeEach(fn () => config(['pm.edition' => 'cloud']));

    test('registering asks for a workspace name and makes the person its Owner', function () use ($register) {
        $other = User::factory()->create();
        makeWorkspace($other, 'existing');
        auth()->logout();

        $this->get('/register')->assertInertia(fn (Assert $page) => $page->where('createsWorkspace', true)->where('closedMessage', null));
        $this->post('/register', $register())->assertSessionHasErrors('workspace_name');

        $this->post('/register', $register(['workspace_name' => 'Acme Inc', 'workspace_slug' => 'acme']))->assertRedirect('/dashboard');

        $user = User::firstWhere('email', 'new@example.com');
        $workspace = Workspace::firstWhere('slug', 'acme');
        expect($workspace->name)->toBe('Acme Inc')
            ->and($user->workspaceRole($workspace))->toBe(WorkspaceRole::Owner)
            ->and($user->current_workspace_id)->toBe($workspace->id);
    });

    test('a taken or reserved slug is refused at sign-up', function (string $slug) use ($register) {
        $other = User::factory()->create();
        makeWorkspace($other, 'taken');
        auth()->logout();

        $this->post('/register', $register(['workspace_name' => 'Acme', 'workspace_slug' => $slug]))->assertSessionHasErrors('workspace_slug');
        $this->assertGuest();
    })->with(['taken', 'admin', 'ab']);

    test('members create more workspaces from the switcher', function () {
        $ann = User::factory()->create();
        makeWorkspace($ann, 'alpha');

        $this->actingAs($ann)->get('/workspaces/create')->assertInertia(fn (Assert $page) => $page->component('Workspaces/Create'));
        $this->actingAs($ann)->post('/workspaces', ['name' => 'Second', 'slug' => 'second'])->assertRedirect('/second/dashboard');

        expect($ann->fresh()->workspaceRole(Workspace::firstWhere('slug', 'second')))->toBe(WorkspaceRole::Owner)
            ->and($ann->fresh()->current_workspace_id)->toBe(Workspace::firstWhere('slug', 'second')->id);
    });
});
