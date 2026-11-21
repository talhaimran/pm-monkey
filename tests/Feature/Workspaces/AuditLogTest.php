<?php

use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Mail\ProjectInvitationMail;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AgentService;
use App\Services\AuditLogger;
use App\Services\InvitationService;
use App\Services\ProjectService;
use App\Services\TwoFactorService;
use App\Services\WorkspaceMemberService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    Mail::fake();
    $this->owner = User::factory()->create(['name' => 'Olive', 'email' => 'owner@example.com']);
    $this->ws = makeWorkspace($this->owner, 'acme');
    $this->project = makeProject($this->owner, 'WEB');
});

/** The audit rows for $action, oldest first. */
function audits(string $action)
{
    return AuditLog::where('action', $action)->orderBy('id')->get();
}

/** Exactly one row for $action, in $workspace, by $actor, with $label. */
function expectOneAudit(string $action, ?Workspace $workspace, ?User $actor, ?string $label = null): AuditLog
{
    $rows = audits($action);
    expect($rows)->toHaveCount(1);
    $row = $rows->first();
    expect($row->workspace_id)->toBe($workspace?->id)
        ->and($row->actor_user_id)->toBe($actor?->id);
    if ($label !== null) {
        expect($row->target_label)->toBe($label);
    }

    return $row;
}

describe('sign-in events', function () {
    beforeEach(function () {
        $this->other = makeWorkspace($this->owner, 'other');
        AuditLog::query()->toBase()->delete();
    });

    test('auth.login goes to every workspace the user belongs to', function () {
        $this->post('/login', ['email' => 'owner@example.com', 'password' => 'password'])->assertRedirect();

        $rows = audits('auth.login');
        expect($rows->pluck('workspace_id')->sort()->values()->all())->toBe([$this->ws->id, $this->other->id])
            ->and($rows->pluck('actor_user_id')->unique()->all())->toBe([$this->owner->id])
            ->and($rows->first()->via)->toBe('web')
            ->and($rows->first()->ip)->toBe('127.0.0.1');
    });

    test('auth.login_failed: per workspace for a known email, one unscoped row for an unknown one', function () {
        $this->post('/login', ['email' => 'owner@example.com', 'password' => 'wrong-secret-1']);
        expect(audits('auth.login_failed'))->toHaveCount(2)
            ->and(audits('auth.login_failed')->pluck('actor_user_id')->unique()->all())->toBe([$this->owner->id]);

        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong-secret-2']);
        $unknown = audits('auth.login_failed')->last();
        expect($unknown->workspace_id)->toBeNull()->and($unknown->actor_user_id)->toBeNull()
            ->and($unknown->metadata)->toBe(['email' => 'nobody@example.com']);
    });

    test('auth.logout, auth.password_changed and auth.password_reset', function () {
        $this->actingAs($this->owner)->post('/logout');
        expect(audits('auth.logout'))->toHaveCount(2);

        $this->actingAs($this->owner)->put('/password', ['current_password' => 'password', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
            ->assertSessionHasNoErrors();
        expect(audits('auth.password_changed'))->toHaveCount(2);

        Notification::fake();
        app('auth')->forgetGuards();
        $this->post('/logout');
        $this->post('/forgot-password', ['email' => 'owner@example.com']);
        Notification::assertSentTo($this->owner, ResetPassword::class, function ($n) {
            $this->post('/reset-password', ['token' => $n->token, 'email' => 'owner@example.com', 'password' => 'new-password-2', 'password_confirmation' => 'new-password-2'])
                ->assertSessionHasNoErrors();

            return true;
        });
        expect(audits('auth.password_reset'))->toHaveCount(2);
    });
});

test('workspace.created, workspace.updated and workspace.settings_changed with before and after', function () {
    expectOneAudit('workspace.created', $this->ws, $this->owner, 'Acme');

    $this->actingAs($this->owner)->patch('/acme/settings/general', ['name' => 'Acme Co', 'slug' => 'acme-co', 'members_can_create_projects' => false])
        ->assertSessionHasNoErrors();

    expect(expectOneAudit('workspace.updated', $this->ws, $this->owner, 'Acme Co')->metadata)
        ->toEqual(['before' => ['name' => 'Acme', 'slug' => 'acme'], 'after' => ['name' => 'Acme Co', 'slug' => 'acme-co']]);
    expect(expectOneAudit('workspace.settings_changed', $this->ws, $this->owner)->metadata)
        ->toEqual(['before' => ['members_can_create_projects' => true], 'after' => ['members_can_create_projects' => false]]);

    // Saving without changes logs nothing.
    $this->actingAs($this->owner)->patch('/acme-co/settings/general', ['name' => 'Acme Co', 'slug' => 'acme-co', 'members_can_create_projects' => false]);
    expect(audits('workspace.updated'))->toHaveCount(1)->and(audits('workspace.settings_changed'))->toHaveCount(1);
});

test('deletion scheduled, cancelled, and workspace.deleted survives the purge on its own', function () {
    $this->actingAs($this->owner)->delete('/acme/settings/general', ['confirm_slug' => 'acme']);
    expectOneAudit('workspace.deletion_scheduled', $this->ws, $this->owner, 'Acme');
    $this->actingAs($this->owner)->post('/workspaces/acme/restore');
    expectOneAudit('workspace.deletion_cancelled', $this->ws, $this->owner, 'Acme');

    $this->ws->forceFill(['deletion_scheduled_at' => now()->subMinute()])->save();
    $this->artisan('workspaces:purge')->assertSuccessful();

    // The workspace's own log goes with it; one system row records the deletion.
    expect(AuditLog::where('workspace_id', $this->ws->id)->count())->toBe(0);
    $deleted = expectOneAudit('workspace.deleted', null, null, 'Acme');
    expect($deleted->actor_type)->toBe('system')->and($deleted->target_id)->toBe($this->ws->id)->and($deleted->via)->toBe('system');
});

test('member events: invited, revoked, joined, role changed, owner added and removed, removed', function () {
    $invitations = app(InvitationService::class);
    $members = app(WorkspaceMemberService::class);
    $this->actingAs($this->owner);

    $inv = $invitations->invite($this->ws, $this->owner, 'gone@example.com', WorkspaceRole::Member);
    $invitations->revoke($inv, $this->owner);
    expectOneAudit('member.invitation_revoked', $this->ws, $this->owner, 'gone@example.com');

    $inv = $invitations->invite($this->ws, $this->owner, 'new@example.com', WorkspaceRole::Member, $this->project, ProjectRole::Member);
    expect(audits('member.invited'))->toHaveCount(2)
        ->and(audits('member.invited')->last()->metadata)->toEqual(['role' => 'member', 'project' => 'WEB', 'project_role' => 'member']);

    $new = User::factory()->create(['email' => 'new@example.com']);
    $invitations->accept($inv, $new);
    $joined = expectOneAudit('member.joined', $this->ws, $new, 'new@example.com');
    expect($joined->metadata)->toEqual(['role' => 'member', 'project' => 'WEB']);

    $members->changeRole($this->ws, $this->owner, $new, WorkspaceRole::Admin);
    expect(expectOneAudit('member.role_changed', $this->ws, $this->owner, 'new@example.com')->metadata)->toEqual(['from' => 'member', 'to' => 'admin']);
    $members->changeRole($this->ws, $this->owner, $new, WorkspaceRole::Owner);
    expectOneAudit('owner.added', $this->ws, $this->owner, 'new@example.com');
    $members->changeRole($this->ws, $this->owner, $new, WorkspaceRole::Member);
    expect(expectOneAudit('owner.removed', $this->ws, $this->owner, 'new@example.com')->metadata)->toEqual(['from' => 'owner', 'to' => 'member']);
    expect(audits('member.role_changed'))->toHaveCount(1);

    $members->remove($this->ws, $this->owner, $new);
    expectOneAudit('member.removed', $this->ws, $this->owner, 'new@example.com');
});

test('project events: created (with via), deleted and admin_joined', function () {
    expectOneAudit('project.created', $this->ws, $this->owner, 'WEB');

    apiAs($this->owner);
    $this->postJson('/api/v1/projects', ['key' => 'API', 'name' => 'Api'])->assertCreated();
    expect(audits('project.created')->last()->via)->toBe('api');
    $this->deleteJson('/api/v1/projects/API', ['confirm' => true])->assertSuccessful();
    expectOneAudit('project.deleted', $this->ws, $this->owner, 'API');

    $admin = User::factory()->create();
    $this->ws->addMember($admin, WorkspaceRole::Admin);
    app('auth')->forgetGuards();
    $this->actingAs($admin)->post('/acme/projects/WEB/join')->assertRedirect();
    expectOneAudit('project.admin_joined', $this->ws, $admin, 'WEB');
});

test('agent events record the agent name, never the token', function () {
    $this->actingAs($this->owner);
    [$agent] = app(AgentService::class)->create($this->owner, ['name' => 'ci deploy', 'level' => 'write']);
    expectOneAudit('agent.created', $this->ws, $this->owner, 'ci deploy');

    app(AgentService::class)->revoke($agent, $this->owner);
    expectOneAudit('agent.revoked', $this->ws, $this->owner, 'ci deploy');
});

test('issues:purge records issue.purged in the audit log by key, without the title (E8)', function () {
    $issue = makeIssue($this->project, $this->owner, ['title' => 'Private title']);
    $issue->delete();
    $issue->forceFill(['deleted_at' => now()->subDays(31)])->saveQuietly();

    $this->artisan('issues:purge')->assertSuccessful();

    $row = expectOneAudit('issue.purged', $this->ws, null, 'WEB-1');
    expect($row->actor_type)->toBe('system')->and(json_encode($row->toArray()))->not->toContain('Private title');
});

test('the log is append-only', function () {
    $row = AuditLog::firstOrFail();

    expect(fn () => $row->update(['action' => 'x']))->toThrow(LogicException::class)
        ->and(fn () => $row->delete())->toThrow(LogicException::class);
});

test('Owners and Admins read the log of this workspace only; Members cannot', function () {
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $this->ws->addMember($admin, WorkspaceRole::Admin);
    $this->ws->addMember($member, WorkspaceRole::Member);
    makeWorkspace(User::factory()->create(['name' => 'Stranger']), 'other');
    useWorkspace($this->ws);

    $this->actingAs($member)->get('/acme/settings/audit-log')->assertForbidden();
    $this->actingAs($member)->get('/acme/settings/audit-log/export')->assertForbidden();
    $this->actingAs($admin)->get('/acme/settings/audit-log')->assertInertia(fn (Assert $page) => $page
        ->component('Workspaces/Settings/AuditLog')
        ->where('entries.data', fn ($rows) => collect($rows)->pluck('action')->sort()->values()->all() === ['project.created', 'workspace.created'])
        ->where('entries.data.0.actor', 'Olive'));
});

test('filters by actor, action group and date', function () {
    $admin = User::factory()->create();
    $this->ws->addMember($admin, WorkspaceRole::Admin);
    $this->travel(2)->days();
    $this->actingAs($admin);
    app(AgentService::class)->create($admin, ['name' => 'later', 'level' => 'read']);

    $get = fn (array $q) => $this->actingAs($this->owner)->get('/acme/settings/audit-log?'.http_build_query($q))->viewData('page')['props']['entries']['data'];

    expect(collect($get(['actor' => $admin->id]))->pluck('action')->all())->toBe(['agent.created'])
        ->and(collect($get(['group' => 'workspace']))->pluck('action')->all())->toBe(['workspace.created'])
        ->and(collect($get(['from' => now()->toDateString()]))->pluck('action')->all())->toBe(['agent.created'])
        ->and(collect($get(['to' => now()->subDays(1)->toDateString()]))->pluck('action')->sort()->values()->all())->toBe(['project.created', 'workspace.created']);
});

test('CSV export follows the filter, newest first, logs audit_log.exported and is capped (E9)', function () {
    $this->actingAs($this->owner);
    $csv = $this->get('/acme/settings/audit-log/export?group=project')->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')->streamedContent();

    $lines = array_values(array_filter(explode("\n", trim($csv))));
    expect($lines[0])->toBe('When,Actor,Action,Target,Via,IP')
        ->and($lines)->toHaveCount(2)
        ->and($lines[1])->toContain('project.created')->toContain('WEB');
    expect(expectOneAudit('audit_log.exported', $this->ws, $this->owner)->metadata)->toEqual(['filters' => ['group' => 'project'], 'rows' => 1]);

    // 10,001 rows made by MySQL, not PHP, to keep the suite's memory down.
    DB::statement('SET SESSION cte_max_recursion_depth = 20000');
    DB::insert("insert into audit_logs (workspace_id, actor_type, via, action, target_label, created_at)
        with recursive n(i) as (select 1 union all select i + 1 from n where i < 10001)
        select ?, 'system', 'system', 'issue.purged', concat('WEB-', i), now() from n", [$this->ws->id]);

    $csv = $this->get('/acme/settings/audit-log/export?group=issue')->streamedContent();
    $lines = explode("\n", trim($csv));
    expect($lines)->toHaveCount(10_002)
        ->and(end($lines))->toBe('"Cut off at 10,000 rows. Narrow the filter to export the rest."');
});

test('no password, token or secret ever reaches the audit log', function () {
    $password = 'Very-Secret-Passw0rd!';
    $user = User::factory()->create(['email' => 'secret@example.com', 'password' => $password]);
    $this->ws->addMember($user, WorkspaceRole::Admin);
    $plain = [];

    $this->post('/login', ['email' => 'secret@example.com', 'password' => 'Wrong-Secret-Passw0rd!']);
    $this->post('/login', ['email' => 'secret@example.com', 'password' => $password]);
    $this->put('/password', ['current_password' => $password, 'password' => 'Next-Secret-Passw0rd!', 'password_confirmation' => 'Next-Secret-Passw0rd!']);
    $plain[] = app(AgentService::class)->create($user, ['name' => 'agent', 'level' => 'write'])[1];
    $this->post('/acme/settings/agents', ['name' => 'web token', 'level' => 'write']);
    $plain[] = session('token');
    Mail::fake();
    app(InvitationService::class)->invite($this->ws, $user, 'invitee@example.com', WorkspaceRole::Member);
    Mail::assertSent(ProjectInvitationMail::class, function ($mail) use (&$plain) {
        $plain[] = $mail->token;

        return true;
    });
    $this->post('/logout');

    $all = json_encode(DB::table('audit_logs')->get());
    foreach (['Very-Secret-Passw0rd!', 'Wrong-Secret-Passw0rd!', 'Next-Secret-Passw0rd!', ...array_filter($plain, 'is_string')] as $secret) {
        $needle = str_contains($secret, '|') ? explode('|', $secret, 2)[1] : $secret;
        expect($all)->not->toContain($needle)->not->toContain(hash('sha256', $needle));
    }
    expect(count(array_filter($plain, 'is_string')))->toBeGreaterThanOrEqual(2);
});

test('AuditLogger keeps the target label when the target is gone', function () {
    $project = app(ProjectService::class)->create($this->owner, ['key' => 'TMP', 'name' => 'Temp']);
    $project->delete();

    expect(audits('project.created')->last()->target_label)->toBe('TMP');
    app(AuditLogger::class)->log('workspace.updated', $this->ws);
    expect(audits('workspace.updated')->last()->target_type)->toBe('workspace');
});

test('the Who filter lists former members who still have entries here, but not people from other workspaces', function () {
    $former = User::factory()->create(['name' => 'Former Fay']);
    $this->ws->addMember($former, WorkspaceRole::Admin);
    $this->actingAs($former);
    app(AgentService::class)->create($former, ['name' => 'old token', 'level' => 'read']);
    app(WorkspaceMemberService::class)->remove($this->ws, $this->owner, $former);
    makeWorkspace(User::factory()->create(['name' => 'Stranger']), 'other');
    useWorkspace($this->ws);

    $this->actingAs($this->owner)->get('/acme/settings/audit-log')->assertInertia(fn (Assert $page) => $page
        ->where('actors', [['id' => $former->id, 'name' => 'Former Fay'], ['id' => $this->owner->id, 'name' => 'Olive']]));
    $this->actingAs($this->owner)->get("/acme/settings/audit-log?actor={$former->id}")->assertInertia(fn (Assert $page) => $page
        ->where('entries.data', fn ($rows) => collect($rows)->pluck('action')->all() === ['agent.created']));
});

test('2fa events: enabled, recovery codes regenerated, recovery code used, disabled, in each of the user’s workspaces', function () {
    $other = makeWorkspace($this->owner, 'other');
    $service = app(TwoFactorService::class);
    $this->actingAs($this->owner);

    $service->enable($this->owner);
    $secret = Fortify::currentEncrypter()->decrypt($this->owner->fresh()->two_factor_secret);
    $codes = $service->confirm($this->owner->fresh(), (new Google2FA)->getCurrentOtp($secret));
    $codes = $service->regenerateRecoveryCodes($this->owner->fresh());
    $this->owner->fresh()->replaceRecoveryCode($codes[0]);
    $service->disable($this->owner->fresh());

    foreach (['2fa.enabled', '2fa.recovery_codes_regenerated', '2fa.recovery_code_used', '2fa.disabled'] as $action) {
        expect(audits($action)->pluck('workspace_id')->sort()->values()->all())->toBe([$this->ws->id, $other->id])
            ->and(audits($action)->pluck('actor_user_id')->unique()->all())->toBe([$this->owner->id]);
    }
    expect(json_encode(DB::table('audit_logs')->get()))->not->toContain($secret)->not->toContain($codes[0]);
});
