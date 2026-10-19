export interface AuthUser {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string | null;
    phone?: string | null;
    roles: { id: number; slug: string; name: string }[];
    active_role?: { id?: number; slug: string; name: string } | null;
}

export interface FlashProps {
    success?: string | null;
    error?: string | null;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: AuthUser;
        permissions: string[];
    };
    flash: FlashProps;
    app: {
        name: string;
    };
};
