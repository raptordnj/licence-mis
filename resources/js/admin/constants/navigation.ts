import type { Component } from 'vue';
import {
    Activity,
    BadgeCheck,
    Boxes,
    FileCheck2,
    KeyRound,
    LayoutDashboard,
    ScanSearch,
    Settings,
    ShieldUser,
} from 'lucide-vue-next';

import type { Permission } from '@/admin/constants/rbac';

export interface SidebarItem {
    label: string;
    to: string;
    icon: Component;
    permission: Permission;
}

export const sidebarNavigation: SidebarItem[] = [
    {
        label: 'Dashboard',
        to: '/admin/dashboard',
        icon: LayoutDashboard,
        permission: 'dashboard:view',
    },
    {
        label: 'Envato Items',
        to: '/admin/items',
        icon: Boxes,
        permission: 'items:view',
    },
    {
        label: 'Checker',
        to: '/admin/checker',
        icon: ScanSearch,
        permission: 'purchases:view',
    },
    {
        label: 'Purchases',
        to: '/admin/purchases',
        icon: BadgeCheck,
        permission: 'purchases:view',
    },
    {
        label: 'Licenses',
        to: '/admin/licenses',
        icon: KeyRound,
        permission: 'licenses:view',
    },
    {
        label: 'Validation Logs',
        to: '/admin/validation-logs',
        icon: FileCheck2,
        permission: 'validation-logs:view',
    },
    {
        label: 'Admin Users',
        to: '/admin/admin-users',
        icon: ShieldUser,
        permission: 'admin-users:view',
    },
    {
        label: 'Audit Logs',
        to: '/admin/audit-logs',
        icon: Activity,
        permission: 'audit-logs:view',
    },
    {
        label: 'Settings',
        to: '/admin/settings',
        icon: Settings,
        permission: 'settings:view',
    },
];
