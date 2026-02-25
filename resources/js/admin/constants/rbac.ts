import type { RoleName } from '@/admin/types/api';

export type Permission =
    | 'dashboard:view'
    | 'items:view'
    | 'items:manage'
    | 'purchases:view'
    | 'purchases:manage'
    | 'licenses:view'
    | 'licenses:revoke'
    | 'licenses:reset-domain'
    | 'validation-logs:view'
    | 'admin-users:view'
    | 'admin-users:manage'
    | 'audit-logs:view'
    | 'settings:view'
    | 'settings:manage';

const permissionMap: Record<string, Permission[]> = {
    'super-admin': [
        'dashboard:view',
        'items:view',
        'items:manage',
        'purchases:view',
        'purchases:manage',
        'licenses:view',
        'licenses:revoke',
        'licenses:reset-domain',
        'validation-logs:view',
        'admin-users:view',
        'admin-users:manage',
        'audit-logs:view',
        'settings:view',
        'settings:manage',
    ],
    admin: [
        'dashboard:view',
        'items:view',
        'items:manage',
        'purchases:view',
        'purchases:manage',
        'licenses:view',
        'licenses:revoke',
        'licenses:reset-domain',
        'validation-logs:view',
        'admin-users:view',
        'audit-logs:view',
        'settings:view',
        'settings:manage',
    ],
    support: [
        'dashboard:view',
        'items:view',
        'purchases:view',
        'licenses:view',
        'validation-logs:view',
        'audit-logs:view',
    ],
};

export const hasPermission = (role: RoleName | string | null, permission: Permission): boolean => {
    if (role === null || role === '') {
        return false;
    }

    const permissions = permissionMap[role];

    if (permissions === undefined) {
        return false;
    }

    return permissions.includes(permission);
};
