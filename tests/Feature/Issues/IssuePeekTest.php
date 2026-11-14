<?php

use App\Models\User;
use App\Services\IssueService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->project = makeProject($this->admin, 'WEB');
    makeIssue($this->project, $this->admin, ['title' => 'Peek at me', 'description' => '**bold**']);
    $this->actingAs($this->admin);
});

dataset('screens', ['/projects/WEB', '/projects/WEB/issues', '/search?q=Peek']);

test('?issue= opens the same issue the full page shows', function (string $url) {
    $this->get($url.(str_contains($url, '?') ? '&' : '?').'issue=WEB-1')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('peek.issue.key', 'WEB-1')
        ->where('peek.issue.title', 'Peek at me')
        ->where('peek.issue.description_html', fn ($html) => str_contains($html, '<strong>bold</strong>'))
        ->has('peek.options.columns')
        ->where('peek.can.contribute', true));
})->with('screens');

test('without ?issue= there is no peek', function (string $url) {
    $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->where('peek', null));
})->with('screens');

test('a key the user cannot see, a deleted key or junk never opens a drawer or leaks', function (string $url) {
    $other = User::factory()->create();
    makeIssue(makeProject($other, 'OPS'), $other, ['title' => 'Secret plan']);
    $gone = makeIssue($this->project, $this->admin, ['title' => 'Deleted one']);
    app(IssueService::class)->delete($gone, $this->admin);

    foreach (['OPS-1', 'WEB-2', 'WEB-999', 'junk', '', '<script>'] as $key) {
        $this->get($url.(str_contains($url, '?') ? '&' : '?').'issue='.urlencode($key))
            ->assertOk()
            ->assertDontSee('Secret plan')
            ->assertDontSee('Deleted one')
            ->assertInertia(fn (Assert $page) => $page->where('peek', null));
    }
})->with('screens');

test('the full page keeps its URL', function () {
    $this->get('/issues/WEB-1')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Issues/Show')->where('issue.key', 'WEB-1'));
});
