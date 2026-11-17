<?php

use App\Models\User;
use App\Services\TokenService;

/** Step 0 PR 4 security review probes: the audit log export and who can reach it. */
beforeEach(function () {
    $this->owner = User::factory()->create(['name' => '@SUM(1+1)']);
    $this->ws = makeWorkspace($this->owner, 'acme');
});

test('user-supplied text in the CSV export cannot run as a spreadsheet formula', function () {
    $this->actingAs($this->owner);
    app(TokenService::class)->create($this->owner, '=HYPERLINK("https://evil.example","x")', false, null);

    $csv = $this->get('/acme/settings/audit-log/export?group=token')->streamedContent();
    $row = str_getcsv(explode("\n", trim($csv))[1], escape: '');

    expect($row[1])->toBe("'@SUM(1+1)")
        ->and($row[3])->toBe('\'=HYPERLINK("https://evil.example","x")');
});

test('API tokens cannot read the audit log: it is a web page for signed-in Owners and Admins', function () {
    $token = app(TokenService::class)->create($this->owner, 'agent', false, null)->plainTextToken;

    $this->withToken($token)->get('/acme/settings/audit-log')->assertRedirect('/login');
    $this->withToken($token)->getJson('/acme/settings/audit-log/export')->assertUnauthorized();
});
