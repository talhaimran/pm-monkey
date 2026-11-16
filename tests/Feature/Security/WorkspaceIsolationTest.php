<?php

use App\Enums\ProjectRole;
use App\Mcp\Servers\LocalPmServer;
use App\Mcp\Servers\PmServer;
use App\Mcp\Tools;
use App\Models\Column;
use App\Models\Comment;
use App\Models\Issue;
use App\Models\Label;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ColumnService;
use App\Services\CommentService;
use App\Services\CurrentWorkspace;
use App\Services\LabelService;
use App\Services\MembershipService;
use App\Services\SprintService;
use App\Services\TokenService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/*
 * Spec 7.0a: acting in workspace A, nothing of workspace B is ever shown or changed, and B's ids and keys
 * get the same error as ids and keys that don't exist. ann is in A and B (and Admin of B's projects, so
 * only the workspace boundary stops her); bob is only in B. B's text all carries the marker "B-SECRET".
 *
 * Every registered route and MCP tool needs a case below or a reasoned exemption; a new one fails
 * the coverage test until it gets one.
 */

const B_MARKER = 'B-SECRET';

/** Placeholders: {P} project key, {I} issue key, {C} comment id, {COL}/{COLN} column id/name, {L}/{LN} label id/name, {S}/{SN} sprint id/name, {T} token id, {INV} invitation id, {U} user id, {E} member email. */
function isolationRouteCases(): array
{
    $confirm = ['confirm' => true];

    return [
        // REST API, as ann with her workspace A token.
        'PATCH api/v1/comments/{comment}' => ['api/v1/comments/{C}', ['body' => 'x']],
        'DELETE api/v1/comments/{comment}' => ['api/v1/comments/{C}', $confirm],
        'GET api/v1/issues' => ['api/v1/issues?q=secret', []],
        'PATCH api/v1/issues/bulk' => ['api/v1/issues/bulk', ['updates' => [['key' => '{I}', 'title' => 'x']]]],
        'GET api/v1/issues/{key}' => ['api/v1/issues/{I}', []],
        'PATCH api/v1/issues/{key}' => ['api/v1/issues/{I}', ['title' => 'x']],
        'DELETE api/v1/issues/{key}' => ['api/v1/issues/{I}', $confirm],
        'GET api/v1/issues/{key}/activity' => ['api/v1/issues/{I}/activity', []],
        'POST api/v1/issues/{key}/comments' => ['api/v1/issues/{I}/comments', ['body' => 'x']],
        'POST api/v1/issues/{key}/move' => ['api/v1/issues/{I}/move', ['status' => '{COLN}']],
        'POST api/v1/issues/{key}/rank' => ['api/v1/issues/{I}/rank', ['sprint' => '{SN}']],
        'GET api/v1/me/issues' => ['api/v1/me/issues', []],
        'GET api/v1/projects' => ['api/v1/projects', []],
        // B has OPS too: creating it in A must work (keys are unique per workspace) and reveal nothing.
        'POST api/v1/projects' => ['api/v1/projects', ['key' => '{P}', 'name' => 'x'], false],
        'GET api/v1/projects/{key}' => ['api/v1/projects/{P}', []],
        'PATCH api/v1/projects/{key}' => ['api/v1/projects/{P}', ['name' => 'x']],
        'DELETE api/v1/projects/{key}' => ['api/v1/projects/{P}', $confirm],
        'GET api/v1/projects/{key}/backlog' => ['api/v1/projects/{P}/backlog', []],
        'GET api/v1/projects/{key}/board' => ['api/v1/projects/{P}/board', []],
        'GET api/v1/projects/{key}/columns' => ['api/v1/projects/{P}/columns', []],
        'POST api/v1/projects/{key}/columns' => ['api/v1/projects/{P}/columns', ['name' => 'x']],
        'PATCH api/v1/projects/{key}/columns/{name}' => ['api/v1/projects/{P}/columns/{COLN}', ['name' => 'x']],
        'DELETE api/v1/projects/{key}/columns/{name}' => ['api/v1/projects/{P}/columns/{COLN}', $confirm],
        'POST api/v1/projects/{key}/invitations' => ['api/v1/projects/{P}/invitations', ['email' => 'x@example.com', 'role' => 'member']],
        'GET api/v1/projects/{key}/issues' => ['api/v1/projects/{P}/issues', []],
        'POST api/v1/projects/{key}/issues' => ['api/v1/projects/{P}/issues', ['title' => 'x']],
        'POST api/v1/projects/{key}/issues/bulk' => ['api/v1/projects/{P}/issues/bulk', ['issues' => [['title' => 'x']]]],
        'GET api/v1/projects/{key}/labels' => ['api/v1/projects/{P}/labels', []],
        'POST api/v1/projects/{key}/labels' => ['api/v1/projects/{P}/labels', ['name' => 'x']],
        'PATCH api/v1/projects/{key}/labels/{name}' => ['api/v1/projects/{P}/labels/{LN}', ['name' => 'x']],
        'DELETE api/v1/projects/{key}/labels/{name}' => ['api/v1/projects/{P}/labels/{LN}', $confirm],
        'GET api/v1/projects/{key}/members' => ['api/v1/projects/{P}/members', []],
        'PATCH api/v1/projects/{key}/members/{email}' => ['api/v1/projects/{P}/members/{E}', ['role' => 'viewer']],
        'DELETE api/v1/projects/{key}/members/{email}' => ['api/v1/projects/{P}/members/{E}', $confirm],
        'GET api/v1/projects/{key}/sprints' => ['api/v1/projects/{P}/sprints', []],
        'POST api/v1/projects/{key}/sprints' => ['api/v1/projects/{P}/sprints', []],
        'GET api/v1/projects/{key}/sprints/{sprint}' => ['api/v1/projects/{P}/sprints/{SN}', []],
        'PATCH api/v1/projects/{key}/sprints/{sprint}' => ['api/v1/projects/{P}/sprints/{SN}', ['goal' => 'x']],
        'DELETE api/v1/projects/{key}/sprints/{sprint}' => ['api/v1/projects/{P}/sprints/{SN}', $confirm],
        'POST api/v1/projects/{key}/sprints/{sprint}/complete' => ['api/v1/projects/{P}/sprints/{SN}/complete', ['confirm' => true, 'move_open_issues_to' => 'backlog']],
        'POST api/v1/projects/{key}/sprints/{sprint}/start' => ['api/v1/projects/{P}/sprints/{SN}/start', []],
        'GET api/v1/projects/{key}/summary' => ['api/v1/projects/{P}/summary', []],

        // Web, as ann signed in with workspace A current.
        'PATCH comments/{comment}' => ['comments/{C}', ['body' => 'x']],
        'DELETE comments/{comment}' => ['comments/{C}', []],
        'POST comments/{comment}/restore' => ['comments/{C}/restore', []],
        'GET dashboard' => ['dashboard', []],
        'GET search' => ['search?q=secret', []],
        'GET issues/{key}' => ['issues/{I}', []],
        'PATCH issues/{key}' => ['issues/{I}', ['title' => 'x']],
        'DELETE issues/{key}' => ['issues/{I}', []],
        'POST issues/{key}/comments' => ['issues/{I}/comments', ['body' => 'x']],
        'POST issues/{key}/move' => ['issues/{I}/move', ['status' => '{COLN}']],
        'POST issues/{key}/rank' => ['issues/{I}/rank', ['sprint' => '{SN}']],
        'POST issues/{key}/restore' => ['issues/{I}/restore', []],
        'GET projects' => ['projects', []],
        'POST projects' => ['projects', ['key' => '{P}', 'name' => 'x'], false],
        'GET projects/{project}' => ['projects/{P}', []],
        'PATCH projects/{project}' => ['projects/{P}', ['name' => 'x']],
        'DELETE projects/{project}' => ['projects/{P}', $confirm],
        'GET projects/{project}/backlog' => ['projects/{P}/backlog', []],
        'POST projects/{project}/columns' => ['projects/{P}/columns', ['name' => 'x']],
        'PATCH projects/{project}/columns/{column}' => ['projects/{P}/columns/{COL}', ['name' => 'x']],
        'DELETE projects/{project}/columns/{column}' => ['projects/{P}/columns/{COL}', []],
        'POST projects/{project}/invitations' => ['projects/{P}/invitations', ['email' => 'x@example.com', 'role' => 'member']],
        'DELETE projects/{project}/invitations/{invitation}' => ['projects/{P}/invitations/{INV}', []],
        'GET projects/{project}/issues' => ['projects/{P}/issues', []],
        'POST projects/{project}/issues' => ['projects/{P}/issues', ['title' => 'x']],
        'POST projects/{project}/labels' => ['projects/{P}/labels', ['name' => 'x']],
        'PATCH projects/{project}/labels/{label}' => ['projects/{P}/labels/{L}', ['name' => 'x']],
        'DELETE projects/{project}/labels/{label}' => ['projects/{P}/labels/{L}', []],
        'PATCH projects/{project}/members/{user}' => ['projects/{P}/members/{U}', ['role' => 'viewer']],
        'DELETE projects/{project}/members/{user}' => ['projects/{P}/members/{U}', []],
        'GET projects/{project}/settings' => ['projects/{P}/settings', []],
        'POST projects/{project}/sprints' => ['projects/{P}/sprints', []],
        'PATCH projects/{project}/sprints/{sprint}' => ['projects/{P}/sprints/{S}', ['goal' => 'x']],
        'DELETE projects/{project}/sprints/{sprint}' => ['projects/{P}/sprints/{S}', []],
        'POST projects/{project}/sprints/{sprint}/complete' => ['projects/{P}/sprints/{S}/complete', ['confirm' => true, 'move_open_issues_to' => 'backlog']],
        'POST projects/{project}/sprints/{sprint}/start' => ['projects/{P}/sprints/{S}/start', []],
        'GET settings/tokens' => ['settings/tokens', []],
        'POST settings/tokens' => ['settings/tokens', ['name' => 'x', 'access' => 'read', 'projects' => ['{P}']]],
        'DELETE settings/tokens/{token}' => ['settings/tokens/{T}', []],
    ];
}

/** Routes that never take a workspace object's id or key, with the reason. */
function isolationExemptRoutes(): array
{
    $auth = 'account sign-in flow, no workspace data';

    return [
        'GET /' => 'public welcome page',
        'GET _scramble/dev-tools/devtools.js' => 'API docs asset',
        'GET docs/api' => 'API docs', 'GET docs/api.json' => 'API docs',
        'GET up' => 'health check', 'GET sanctum/csrf-cookie' => 'CSRF cookie',
        'GET storage/{path}' => 'framework file route', 'PUT storage/{path}' => 'framework file route',
        'GET mcp' => 'MCP transport; every tool is covered below', 'POST mcp' => 'MCP transport; every tool is covered below', 'DELETE mcp' => 'MCP transport; every tool is covered below',
        'GET profile' => 'account page, global by D10', 'PATCH profile' => 'account page, global by D10', 'DELETE profile' => 'account page, global by D10',
        'PUT password' => $auth,
        // Invitations belong to the invitee, who may accept one from any workspace; PR 2 replaces the id with a hashed token.
        'POST invitations/{invitation}/accept' => 'addressed to the invitee across workspaces',
        'GET confirm-password' => $auth, 'POST confirm-password' => $auth, 'POST email/verification-notification' => $auth,
        'GET forgot-password' => $auth, 'POST forgot-password' => $auth, 'GET login' => $auth, 'POST login' => $auth,
        'POST logout' => $auth, 'GET register' => $auth, 'POST register' => $auth, 'POST reset-password' => $auth,
        'GET reset-password/{token}' => $auth, 'GET verify-email' => $auth, 'GET verify-email/{id}/{hash}' => $auth,
    ];
}

function isolationToolCases(): array
{
    $confirm = ['confirm' => true];

    return [
        Tools\ListProjects::class => [],
        Tools\GetProject::class => ['project' => '{P}'],
        Tools\CreateProject::class => ['key' => '{P}', 'name' => 'x', '_compare' => false],
        Tools\UpdateProject::class => ['project' => '{P}', 'name' => 'x'],
        Tools\DeleteProject::class => ['project' => '{P}', ...$confirm],
        Tools\CreateIssue::class => ['project' => '{P}', 'title' => 'x'],
        Tools\GetIssue::class => ['issue' => '{I}'],
        Tools\UpdateIssue::class => ['issue' => '{I}', 'title' => 'x'],
        Tools\DeleteIssue::class => ['issue' => '{I}', ...$confirm],
        Tools\GetIssueActivity::class => ['issue' => '{I}'],
        Tools\AddComment::class => ['issue' => '{I}', 'body' => 'x'],
        Tools\UpdateComment::class => ['comment_id' => '{C}', 'body' => 'x'],
        Tools\DeleteComment::class => ['comment_id' => '{C}', ...$confirm],
        Tools\ListLabels::class => ['project' => '{P}'],
        Tools\CreateLabel::class => ['project' => '{P}', 'name' => 'x'],
        Tools\UpdateLabel::class => ['project' => '{P}', 'label' => '{LN}', 'name' => 'x'],
        Tools\DeleteLabel::class => ['project' => '{P}', 'label' => '{LN}', ...$confirm],
        Tools\GetBoard::class => ['project' => '{P}'],
        Tools\MoveIssue::class => ['issue' => '{I}', 'status' => '{COLN}'],
        Tools\ListColumns::class => ['project' => '{P}'],
        Tools\CreateColumn::class => ['project' => '{P}', 'name' => 'x'],
        Tools\UpdateColumn::class => ['project' => '{P}', 'column' => '{COLN}', 'name' => 'x'],
        Tools\DeleteColumn::class => ['project' => '{P}', 'column' => '{COLN}', ...$confirm],
        Tools\ListSprints::class => ['project' => '{P}'],
        Tools\CreateSprint::class => ['project' => '{P}'],
        Tools\UpdateSprint::class => ['project' => '{P}', 'sprint' => '{SN}', 'goal' => 'x'],
        Tools\DeleteSprint::class => ['project' => '{P}', 'sprint' => '{SN}', ...$confirm],
        Tools\StartSprint::class => ['project' => '{P}', 'sprint' => '{SN}'],
        Tools\CompleteSprint::class => ['project' => '{P}', 'sprint' => '{SN}', 'move_open_issues_to' => 'backlog', ...$confirm],
        Tools\GetBacklog::class => ['project' => '{P}'],
        Tools\RankBacklog::class => ['issue' => '{I}', 'sprint' => '{SN}'],
        Tools\SearchIssues::class => ['q' => 'secret'],
        Tools\MyIssues::class => [],
        Tools\ProjectSummary::class => ['project' => '{P}'],
        Tools\BulkCreateIssues::class => ['project' => '{P}', 'issues' => [['title' => 'x']]],
        Tools\BulkUpdateIssues::class => ['updates' => [['key' => '{I}', 'title' => 'x']]],
    ];
}

const CHILD_PLACEHOLDERS = ['{C}', '{COL}', '{COLN}', '{L}', '{LN}', '{S}', '{SN}', '{T}', '{INV}'];

/** @return array<string, array<string, string>> variant name => placeholder values; paired variants must answer alike. */
function isolationVariants($t): array
{
    $b = [
        '{P}' => 'OPS', '{I}' => 'OPS-1', '{C}' => (string) $t->bComment->id, '{COL}' => (string) $t->bColumn->id, '{COLN}' => 'B-SECRET col',
        '{L}' => (string) $t->bLabel->id, '{LN}' => 'b-secret', '{S}' => (string) $t->bSprint->id, '{SN}' => 'B-SECRET sprint',
        '{T}' => (string) $t->annB->accessToken->id, '{INV}' => (string) $t->bInvitation->id, '{U}' => (string) $t->bob->id, '{E}' => $t->bob->email,
    ];
    $missing = [
        '{P}' => 'NOPE', '{I}' => 'NOPE-1', '{C}' => '999999', '{COL}' => '999998', '{COLN}' => 'No such col',
        '{L}' => '999997', '{LN}' => 'no-such', '{S}' => '999996', '{SN}' => 'No such sprint',
        '{T}' => '999995', '{INV}' => '999994', '{U}' => '999993', '{E}' => 'nobody@example.com',
    ];
    $inA = ['{P}' => 'WEB', '{I}' => 'WEB-1'];

    return [
        'b' => $b, 'missing' => $missing,
        'b child in A' => [...$b, ...$inA], 'missing child in A' => [...$missing, ...$inA],
    ];
}

function fillPlaceholders(mixed $value, array $values): mixed
{
    return json_decode(strtr(json_encode($value), array_map(fn ($v) => trim(json_encode($v), '"'), $values)), true);
}

/** Replaces the variant's own values with "§" so B's answer and the missing answer can be compared. */
function normalizeAnswer(string $text, array $values): string
{
    uasort($values, fn ($a, $b) => strlen($b) <=> strlen($a));
    foreach ($values as $v) {
        $text = ctype_digit($v)
            ? preg_replace('/(?<![\w-])'.$v.'(?![\w-])/', '§', $text)
            : str_ireplace([$v, json_encode($v), rawurlencode($v)], '§', $text);
    }

    return $text;
}

function workspaceSnapshot(Workspace $w): string
{
    $projects = Project::where('workspace_id', $w->id)->orderBy('id')->pluck('id');
    $issues = Issue::withTrashed()->whereIn('project_id', $projects)->orderBy('id')->get();

    return md5(json_encode([
        Project::whereIn('id', $projects)->orderBy('id')->get(), $issues,
        Comment::withTrashed()->whereIn('issue_id', $issues->pluck('id'))->orderBy('id')->get(),
        Label::whereIn('project_id', $projects)->orderBy('id')->get(), Column::whereIn('project_id', $projects)->orderBy('id')->get(),
        Sprint::whereIn('project_id', $projects)->orderBy('id')->get(),
        DB::table('project_user')->whereIn('project_id', $projects)->orderBy('id')->get(),
        DB::table('workspace_user')->where('workspace_id', $w->id)->orderBy('user_id')->get(),
        DB::table('workspace_invitations')->where('workspace_id', $w->id)->orderBy('id')->get(),
        DB::table('personal_access_tokens')->where('workspace_id', $w->id)->orderBy('id')->get(['id', 'name', 'abilities']),
    ]));
}

beforeEach(function () {
    $this->ann = User::factory()->create(['email' => 'ann@example.com']);
    $this->bob = User::factory()->create(['email' => 'bob@example.com']);

    // Workspace B, built first so A is current afterwards.
    $this->b = makeWorkspace($this->bob, 'bravo');
    $bWeb = makeProject($this->bob, 'WEB', 'B-SECRET web');
    makeIssue($bWeb, $this->bob, ['title' => 'B-SECRET web issue']);
    $ops = makeProject($this->bob, 'OPS', 'B-SECRET ops');
    addMember($bWeb, $this->ann, ProjectRole::Admin);
    addMember($ops, $this->ann, ProjectRole::Admin);
    $opsIssue = makeIssue($ops, $this->bob, ['title' => 'B-SECRET ops issue']);
    $this->bComment = app(CommentService::class)->add($opsIssue, $this->bob, 'B-SECRET comment');
    $this->bLabel = app(LabelService::class)->create($ops, ['name' => 'b-secret']);
    $this->bColumn = app(ColumnService::class)->create($ops, ['name' => 'B-SECRET col']);
    $this->bSprint = app(SprintService::class)->create($ops, ['name' => 'B-SECRET sprint']);
    $this->bInvitation = app(MembershipService::class)->invite($ops, $this->bob, 'carol@example.com', ProjectRole::Member);
    $this->annB = app(TokenService::class)->create($this->ann, 'B-SECRET token', true, null);

    // Workspace A: ann's current workspace.
    $this->a = makeWorkspace($this->ann, 'alpha');
    $web = makeProject($this->ann, 'WEB', 'Alpha web');
    $issue = makeIssue($web, $this->ann, ['title' => 'Alpha issue']);
    app(CommentService::class)->add($issue, $this->ann, 'Alpha comment');
    app(LabelService::class)->create($web, ['name' => 'alpha']);
    app(SprintService::class)->create($web, ['name' => 'Alpha sprint']);
    $this->annA = app(TokenService::class)->create($this->ann, 'ann in A', true, null);

    $this->bBefore = workspaceSnapshot($this->b);
});

function answerRoute($t, string $route, array $values): string
{
    [$path, $payload] = isolationRouteCases()[$route];
    $method = explode(' ', $route)[0];
    $uri = '/'.fillPlaceholders($path, $values);
    $payload = fillPlaceholders($payload, $values);

    if (str_starts_with($path, 'api/')) {
        $response = $t->withToken($t->annA->plainTextToken)->json($method, $uri, $payload);
        $answer = $response->getContent();
    } else {
        $response = $t->actingAs($t->ann)->from('/dashboard')->call($method, $uri, $payload);
        $errors = session('errors');
        $errors = $errors instanceof ViewErrorBag ? $errors->getBag('default')->toArray() : $errors;
        // Web errors are a status page or a redirect with session errors; the 404 page itself is static HTML.
        $answer = $response->headers->get('Location').' '.json_encode($errors);
        expect($response->getContent())->not->toContain(B_MARKER);
        $t->flushSession();
    }

    // The status stays out of normalizing: an id can be 404 too.
    return $response->status().' '.normalizeAnswer($answer, $values);
}

function answerTool($t, string $tool, array $values): string
{
    $args = fillPlaceholders(collect(isolationToolCases()[$tool])->except('_compare')->all(), $values);
    Auth::setUser($t->ann->fresh()->withAccessToken($t->annA->accessToken));
    app(CurrentWorkspace::class)->set($t->a);
    $response = PmServer::tool($tool, $args);
    $text = (fn () => json_encode([...$this->content(), ...$this->errors()]))->call($response);

    return normalizeAnswer($text, $values);
}

test('every route and MCP tool has an isolation case or a reasoned exemption', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->flatMap(fn ($r) => collect($r->methods())->reject(fn ($m) => $m === 'HEAD')->map(fn ($m) => "{$m} {$r->uri()}"))
        ->unique();
    $tools = (new ReflectionClass(PmServer::class))->getProperty('tools')->getDefaultValue();

    expect($routes->diff([...array_keys(isolationRouteCases()), ...array_keys(isolationExemptRoutes())])->values()->all())->toBe([])
        ->and(collect($tools)->diff(array_keys(isolationToolCases()))->values()->all())->toBe([]);
});

test('acting in A, a route never shows or changes B and answers B like something missing', function (string $route) {
    $case = isolationRouteCases()[$route];
    $hasChild = collect(CHILD_PLACEHOLDERS)->contains(fn ($p) => str_contains(json_encode($case), $p));
    $answers = collect(isolationVariants($this))
        ->filter(fn ($v, $name) => $hasChild || ! str_contains($name, 'child'))
        ->map(fn ($values) => answerRoute($this, $route, $values));

    foreach ($answers as $answer) {
        expect($answer)->not->toContain(B_MARKER);
    }
    if ($case[2] ?? true) {
        expect($answers['b'])->toBe($answers['missing']);
        if ($hasChild) {
            expect($answers['b child in A'])->toBe($answers['missing child in A']);
        }
    }
    expect(workspaceSnapshot($this->b))->toBe($this->bBefore);
})->with(fn () => array_keys(isolationRouteCases()));

test('acting in A, an MCP tool never shows or changes B and answers B like something missing', function (string $tool) {
    $case = isolationToolCases()[$tool];
    $hasChild = collect(CHILD_PLACEHOLDERS)->contains(fn ($p) => str_contains(json_encode($case), $p));
    $answers = collect(isolationVariants($this))
        ->filter(fn ($v, $name) => $hasChild || ! str_contains($name, 'child'))
        ->map(fn ($values) => answerTool($this, $tool, $values));

    foreach ($answers as $answer) {
        expect($answer)->not->toContain(B_MARKER);
    }
    if ($case['_compare'] ?? true) {
        expect($answers['b'])->toBe($answers['missing']);
        if ($hasChild) {
            expect($answers['b child in A'])->toBe($answers['missing child in A']);
        }
    }
    expect(workspaceSnapshot($this->b))->toBe($this->bBefore);
})->with(fn () => array_keys(isolationToolCases()));

test('the same issue key means the current workspace\'s issue', function () {
    $this->withToken($this->annA->plainTextToken)->getJson('/api/v1/issues/WEB-1')->assertOk()->assertJsonPath('data.title', 'Alpha issue');
    $this->flushHeaders();
    app('auth')->forgetGuards();
    $this->withToken($this->annB->plainTextToken)->getJson('/api/v1/issues/WEB-1')->assertOk()->assertJsonPath('data.title', 'B-SECRET web issue');
});

test('a token cannot be scoped to another workspace\'s project', function () {
    useWorkspace($this->a, $this->ann);

    expect(fn () => app(TokenService::class)->create($this->ann, 'x', true, ['OPS']))
        ->toThrow(ValidationException::class, 'You are not a member of: OPS. Your projects: WEB.');
});

test('a token whose owner left its workspace returns 401 and is deleted, on the API, MCP over HTTP and stdio', function () {
    $mcp = app(TokenService::class)->create($this->ann, 'mcp', true, null);  // workspace A
    $stdio = app(TokenService::class)->create($this->ann, 'stdio', true, null);
    $this->a->members()->detach($this->ann);

    $this->withToken($this->annA->plainTextToken)->getJson('/api/v1/projects')->assertUnauthorized();
    $this->flushHeaders();
    app('auth')->forgetGuards();
    $this->withToken($mcp->plainTextToken)->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
    expect(fn () => LocalPmServer::authenticate($stdio->plainTextToken))->toThrow(RuntimeException::class);

    expect(PersonalAccessToken::whereKey([$this->annA->accessToken->id, $mcp->accessToken->id, $stdio->accessToken->id])->count())->toBe(0)
        ->and(PersonalAccessToken::whereKey($this->annB->accessToken->id)->exists())->toBeTrue();
});
