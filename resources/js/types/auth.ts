export type User = {
    id: number;
    name: string;
    last_name?: string;
    username: string;
    email: string;
    phone?: string;
    avatar?: string;
    bio?: string;
    role: 'user' | 'admin' | 'moderator';
    reliability_level: 'new' | 'active' | 'reliable';
    reputation_score: number;
    status: 'active' | 'suspended' | 'banned';
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
