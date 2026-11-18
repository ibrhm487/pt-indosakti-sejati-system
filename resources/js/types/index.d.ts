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
    info?: string | null;
    warning?: string | null;
}

export interface EventBrand {
    id: number;
    name: string;
    slug: string;
}

export interface EventGroupItem {
    id: number;
    event_name: string;
    event_date: string | null;
    [key: string]: unknown;
}

export interface EventDateGroup {
    key: string;
    date: string | null;
    count: number;
    events: EventGroupItem[];
}

export interface EventTypeGroup {
    key: string;
    event_type_id: number | null;
    event_type_name: string;
    requires_school: boolean;
    count: number;
    dates: EventDateGroup[];
}

export interface EventBrandGroup {
    key: string;
    brand_id: number | null;
    brand_name: string;
    count: number;
    event_types: EventTypeGroup[];
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: AuthUser;
        permissions: string[];
    };
    flash: FlashProps;
    eventBrands: EventBrand[];
    app: {
        name: string;
    };
};
