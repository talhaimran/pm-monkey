export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
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
    created_at: string;
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
}
