<?php

use App\Enums\Entitlement;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Entitlements;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->ws = makeWorkspace(User::factory()->create(), 'acme');
    $this->entitlements = app(Entitlements::class);
});

test('--set stores an override and logs entitlement.changed with before and after', function () {
    $this->artisan('pm:entitlements', ['workspace' => 'acme', '--set' => ['projects=5', 'webhooks=true']])
        ->expectsOutputToContain('projects = 5')
        ->assertSuccessful();

    expect($this->entitlements->get($this->ws, Entitlement::Projects))->toBe(5)
        ->and($this->entitlements->get($this->ws, Entitlement::Webhooks))->toBeTrue()
        ->and($this->ws->entitlements()->where('key', 'projects')->value('source'))->toBe('override');
    $row = AuditLog::where('action', 'entitlement.changed')->orderBy('id')->first();
    expect($row->workspace_id)->toBe($this->ws->id)->and($row->actor_type)->toBe('system')
        ->and($row->metadata)->toEqual(['key' => 'projects', 'before' => null, 'after' => '5', 'source' => 'override']);
});

test('--unset removes the row, which means unlimited, and --list shows every key', function () {
    $this->entitlements->set($this->ws, Entitlement::Projects, '1', 'default');

    $this->artisan('pm:entitlements', ['workspace' => 'acme', '--unset' => ['projects']])->assertSuccessful();
    expect($this->entitlements->get($this->ws, Entitlement::Projects))->toBeNull();
    expect(AuditLog::where('action', 'entitlement.changed')->latest('id')->first()->metadata)->toEqual(['key' => 'projects', 'before' => '1', 'after' => null, 'source' => null]);

    expect(Artisan::call('pm:entitlements', ['workspace' => 'acme', '--list' => true]))->toBe(0);
    $output = Artisan::output();
    foreach (Entitlement::cases() as $key) {
        expect($output)->toContain($key->value);
    }
    expect($output)->toMatch('/projects\s*\|\s*unlimited/');
});

test('bad input fails with the valid options and changes nothing', function (array $args, string $error) {
    $this->artisan('pm:entitlements', ['workspace' => 'acme', ...$args])->expectsOutputToContain($error)->assertFailed();
    expect($this->ws->entitlements()->count())->toBe(0);
})->with([
    'unknown key' => [['--set' => ['seats=3']], 'Unknown entitlement "seats". Valid keys: billed_users, viewers, projects'],
    'not a number' => [['--set' => ['projects=lots']], 'projects takes a whole number of 0 or more.'],
    'not a boolean' => [['--set' => ['webhooks=maybe']], 'webhooks takes true or false.'],
    'bad custom fields mode' => [['--set' => ['custom_fields_mode=some']], 'custom_fields_mode takes one of: catalog_only, full.'],
    'no equals sign' => [['--set' => ['projects']], 'Use --set=key=value.'],
]);

test('an unknown workspace fails', function () {
    $this->artisan('pm:entitlements', ['workspace' => 'nope', '--list' => true])
        ->expectsOutputToContain('No workspace with the address "nope".')
        ->assertFailed();
});
