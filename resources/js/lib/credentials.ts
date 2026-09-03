import type { BadgeVariants } from '@/components/ui/badge';
import type { CredentialAccessLevel, CredentialVisibility } from '@/types/vault';

export const ACCESS_LEVEL_OPTIONS: { value: CredentialAccessLevel; label: string }[] = [
    { value: 'view', label: 'Перегляд' },
    { value: 'edit', label: 'Редагування' },
    { value: 'manage', label: 'Керування' },
];

const ACCESS_LEVEL_BADGE: Record<CredentialAccessLevel, NonNullable<BadgeVariants['variant']>> = {
    view: 'outline',
    edit: 'warning',
    manage: 'success',
};

export function accessLevelLabel(level: string | null | undefined): string {
    if (!level) return '—';
    return ACCESS_LEVEL_OPTIONS.find((o) => o.value === level)?.label ?? level;
}

export function accessLevelBadgeVariant(level: string): NonNullable<BadgeVariants['variant']> {
    return ACCESS_LEVEL_BADGE[level as CredentialAccessLevel] ?? 'default';
}

/** view=1 < edit=2 < manage=3 — the same rank as CredentialGroup::accessRank() on the backend. */
export function accessRank(level: string | null | undefined): number {
    return level === 'manage' ? 3 : level === 'edit' ? 2 : level === 'view' ? 1 : 0;
}

export function canEditWith(level: string | null | undefined): boolean {
    return accessRank(level) >= 2;
}

export function canManageWith(level: string | null | undefined): boolean {
    return level === 'manage';
}

export const VISIBILITY_OPTIONS: { value: CredentialVisibility; label: string; hint: string }[] = [
    { value: 'group', label: 'Група', hint: 'Бачать лише учасники обраних груп' },
    { value: 'public', label: 'Публічно', hint: 'Бачать усі співробітники (перегляд)' },
];

export function visibilityLabel(visibility: string | null | undefined): string {
    return VISIBILITY_OPTIONS.find((o) => o.value === visibility)?.label ?? '—';
}
