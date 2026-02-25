<template>
    <section class="space-y-4">
        <PageHeader
            title="Admin Users"
            eyebrow="RBAC"
            description="Manage admin accounts, role assignments, and access controls."
        >
            <template #actions>
                <UiButton :disabled="!canManageUsers" @click="openCreate">Create User</UiButton>
            </template>
        </PageHeader>

        <FilterBar>
            <div class="md:col-span-6">
                <UiInput v-model="query.search" label="Search" placeholder="Name or email" />
            </div>
            <div class="md:col-span-3">
                <UiSelect v-model="query.role" label="Role" :options="roleOptions" />
            </div>
            <div class="md:col-span-3">
                <UiSelect v-model="query.twoFactor" label="2FA" :options="twoFactorOptions" />
            </div>
        </FilterBar>

        <ErrorBanner v-if="adminUsersStore.error !== null" :message="adminUsersStore.error.message" />

        <DataTable
            :columns="columns"
            :rows="rows"
            :loading="adminUsersStore.loading"
            empty-title="No admin users"
            empty-description="Create admin users and assign role-based permissions."
        >
            <template #cell-role="{ value }">
                <StatusBadge :value="String(value)" />
            </template>
            <template #cell-two_factor_enabled="{ value }">
                <StatusBadge :value="value ? 'enabled' : 'disabled'" />
            </template>
            <template #cell-last_login_at="{ value }">
                {{ formatDateTime(value ? String(value) : null) }}
            </template>
            <template #cell-actions="{ row }">
                <div class="flex justify-end gap-2">
                    <UiButton variant="secondary" :disabled="!canManageUsers" @click="openRoleEdit(row)">
                        Edit Role
                    </UiButton>
                    <UiButton variant="danger" :disabled="!canManageUsers" @click="disableUser(row)">Disable</UiButton>
                </div>
            </template>
        </DataTable>

        <div class="flex items-center justify-end gap-2">
            <UiButton variant="secondary" :disabled="query.page <= 1" @click="query.page -= 1">Previous</UiButton>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Page {{ adminUsersStore.response.current_page }} of {{ adminUsersStore.response.last_page }}
            </p>
            <UiButton
                variant="secondary"
                :disabled="adminUsersStore.response.current_page >= adminUsersStore.response.last_page"
                @click="query.page += 1"
            >
                Next
            </UiButton>
        </div>

        <UiModal :open="createOpen" title="Create Admin User" description="Super-admin only action." @update:open="createOpen = $event">
            <div class="grid gap-3">
                <UiInput v-model="createForm.name" label="Name" required />
                <UiInput v-model="createForm.email" label="Email" type="email" required />
                <UiSelect v-model="createForm.role" label="Role" :options="roleOptions.filter((option) => option.value !== '')" />
            </div>
            <div class="flex justify-end gap-2">
                <UiButton variant="secondary" @click="createOpen = false">Cancel</UiButton>
                <UiButton :loading="adminUsersStore.saving" @click="createUser">Create</UiButton>
            </div>
        </UiModal>

        <UiModal :open="roleOpen" title="Edit User Role" description="Super-admin only action." @update:open="roleOpen = $event">
            <UiSelect v-model="roleForm.role" label="Role" :options="roleOptions.filter((option) => option.value !== '')" />
            <div class="flex justify-end gap-2">
                <UiButton variant="secondary" @click="roleOpen = false">Cancel</UiButton>
                <UiButton :loading="adminUsersStore.saving" @click="saveRole">Save Role</UiButton>
            </div>
        </UiModal>
    </section>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';

import DataTable, { type DataTableColumn } from '@/admin/components/data/DataTable.vue';
import FilterBar from '@/admin/components/data/FilterBar.vue';
import ErrorBanner from '@/admin/components/feedback/ErrorBanner.vue';
import StatusBadge from '@/admin/components/feedback/StatusBadge.vue';
import PageHeader from '@/admin/components/layout/PageHeader.vue';
import UiButton from '@/admin/components/ui/UiButton.vue';
import UiInput from '@/admin/components/ui/UiInput.vue';
import UiModal from '@/admin/components/ui/UiModal.vue';
import UiSelect from '@/admin/components/ui/UiSelect.vue';
import { useComputedField } from '@/admin/composables/useComputedField';
import { useDebounced } from '@/admin/composables/useDebounced';
import { useQuerySync } from '@/admin/composables/useQuerySync';
import { useAuthStore } from '@/admin/stores/auth';
import { useAdminUsersStore } from '@/admin/stores/adminUsers';
import { useToastStore } from '@/admin/stores/toast';
import { formatDateTime } from '@/admin/utils/format';

const authStore = useAuthStore();
const adminUsersStore = useAdminUsersStore();
const toastStore = useToastStore();

const canManageUsers = computed<boolean>(() => authStore.role === 'super-admin');

const query = useQuerySync({
    page: 1,
    perPage: 15,
    search: '',
    role: '',
    twoFactor: '',
});

const debouncedSearch = useDebounced(useComputedField(query, 'search'), 280);

const columns: DataTableColumn[] = [
    { key: 'name', label: 'Name' },
    { key: 'email', label: 'Email' },
    { key: 'role', label: 'Role' },
    { key: 'two_factor_enabled', label: '2FA Enabled' },
    { key: 'last_login_at', label: 'Last Login' },
    { key: 'actions', label: 'Actions', className: 'text-right' },
];

const rows = computed(() =>
    adminUsersStore.rows.map((entry) => ({
        ...entry,
        actions: '',
    })),
);

const roleOptions = [
    { label: 'All', value: '' },
    { label: 'Super Admin', value: 'super-admin' },
    { label: 'Admin', value: 'admin' },
    { label: 'Support', value: 'support' },
];
const twoFactorOptions = [
    { label: 'All', value: '' },
    { label: 'Enabled', value: 'enabled' },
    { label: 'Disabled', value: 'disabled' },
];

const createOpen = ref(false);
const roleOpen = ref(false);
const editingUserId = ref<number | null>(null);

const createForm = reactive({
    name: '',
    email: '',
    role: 'support',
});

const roleForm = reactive({
    role: 'support',
});

const openCreate = (): void => {
    createForm.name = '';
    createForm.email = '';
    createForm.role = 'support';
    createOpen.value = true;
};

const createUser = async (): Promise<void> => {
    await adminUsersStore.createUser({
        name: createForm.name,
        email: createForm.email,
        role: createForm.role,
    });
    createOpen.value = false;

    toastStore.push({
        tone: adminUsersStore.error === null ? 'success' : 'error',
        title: adminUsersStore.error === null ? 'User created' : 'Create failed',
        message: adminUsersStore.error === null ? 'Admin user created successfully.' : adminUsersStore.error.message,
    });
};

const openRoleEdit = (row: Record<string, unknown>): void => {
    editingUserId.value = Number(row.id);
    roleForm.role = String(row.role);
    roleOpen.value = true;
};

const saveRole = async (): Promise<void> => {
    if (editingUserId.value === null) {
        return;
    }

    await adminUsersStore.updateRole(editingUserId.value, roleForm.role);
    roleOpen.value = false;

    toastStore.push({
        tone: adminUsersStore.error === null ? 'success' : 'error',
        title: adminUsersStore.error === null ? 'Role updated' : 'Update failed',
        message: adminUsersStore.error === null ? 'User role updated successfully.' : adminUsersStore.error.message,
    });
};

const disableUser = (row: Record<string, unknown>): void => {
    toastStore.push({
        tone: 'info',
        title: 'Disable requested',
        message: `Disable flow for ${row.email} is pending backend support.`,
    });
};

watch(
    () => [query.page, query.perPage, debouncedSearch.value, query.role, query.twoFactor],
    async () => {
        await adminUsersStore.fetchUsers({
            page: Number(query.page),
            perPage: Number(query.perPage),
            search: String(debouncedSearch.value),
            role: String(query.role),
            twoFactor: String(query.twoFactor),
        });
    },
    { immediate: true },
);
</script>
