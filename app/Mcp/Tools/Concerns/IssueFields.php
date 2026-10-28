<?php

namespace App\Mcp\Tools\Concerns;

use App\Enums\IssuePriority;
use App\Enums\IssueType;
use Illuminate\Contracts\JsonSchema\JsonSchema;

trait IssueFields
{
    /** Writable issue fields shared by create_issue and update_issue. */
    protected function issueFields(JsonSchema $schema): array
    {
        return [
            'description' => $schema->string()->description('Markdown description.'),
            'type' => $schema->string()->enum(IssueType::class)->description('Default task.'),
            'priority' => $schema->string()->enum(IssuePriority::class)->description('Default medium.'),
            'status' => $schema->string()->description('Board column name, e.g. "To Do", "In Progress". Case-insensitive.'),
            'assignee' => $schema->string()->nullable()->description('Assignee email (must be a project member); null to unassign.'),
            'labels' => $schema->array()->items($schema->string())->description('Label names; replaces the current labels. Labels must exist.'),
            'story_points' => $schema->integer()->min(0)->max(1000)->nullable(),
            'due_date' => $schema->string()->format('date')->nullable()->description('YYYY-MM-DD.'),
            'epic' => $schema->string()->nullable()->description('Key of an epic in the same project, e.g. WEB-3; null to unlink.'),
            'parent' => $schema->string()->nullable()->description('Key of the parent issue to make this a sub-task (one level deep); null to detach.'),
        ];
    }
}
