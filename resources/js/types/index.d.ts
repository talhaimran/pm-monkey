export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
}

export type WorkspaceRole = 'owner' | 'admin' | 'member';

export interface CurrentWorkspace {
    id: number;
    name: string;
    slug: string;
    role: WorkspaceRole | null;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    workspace: CurrentWorkspace | null;
    workspaces: { name: string; slug: string }[];
    edition: 'self_hosted' | 'cloud';
};

export type ProjectRole = 'admin' | 'member' | 'viewer';

export interface Member {
    id: number;
    name: string;
    email: string;
    role: ProjectRole;
}

export interface Project {
    id: number;
    key: string;
    name: string;
    description: string | null;
    my_role: ProjectRole | null;
    members?: Member[];
}

export interface Invitation {
    id: number;
    email: string;
    role: ProjectRole;
    project?: { key: string; name: string };
}

export interface UserSummary {
    id: number;
    name: string;
    email: string;
}

export interface Label {
    id: number;
    name: string;
    color: string;
}

export interface IssueLink {
    key: string;
    title: string;
    type?: string;
    status?: string;
}

export interface Comment {
    id: number;
    body: string;
    body_html?: string;
    can_edit?: boolean;
    author: UserSummary | null;
    agent?: AgentRef | null;
    created_at: string;
}

/** The agent that acted, when one did: its name and its owner's name. */
export interface AgentRef {
    name: string;
    owner: string;
}

export interface Issue {
    id: number;
    key: string;
    project: string;
    title: string;
    description: string | null;
    description_html?: string;
    type: string;
    priority: string;
    status: string;
    assignee: UserSummary | null;
    reporter: UserSummary | null;
    labels: string[];
    story_points: number | null;
    due_date: string | null;
    epic: IssueLink | null;
    parent: IssueLink | null;
    sprint: { id: number; name: string; state: string } | null;
    subtasks?: IssueLink[];
    epic_issues?: IssueLink[];
    comments?: Comment[];
    created_at: string;
    updated_at: string;
}

export interface ActivityEntry {
    id: number;
    action: string;
    changes: Record<string, { from: unknown; to: unknown }> | null;
    user: UserSummary | null;
    agent?: AgentRef | null;
    via: 'web' | 'api' | 'mcp';
    token_name: string | null;
    created_at: string;
}

export interface IssueOptions {
    columns: string[];
    members: { name: string; email: string }[];
    labels: Label[];
    types: string[];
    priorities: string[];
    sprints: string[];
}
