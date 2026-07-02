export type User = {
    id: number;
    name: string;
    username?: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    is_admin?: boolean;
    permissions?: string[];
    can_view_app_features?: boolean;
    can_view_bundle_features?: boolean;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
