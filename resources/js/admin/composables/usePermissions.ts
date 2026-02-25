import { computed } from 'vue';

import { hasPermission, type Permission } from '@/admin/constants/rbac';
import { useAuthStore } from '@/admin/stores/auth';

export const usePermissions = () => {
    const authStore = useAuthStore();

    const role = computed(() => authStore.admin?.role ?? null);

    const can = (permission: Permission): boolean => hasPermission(role.value, permission);

    return {
        role,
        can,
    };
};
