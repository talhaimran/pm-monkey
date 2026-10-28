<?php

namespace Database\Seeders;

use App\Enums\ProjectRole;
use App\Models\User;
use App\Services\CommentService;
use App\Services\IssueService;
use App\Services\LabelService;
use App\Services\ProjectService;
use App\Services\SprintService;
use App\Services\TokenService;
use Illuminate\Database\Seeder;

/**
 * Demo workspace built through the real services, so keys, ranks and the activity log are valid.
 * Logins (local only): demo@example.com / password; alex@example.com and sam@example.com use the same password.
 */
class DemoSeeder extends Seeder
{
    public function run(
        ProjectService $projects,
        IssueService $issues,
        LabelService $labels,
        SprintService $sprints,
        CommentService $comments,
        TokenService $tokens,
    ): void {
        if (User::where('email', 'demo@example.com')->exists()) {
            $this->command?->warn('Demo data already seeded; run `php artisan migrate:fresh --seed` to start over.');

            return;
        }

        $demo = User::factory()->create(['name' => 'Demo User', 'email' => 'demo@example.com']);
        $alex = User::factory()->create(['name' => 'Alex Rivera', 'email' => 'alex@example.com']);
        $sam = User::factory()->create(['name' => 'Sam Chen', 'email' => 'sam@example.com']);

        $project = $projects->create($demo, [
            'key' => 'WEB',
            'name' => 'Website Relaunch',
            'description' => "Rebuild the marketing site and customer portal.\n\n- New design system\n- Faster checkout\n- Accessible by default",
        ]);
        $project->members()->attach($alex, ['role' => ProjectRole::Member->value]);
        $project->members()->attach($sam, ['role' => ProjectRole::Viewer->value]);

        foreach (['frontend' => '#3b82f6', 'backend' => '#10b981', 'ux' => '#a855f7', 'devops' => '#f59e0b', 'bug-bash' => '#ef4444'] as $name => $color) {
            $labels->create($project, ['name' => $name, 'color' => $color]);
        }

        $sprint1 = $sprints->create($project, ['goal' => 'Ship the new checkout behind a feature flag.']);
        $sprint2 = $sprints->create($project, ['goal' => 'Accessibility pass and performance budget.']);

        $d = fn (int $days) => now()->addDays($days)->toDateString();
        $me = 'demo@example.com';
        $al = 'alex@example.com';

        // [title, type, priority, status, assignee, points, labels, epic, sprint, due, description]
        $rows = [
            ['Design system', 'epic', 'high', 'In Progress', $me, null, ['ux'], null, null, null, 'Tokens, components and docs shared by marketing and portal.'],
            ['Checkout redesign', 'epic', 'highest', 'In Progress', $al, null, ['frontend', 'backend'], null, null, null, 'Fewer steps, saved addresses, Apple Pay.'],
            ['Performance & accessibility', 'epic', 'medium', 'To Do', null, null, [], null, null, null, 'Hit Lighthouse 90+ and WCAG AA.'],
            ['Define color and spacing tokens', 'story', 'high', 'Done', $me, 3, ['ux'], 'WEB-1', $sprint1, null, null],
            ['Button and input components', 'story', 'high', 'In Review', $me, 5, ['frontend', 'ux'], 'WEB-1', $sprint1, $d(3), 'All states: hover, focus, disabled, loading.'],
            ['Modal and dropdown components', 'story', 'medium', 'In Progress', $al, 5, ['frontend'], 'WEB-1', $sprint1, $d(6), null],
            ['Single-page checkout layout', 'story', 'highest', 'In Progress', $al, 8, ['frontend'], 'WEB-2', $sprint1, $d(5), "Collapse shipping + payment into one page.\n\n**Acceptance:** completes in under 60 seconds on mobile."],
            ['Saved addresses API', 'story', 'high', 'Done', $me, 5, ['backend'], 'WEB-2', $sprint1, null, null],
            ['Apple Pay integration', 'story', 'high', 'To Do', $me, 8, ['backend', 'frontend'], 'WEB-2', $sprint1, $d(9), null],
            ['Cart total rounds wrong with 3+ discounts', 'bug', 'highest', 'In Progress', $me, 2, ['backend', 'bug-bash'], 'WEB-2', $sprint1, $d(1), "Steps: add three 10% codes, total is off by one cent.\n\n```\nexpected 72.90, got 72.91\n```"],
            ['Feature flag for new checkout', 'task', 'medium', 'Done', $al, 1, ['devops'], 'WEB-2', $sprint1, null, null],
            ['Checkout analytics events', 'task', 'low', 'To Do', null, 2, ['frontend'], 'WEB-2', $sprint1, null, null],
            ['Focus ring missing on dark buttons', 'bug', 'medium', 'To Do', $al, 1, ['ux', 'bug-bash'], 'WEB-1', $sprint1, null, null],
            ['Audit color contrast', 'task', 'medium', 'To Do', null, 3, ['ux'], 'WEB-3', $sprint2, null, null],
            ['Lazy-load product images', 'story', 'medium', 'To Do', $al, 3, ['frontend'], 'WEB-3', $sprint2, null, null],
            ['Set a JS bundle budget in CI', 'task', 'low', 'To Do', null, 2, ['devops'], 'WEB-3', $sprint2, null, null],
            ['Screen reader labels for cart', 'story', 'high', 'To Do', $me, 3, ['frontend', 'ux'], 'WEB-3', $sprint2, null, null],
            ['Migrate legacy blog posts', 'task', 'low', 'To Do', null, 5, ['backend'], null, null, null, null],
            ['Cookie banner blocks footer on iOS', 'bug', 'high', 'To Do', null, 1, ['frontend', 'bug-bash'], null, null, $d(-2), null],
            ['Staging deploys take 15 minutes', 'task', 'medium', 'To Do', $al, 3, ['devops'], null, null, null, null],
            ['Newsletter signup double-submits', 'bug', 'medium', 'To Do', null, 1, ['frontend'], null, null, null, null],
            ['Write component usage docs', 'task', 'low', 'To Do', $me, 2, ['ux'], 'WEB-1', null, null, null],
            ['Gift card support', 'story', 'low', 'To Do', null, 8, ['backend'], 'WEB-2', null, null, null],
            ['Old order emails show wrong logo', 'bug', 'lowest', 'Done', $al, 1, ['bug-bash'], null, null, null, null],
        ];

        foreach ($rows as [$title, $type, $priority, $status, $assignee, $points, $labelNames, $epic, $sprint, $due, $description]) {
            $issues->create($project, $demo, array_filter([
                'title' => $title, 'type' => $type, 'priority' => $priority, 'status' => $status,
                'assignee' => $assignee, 'story_points' => $points, 'labels' => $labelNames, 'epic' => $epic,
                'sprint' => $sprint?->name, 'due_date' => $due, 'description' => $description,
            ], fn ($v) => $v !== null && $v !== []));
        }

        // Sub-tasks (one level): WEB-7 checkout layout, WEB-10 rounding bug.
        foreach ([
            ['Shipping form step', 'WEB-7', 'Done', $al],
            ['Payment form step', 'WEB-7', 'In Progress', $al],
            ['Order summary sidebar', 'WEB-7', 'To Do', null],
            ['Write failing test for rounding', 'WEB-10', 'Done', $me],
            ['Switch discount math to integer cents', 'WEB-10', 'In Progress', $me],
        ] as [$title, $parent, $status, $assignee]) {
            $issues->create($project, $demo, array_filter([
                'title' => $title, 'parent' => $parent, 'status' => $status, 'assignee' => $assignee,
                'sprint' => $sprint1->name, 'story_points' => 1,
            ]));
        }

        $sprints->start($sprint1, ['start_date' => now()->subDays(4)->toDateString(), 'end_date' => now()->addDays(10)->toDateString()]);

        $comment = fn (string $key, User $who, string $body) => $comments->add($issues->resolve($demo, $key), $who, $body);
        $comment('WEB-10', $alex, 'Reproduced on staging. Looks like we round each discount instead of the total.');
        $comment('WEB-10', $demo, 'Agreed. Moving the math to integer cents; sub-tasks added.');
        $comment('WEB-7', $demo, "Design review notes:\n\n1. Keep the summary sticky on desktop\n2. Show delivery estimate before payment");
        $comment('WEB-5', $alex, 'Looks great. One nit: the loading spinner shifts the label by 2px.');

        $token = $tokens->create($demo, 'Demo token', write: true, projectKeys: null);

        $this->command?->newLine();
        $this->command?->info('Demo workspace ready: project WEB with '.$project->issues()->count().' issues, active sprint "'.$sprint1->name.'".');
        $this->command?->line('  Log in at '.url('/login').' as demo@example.com / password');
        $this->command?->line('  Demo API token: '.$token->plainTextToken);
        $this->command?->line('  REST: curl -H "Authorization: Bearer <token>" '.url('/api/v1/projects'));
        $this->command?->line('  MCP over HTTP: '.url('/mcp').' (Authorization: Bearer <token>)');
        $this->command?->line('  MCP over stdio: PM_MCP_TOKEN=<token> php artisan mcp:start pm');
        $this->command?->warn('  The token is shown only now. Revoke it at /settings/tokens when you are done.');
    }
}
