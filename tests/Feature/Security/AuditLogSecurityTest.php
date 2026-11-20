<?php

use App\Models\User;
use App\Services\AgentService;

/** Step 0 PR 4 security review probes: the audit log export and who can reach it. */
beforeEach(function () {
    $this->owner = User::factory()->create(['name' => '@SUM(1+1)']);
    $this->ws = makeWorkspace($this->owner, 'acme');
});

test('user-supplied text in the CSV export cannot run as a spreadsheet formula', function () {
    $this->actingAs($this->owner);
    app(AgentService::class)->create($this->owner, ['name' => '=HYPERLINK("https://evil.example","x")', 'level' => 'read']);

    $csv = $this->get('/acme/settings/audit-log/export?group=agent')->streamedContent();
    $row = str_getcsv(explode("\n", trim($csv))[1], escape: '');

    expect($row[1])->toBe("'@SUM(1+1)")
        ->and($row[3])->toBe('\'=HYPERLINK("https://evil.example","x")');
});

test('agent tokens cannot read the audit log: it is a web page for signed-in Owners and Admins', function () {
    $token = app(AgentService::class)->create($this->owner, ['name' => 'agent', 'level' => 'read'])[1];

    $this->withToken($token)->get('/acme/settings/audit-log')->assertRedirect('/login');
    $this->withToken($token)->getJson('/acme/settings/audit-log/export')->assertUnauthorized();
});
