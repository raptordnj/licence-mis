import { createRouter, createWebHistory } from 'vue-router';

import type { Permission } from '@/admin/constants/rbac';
import AdminAppShell from '@/admin/components/layout/AdminAppShell.vue';
import LoginView from '@/admin/views/auth/LoginView.vue';
import TwoFactorChallengeView from '@/admin/views/auth/TwoFactorChallengeView.vue';
import DashboardView from '@/admin/views/dashboard/DashboardView.vue';
import DesignSystemView from '@/admin/views/system/DesignSystemView.vue';
import AdminUsersView from '@/admin/views/users/AdminUsersView.vue';
import AuditLogsView from '@/admin/views/audit/AuditLogsView.vue';
import ItemDetailView from '@/admin/views/items/ItemDetailView.vue';
import ItemsView from '@/admin/views/items/ItemsView.vue';
import CheckerView from '@/admin/views/purchases/CheckerView.vue';
import PurchaseDetailView from '@/admin/views/purchases/PurchaseDetailView.vue';
import PurchasesView from '@/admin/views/purchases/PurchasesView.vue';
import LicenseDetailView from '@/admin/views/licenses/LicenseDetailView.vue';
import LicensesView from '@/admin/views/licenses/LicensesView.vue';
import ValidationLogsView from '@/admin/views/logs/ValidationLogsView.vue';
import TwoFactorRecoveryCodesView from '@/admin/views/settings/TwoFactorRecoveryCodesView.vue';
import TwoFactorSetupView from '@/admin/views/settings/TwoFactorSetupView.vue';
import SettingsView from '@/admin/views/settings/SettingsView.vue';

interface AuthStoreLike {
    initialized: boolean;
    isAuthenticated: boolean;
    pendingTwoFactor: {
        email: string;
        password: string;
        remember_me: boolean;
    } | null;
    bootstrap: () => Promise<void>;
    // eslint-disable-next-line no-unused-vars
    can: (permission: Permission) => boolean;
}

export const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/admin/login',
            name: 'admin-login',
            component: LoginView,
            meta: {
                guestOnly: true,
                title: 'Login',
            },
        },
        {
            path: '/admin/login/2fa',
            name: 'admin-login-2fa',
            component: TwoFactorChallengeView,
            meta: {
                guestOnly: true,
                requiresTwoFactorChallenge: true,
                title: 'Two-Factor Challenge',
            },
        },
        {
            path: '/admin',
            component: AdminAppShell,
            meta: {
                requiresAuth: true,
            },
            children: [
                {
                    path: '',
                    redirect: '/admin/dashboard',
                },
                {
                    path: 'dashboard',
                    name: 'admin-dashboard',
                    component: DashboardView,
                    meta: {
                        title: 'Dashboard',
                        permission: 'dashboard:view',
                    },
                },
                {
                    path: 'items',
                    name: 'admin-items',
                    component: ItemsView,
                    meta: {
                        title: 'Envato Items',
                        permission: 'items:view',
                    },
                },
                {
                    path: 'items/:id',
                    name: 'admin-item-detail',
                    component: ItemDetailView,
                    meta: {
                        title: 'Item Detail',
                        permission: 'items:view',
                    },
                },
                {
                    path: 'checker',
                    name: 'admin-checker',
                    component: CheckerView,
                    meta: {
                        title: 'Checker',
                        permission: 'purchases:view',
                    },
                },
                {
                    path: 'purchases',
                    name: 'admin-purchases',
                    component: PurchasesView,
                    meta: {
                        title: 'Purchases',
                        permission: 'purchases:view',
                    },
                },
                {
                    path: 'purchases/:id',
                    name: 'admin-purchase-detail',
                    component: PurchaseDetailView,
                    meta: {
                        title: 'Purchase Detail',
                        permission: 'purchases:view',
                    },
                },
                {
                    path: 'licenses',
                    name: 'admin-licenses',
                    component: LicensesView,
                    meta: {
                        title: 'Licenses',
                        permission: 'licenses:view',
                    },
                },
                {
                    path: 'licenses/:id',
                    name: 'admin-license-detail',
                    component: LicenseDetailView,
                    meta: {
                        title: 'License Detail',
                        permission: 'licenses:view',
                    },
                },
                {
                    path: 'validation-logs',
                    name: 'admin-validation-logs',
                    component: ValidationLogsView,
                    meta: {
                        title: 'Validation Logs',
                        permission: 'validation-logs:view',
                    },
                },
                {
                    path: 'admin-users',
                    name: 'admin-users',
                    component: AdminUsersView,
                    meta: {
                        title: 'Admin Users',
                        permission: 'admin-users:view',
                    },
                },
                {
                    path: 'audit-logs',
                    name: 'admin-audit-logs',
                    component: AuditLogsView,
                    meta: {
                        title: 'Audit Logs',
                        permission: 'audit-logs:view',
                    },
                },
                {
                    path: 'settings',
                    name: 'admin-settings',
                    component: SettingsView,
                    meta: {
                        title: 'Settings',
                        permission: 'settings:view',
                    },
                },
                {
                    path: 'settings/2fa/setup',
                    name: 'admin-settings-2fa-setup',
                    component: TwoFactorSetupView,
                    meta: {
                        title: '2FA Setup',
                        permission: 'settings:manage',
                    },
                },
                {
                    path: 'settings/2fa/recovery-codes',
                    name: 'admin-settings-2fa-recovery',
                    component: TwoFactorRecoveryCodesView,
                    meta: {
                        title: 'Recovery Codes',
                        permission: 'settings:manage',
                    },
                },
                {
                    path: 'design-system',
                    name: 'admin-design-system',
                    component: DesignSystemView,
                    meta: {
                        title: 'Design System',
                        permission: 'dashboard:view',
                    },
                },
            ],
        },
        {
            path: '/admin/:pathMatch(.*)*',
            redirect: '/admin/dashboard',
        },
    ],
});

export const registerAuthGuard = (authStore: AuthStoreLike): void => {
    router.beforeEach(async (to) => {
        if (!authStore.initialized) {
            await authStore.bootstrap();
        }

        if (to.meta.requiresAuth === true && !authStore.isAuthenticated) {
            return '/admin/login';
        }

        if (to.meta.guestOnly === true && authStore.isAuthenticated) {
            return '/admin/dashboard';
        }

        if (to.meta.requiresTwoFactorChallenge === true && authStore.pendingTwoFactor === null) {
            return '/admin/login';
        }

        const permission = to.meta.permission;

        if (typeof permission === 'string' && !authStore.can(permission as Permission)) {
            return '/admin/dashboard';
        }

        return true;
    });
};
