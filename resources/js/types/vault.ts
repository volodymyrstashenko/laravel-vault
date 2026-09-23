// Types for the thevps/laravel-vault frontend (password manager + Wi-Fi directory).
// Published to resources/js/types/vault.ts — the package's Vue/lib stubs import from
// '@/types/vault' directly (not the host's barrel) so a host that already has its own
// credential/wifi types keeps them without a name collision.

export type CredentialAccessLevel = 'view' | 'edit' | 'manage';
export type CredentialVisibility = 'group' | 'public';

export interface CredentialGroupRef {
    id: number;
    name: string;
}

export type CredentialFieldType = 'text' | 'hidden' | 'boolean';

export interface CredentialCustomField {
    label: string;
    value: string | boolean;
    type: CredentialFieldType;
}

export interface CredentialAttachment {
    id: number;
    file_name: string;
    size: number;
    mime_type: string;
    url: string;
    created_at: string;
}

export interface CredentialSummary {
    [key: string]: unknown;
    id: number;
    name: string;
    login: string | null;
    url: string | null;
    visibility: CredentialVisibility;
    icon_url: string | null;
    groups: CredentialGroupRef[];
    access_level: CredentialAccessLevel;
    created_by: { id: number; name: string } | null;
    created_at: string;
}

/** One user granted DIRECT access to a single credential — no group involved. */
export interface CredentialDirectUser {
    id: number;
    name: string;
    email: string;
    access_level: CredentialAccessLevel;
}

/**
 * A host-specific record pointing AT this credential (e.g. a device whose `credential_id`
 * references it) — passed separately from `CredentialDetail` (own Inertia prop, `linkedAssets`)
 * since the package itself has no concept of "assets"; see `config('vault.linked_assets_resolver')`.
 */
export interface LinkedAsset {
    id: number;
    name: string;
    subtitle: string | null;
    url: string | null;
}

export interface CredentialDetail {
    id: number;
    name: string;
    login: string | null;
    password: string | null;
    totp_secret: string | null;
    url: string | null;
    visibility: CredentialVisibility;
    icon_url: string | null;
    notes: string | null;
    custom_fields: CredentialCustomField[];
    attachments: CredentialAttachment[];
    groups: CredentialGroupRef[];
    direct_users: CredentialDirectUser[];
    access_level: CredentialAccessLevel;
    created_by: { id: number; name: string } | null;
    created_at: string;
}

export interface CredentialGroupMember {
    id: number;
    name: string;
    email: string;
    access_level: CredentialAccessLevel;
}

export interface CredentialGroupSummary {
    [key: string]: unknown;
    id: number;
    name: string;
    description: string | null;
    members_count: number;
    credentials_count: number;
    access_level: CredentialAccessLevel;
    created_at: string;
}

export interface CredentialGroupDetail {
    id: number;
    name: string;
    description: string | null;
    access_level: CredentialAccessLevel;
    members: CredentialGroupMember[];
    credentials: { id: number; name: string; login: string | null; icon_url: string | null }[];
}

// --- Wi-Fi ---

export type WifiSecurity = 'WPA' | 'WEP' | 'nopass';
export type WifiBand = '2.4' | '5' | '6';

export interface WifiNetwork {
    id: number;
    ssid: string;
    password: string | null;
    security: WifiSecurity;
    is_hidden: boolean;
    bands: WifiBand[];
    /** Strongest band (6 > 5 > 2.4), or null — drives the single list icon. */
    max_band: WifiBand | null;
    location: string | null;
    notes: string | null;
    visibility: CredentialVisibility;
    groups: CredentialGroupRef[];
    can_manage: boolean;
}

/** Compact shape from GET wifi/quick (AppWifiQuick.vue). */
export interface WifiNetworkQuick {
    id: number;
    ssid: string;
    password: string | null;
    security: WifiSecurity;
    is_hidden: boolean;
    location: string | null;
    bands: WifiBand[];
    max_band: WifiBand | null;
}
