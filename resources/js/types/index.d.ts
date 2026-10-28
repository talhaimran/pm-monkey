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
