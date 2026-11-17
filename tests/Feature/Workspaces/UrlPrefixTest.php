<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->ws = makeWorkspace($this->user, 'acme');
    makeProject($this->user, 'WEB');
});

test('old unprefixed URLs redirect into the current workspace, query string kept', function (string $old) {
    $this->actingAs($this->user)->get($old)->assertRedirect('/acme'.$old);
})->with([
    '/dashboard', '/dashboard?verified=1', '/projects', '/projects/WEB', '/projects/WEB/backlog?sprint=2',
    '/projects/WEB/issues?type=bug', '/issues/WEB-1', '/issues/WEB-1?tab=history', '/search?q=login', '/settings/tokens',
]);

test('prefixed pages work and keep their route names', function () {
    $this->actingAs($this->user)->get('/acme/projects/WEB')->assertOk();

    expect(route('projects.show', 'WEB', false))->toBe('/acme/projects/WEB');
});

test('an unknown, reserved or malformed slug is a 404', function (string $path) {
    $this->actingAs($this->user)->get($path)->assertNotFound();
})->with(['/nope-ws/projects', '/ab/projects', '/Acme/projects', '/acme_x/projects', '/admin/projects']);

test('a workspace you are not in answers exactly like an unknown slug', function () {
    $other = User::factory()->create();
    makeWorkspace($other, 'bravo');
    useWorkspace($this->ws, $this->user);

    $notMember = $this->actingAs($this->user)->get('/bravo/projects');
    $unknown = $this->actingAs($this->user)->get('/nobody-here/projects');

    $notMember->assertNotFound();
    expect($notMember->getContent())->toBe($unknown->getContent());
});

test('an old slug redirects to the new one, but only for members', function () {
    DB::table('workspace_slug_redirects')->insert(['old_slug' => 'acme-old', 'workspace_id' => $this->ws->id, 'created_at' => now()]);

    $this->actingAs($this->user)->get('/acme-old/projects/WEB?x=1')->assertStatus(301)->assertRedirect('/acme/projects/WEB?x=1');

    $outsider = User::factory()->create();
    makeWorkspace($outsider, 'other');
    $this->actingAs($outsider)->get('/acme-old/projects/WEB')->assertNotFound();
});

test('guests are sent to log in', function () {
    $this->get('/acme/projects')->assertRedirect('/login');
});

test('profile stays global', function () {
    $this->actingAs($this->user)->get('/profile')->assertOk();
});
