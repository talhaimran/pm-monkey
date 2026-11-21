<?php

namespace App\Enums;

/**
 * Spec section 4: every plan limit, so Platform management can set them from day one. Step 0 enforces billed_users,
 * viewers, projects and the API limits; the rest arrive with the step in enforcedInStep().
 */
enum Entitlement: string
{
    case BilledUsers = 'billed_users';
    case Viewers = 'viewers';
    case Projects = 'projects';
    case ApiPerMinute = 'api_per_minute';
    case ApiPerDay = 'api_per_day';
    case AgentsPerUser = 'agents_per_user';
    case AgentRunsPerDay = 'agent_runs_per_day';
    case StorageMb = 'storage_mb';
    case MaxFileMb = 'max_file_mb';
    case SavedFiltersPerUser = 'saved_filters_per_user';
    case DashboardsPerUser = 'dashboards_per_user';
    case HistoryDays = 'history_days';
    case CustomFieldsMode = 'custom_fields_mode';
    case CustomFieldsEnabledMax = 'custom_fields_enabled_max';
    case CustomFieldsOwnMax = 'custom_fields_own_max';
    case Webhooks = 'webhooks';
    case Github = 'github';
    case Plan = 'plan';
    case TrialEndsAt = 'trial_ends_at';
    case BillingNotice = 'billing_notice';

    /** int, bool, date, text, or a list of allowed words. */
    public function type(): string|array
    {
        return match ($this) {
            self::Webhooks, self::Github => 'bool',
            self::CustomFieldsMode => ['catalog_only', 'full'],
            self::TrialEndsAt => 'date',
            self::Plan, self::BillingNotice => 'text',
            default => 'int',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::BilledUsers => 'Owners, Admins and anyone Member or Admin in a project, including pending invitations',
            self::Viewers => 'Everyone else in the workspace, including pending invitations',
            self::Projects => 'Projects in the workspace',
            self::ApiPerMinute => 'API and MCP requests per agent per minute',
            self::ApiPerDay => 'API and MCP requests per agent per day',
            self::AgentsPerUser => 'Agents each person may own',
            self::AgentRunsPerDay => 'In-app agent runs per person per day',
            self::StorageMb => 'Attachment storage in MB',
            self::MaxFileMb => 'Largest attachment in MB',
            self::SavedFiltersPerUser => 'Private saved filters per person',
            self::DashboardsPerUser => 'Personal dashboards per person',
            self::HistoryDays => 'Days of history shown in reports and activity',
            self::CustomFieldsMode => 'catalog_only or full',
            self::CustomFieldsEnabledMax => 'Catalog custom fields that can be on at once',
            self::CustomFieldsOwnMax => 'Custom fields a workspace can define itself',
            self::Webhooks => 'Webhooks on or off',
            self::Github => 'GitHub integration on or off',
            self::Plan => 'Plan name, informational',
            self::TrialEndsAt => 'Trial end date, informational',
            self::BillingNotice => 'Billing notice shown to Owners, informational',
        };
    }

    public function enforcedInStep(): ?string
    {
        return match ($this) {
            self::BilledUsers, self::Viewers, self::Projects, self::ApiPerMinute, self::ApiPerDay => '0',
            self::AgentsPerUser, self::AgentRunsPerDay => 'A',
            self::StorageMb, self::MaxFileMb => '6',
            self::SavedFiltersPerUser => '4',
            self::DashboardsPerUser, self::HistoryDays => '10',
            self::CustomFieldsMode, self::CustomFieldsEnabledMax, self::CustomFieldsOwnMax => '11',
            self::Webhooks, self::Github => '7',
            self::Plan, self::TrialEndsAt, self::BillingNotice => null,
        };
    }

    /** D6: what a new Cloud workspace starts with (spec 4, Free column); null for informational keys. */
    public function free(): ?string
    {
        return match ($this) {
            self::BilledUsers => '2', self::Viewers => '3', self::Projects => '1',
            self::ApiPerMinute => '60', self::ApiPerDay => '2000',
            self::AgentsPerUser => '2', self::AgentRunsPerDay => '20',
            self::StorageMb => '1024', self::MaxFileMb => '10',
            self::SavedFiltersPerUser => '2', self::DashboardsPerUser => '1', self::HistoryDays => '90',
            self::CustomFieldsMode => 'catalog_only', self::CustomFieldsEnabledMax => '3', self::CustomFieldsOwnMax => '0',
            self::Webhooks, self::Github => '0',
            self::Plan, self::TrialEndsAt, self::BillingNotice => null,
        };
    }

    /** "2 billed users", "1 project": the counted thing in a plan-limit message. */
    public function count(int $n): string
    {
        $noun = match ($this) {
            self::BilledUsers => 'billed user',
            self::Viewers => 'Viewer',
            self::Projects => 'project',
            self::AgentsPerUser => 'agent',
            default => str_replace('_', ' ', $this->value),
        };

        return "{$n} {$noun}".($n === 1 ? '' : 's');
    }

    /** The stored string for $value, or an error saying what the key takes. @return array{0: ?string, 1: ?string} */
    public function normalize(string $value): array
    {
        $type = $this->type();
        $value = trim($value);

        return match (true) {
            is_array($type) => in_array($value, $type, true) ? [$value, null] : [null, "{$this->value} takes one of: ".implode(', ', $type).'.'],
            $type === 'int' => ctype_digit($value) ? [(string) (int) $value, null] : [null, "{$this->value} takes a whole number of 0 or more."],
            $type === 'bool' => ($b = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)) === null
                ? [null, "{$this->value} takes true or false."] : [$b ? '1' : '0', null],
            $type === 'date' => strtotime($value) ? [date('Y-m-d', strtotime($value)), null] : [null, "{$this->value} takes a date like 2027-01-31."],
            default => [$value, null],
        };
    }
}
