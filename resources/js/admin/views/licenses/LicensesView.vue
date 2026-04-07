<template>
    <section class="space-y-4">
        <PageHeader
            title="Licenses"
            eyebrow="License Registry"
            description="Filter, inspect, and perform controlled actions on bound licenses."
        >
            <template #actions>
                <UiButton size="compact" variant="secondary" :disabled="!licensesStore.hasSelection || !canRevoke" @click="openBulkRevoke">
                    Revoke Selected
                </UiButton>
                <UiButton size="compact" variant="secondary" :disabled="!licensesStore.hasSelection" @click="exportSelected">Export Selected</UiButton>
            </template>
        </PageHeader>

        <FilterBar>
            <div class="md:col-span-4">
                <UiInput v-model="query.search" label="Search" placeholder="License ID, purchase code, domain, item" />
            </div>
            <div class="md:col-span-2">
                <UiSelect v-model="query.status" label="Status" :options="statusOptions" />
            </div>
            <div class="md:col-span-2">
                <UiSelect v-model="query.boundState" label="Bound State" :options="boundOptions" />
            </div>
            <div class="md:col-span-2">
                <UiSelect v-model="query.marketplace" label="Marketplace" :options="marketplaceOptions" />
            </div>
            <div class="md:col-span-1">
                <UiInput v-model="query.resetMin" label="Reset Min" type="number" />
            </div>
            <div class="md:col-span-1">
                <UiInput v-model="query.resetMax" label="Reset Max" type="number" />
            </div>
            <div class="md:col-span-2">
                <UiInput v-model="query.lastCheckFrom" label="Last Check From" type="date" />
            </div>
            <div class="md:col-span-2">
                <UiInput v-model="query.lastCheckTo" label="Last Check To" type="date" />
            </div>
        </FilterBar>

        <ErrorBanner v-if="licensesStore.error !== null" :message="licensesStore.error.message" />

        <DataTable
            :columns="columns"
            :rows="rows"
            :loading="licensesStore.loading"
            empty-title="No licenses found"
            empty-description="Try broadening filters or verify that sync jobs are healthy."
            @row-click="goToDetail"
        >
            <template #cell-select="{ row }">
                <input
                    type="checkbox"
                    class="h-4 w-4 rounded border-slate-300 text-violet-500 focus:ring-violet-400 dark:border-slate-700 dark:bg-slate-900"
                    :checked="licensesStore.selectedIds.includes(Number(row.id))"
                    @click.stop
                    @change="licensesStore.toggleSelection(Number(row.id))"
                />
            </template>
            <template #cell-status="{ value }">
                <StatusBadge :value="String(value)" />
            </template>
            <template #cell-license_type="{ value }">
                {{ normalizeLicenseType(String(value)) }}
            </template>
            <template #cell-activated_at="{ value }">
                {{ formatDateTime(value ? String(value) : null) }}
            </template>
            <template #cell-last_check_at="{ value }">
                {{ formatDateTime(value ? String(value) : null) }}
            </template>
            <template #cell-actions="{ row }">
                <div class="flex justify-end gap-2">
                    <UiButton
                        v-if="canResetDomain"
                        size="compact"
                        variant="secondary"
                        @click.stop="openResetDialog(Number(row.id), String(row.purchase_code))"
                    >
                        Reset Domain
                    </UiButton>
                    <UiButton
                        v-if="canResetDomain"
                        size="compact"
                        variant="secondary"
                        @click.stop="openResetActivationsDialog(Number(row.id), String(row.purchase_code))"
                    >
                        Reset Activations
                    </UiButton>
                    <UiButton
                        v-if="canRevoke"
                        size="compact"
                        variant="danger"
                        @click.stop="openRevokeDialog(Number(row.id), String(row.purchase_code))"
                    >
                        Revoke
                    </UiButton>
                </div>
            </template>
        </DataTable>

        <div class="flex items-center justify-end gap-2">
            <UiButton size="compact" variant="secondary" :disabled="query.page <= 1" @click="query.page -= 1">Previous</UiButton>
            <span class="pagination-glass text-xs text-slate-600 dark:text-slate-300">
                {{ licensesStore.response.current_page }} / {{ licensesStore.response.last_page }}
            </span>
            <UiButton size="compact" variant="secondary" :disabled="licensesStore.response.current_page >= licensesStore.response.last_page" @click="query.page += 1">
                Next
            </UiButton>
        </div>

        <UiModal :open="reasonModalOpen" :title="reasonModalTitle" :description="reasonModalDescription" @update:open="reasonModalOpen = $event">
            <UiInput v-model="reason" label="Reason" placeholder="Explain why this action is needed..." />
            <div class="flex justify-end gap-2">
                <UiButton size="compact" variant="secondary" @click="reasonModalOpen = false">Cancel</UiButton>
                <UiButton size="compact" variant="danger" :loading="licensesStore.actionLoading" @click="confirmReasonAction">
                    Confirm
                </UiButton>
            </div>
        </UiModal>

        <ConfirmDialog
            :open="bulkDialogOpen"
            title="Revoke selected licenses?"
            description="This action cannot be undone without manual intervention."
            body="Selected licenses will be revoked immediately and audit logs will record this operation."
            :loading="licensesStore.actionLoading"
            @update:open="bulkDialogOpen = $event"
            @confirm="confirmBulkRevoke"
        />
    </section>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';

import DataTable, { type DataTableColumn } from '@/admin/components/data/DataTable.vue';
import FilterBar from '@/admin/components/data/FilterBar.vue';
import ConfirmDialog from '@/admin/components/feedback/ConfirmDialog.vue';
import ErrorBanner from '@/admin/components/feedback/ErrorBanner.vue';
import StatusBadge from '@/admin/components/feedback/StatusBadge.vue';
import PageHeader from '@/admin/components/layout/PageHeader.vue';
import UiButton from '@/admin/components/ui/UiButton.vue';
import UiInput from '@/admin/components/ui/UiInput.vue';
import UiModal from '@/admin/components/ui/UiModal.vue';
import UiSelect from '@/admin/components/ui/UiSelect.vue';
import { useComputedField } from '@/admin/composables/useComputedField';
import { useDebounced } from '@/admin/composables/useDebounced';
import { usePermissions } from '@/admin/composables/usePermissions';
import { useQuerySync } from '@/admin/composables/useQuerySync';
import { useLicensesStore } from '@/admin/stores/licenses';
import { useToastStore } from '@/admin/stores/toast';
import { formatDateTime } from '@/admin/utils/format';

const router = useRouter();
const licensesStore = useLicensesStore();
const toastStore = useToastStore();
const { can } = usePermissions();

const canRevoke = computed<boolean>(() => can('licenses:revoke'));
const canResetDomain = computed<boolean>(() => can('licenses:reset-domain'));

const query = useQuerySync({
    page: 1,
    perPage: 15,
    search: '',
    status: '',
    boundState: '',
    marketplace: '',
    resetMin: '0',
    resetMax: '20',
    lastCheckFrom: '',
    lastCheckTo: '',
});

const debouncedSearch = useDebounced(useComputedField(query, 'search'), 280);

const columns: DataTableColumn[] = [
    { key: 'select', label: '' },
    { key: 'id', label: 'License ID' },
    { key: 'bound_domain', label: 'Domain' },
    { key: 'buyer_username', label: 'Buyer Username' },
    { key: 'purchase_code', label: 'Purchase Code' },
    { key: 'license_type', label: 'License Type' },
    { key: 'version', label: 'Version' },
    { key: 'activated_at', label: 'Activated Time' },
    { key: 'status', label: 'Status' },
    { key: 'last_check_at', label: 'Last Check' },
    { key: 'reset_count', label: 'Reset Count' },
    { key: 'actions', label: 'Actions', className: 'text-right' },
];

const rows = computed(() =>
    licensesStore.rows.map((entry) => ({
        ...entry,
        select: '',
        actions: '',
    })),
);

const statusOptions = [
    { label: 'All', value: '' },
    { label: 'Active', value: 'active' },
    { label: 'Revoked', value: 'revoked' },
    { label: 'Expired', value: 'expired' },
];
const boundOptions = [
    { label: 'All', value: '' },
    { label: 'Bound', value: 'bound' },
    { label: 'Unbound', value: 'unbound' },
];
const marketplaceOptions = [
    { label: 'All', value: '' },
    { label: 'Envato', value: 'envato' },
];

type PendingAction = 'revoke' | 'reset' | 'reset_activations' | null;

const pendingAction = ref<PendingAction>(null);
const pendingLicenseId = ref<number | null>(null);
const pendingLicenseCode = ref('');
const reason = ref('');
const reasonModalOpen = ref(false);
const bulkDialogOpen = ref(false);

const reasonModalTitle = computed(() => {
    if (pendingAction.value === 'revoke') {
        return `Revoke ${pendingLicenseCode.value}`;
    }

    if (pendingAction.value === 'reset_activations') {
        return `Reset Activations for ${pendingLicenseCode.value}`;
    }

    return `Reset Domain for ${pendingLicenseCode.value}`;
});

const reasonModalDescription = computed(() => {
    if (pendingAction.value === 'revoke') {
        return 'Provide a clear reason for this revocation.';
    }

    if (pendingAction.value === 'reset_activations') {
        return 'Provide a reason for clearing active instances to resolve activation limits.';
    }

    return 'Provide a reason for resetting the bound domain.';
});

const openRevokeDialog = (licenseId: number, code: string): void => {
    pendingAction.value = 'revoke';
    pendingLicenseId.value = licenseId;
    pendingLicenseCode.value = code;
    reason.value = '';
    reasonModalOpen.value = true;
};

const openResetDialog = (licenseId: number, code: string): void => {
    pendingAction.value = 'reset';
    pendingLicenseId.value = licenseId;
    pendingLicenseCode.value = code;
    reason.value = '';
    reasonModalOpen.value = true;
};

const openResetActivationsDialog = (licenseId: number, code: string): void => {
    pendingAction.value = 'reset_activations';
    pendingLicenseId.value = licenseId;
    pendingLicenseCode.value = code;
    reason.value = '';
    reasonModalOpen.value = true;
};

const confirmReasonAction = async (): Promise<void> => {
    if (pendingLicenseId.value === null) {
        return;
    }

    if (pendingAction.value === 'revoke') {
        await licensesStore.revokeLicense(pendingLicenseId.value, reason.value);
    } else if (pendingAction.value === 'reset') {
        await licensesStore.resetDomain(pendingLicenseId.value, reason.value);
    } else if (pendingAction.value === 'reset_activations') {
        await licensesStore.resetActivations(pendingLicenseId.value, reason.value);
    }

    reasonModalOpen.value = false;
    toastStore.push({
        tone: licensesStore.error === null ? 'success' : 'error',
        title: licensesStore.error === null ? 'Action completed' : 'Action failed',
        message: licensesStore.error === null ? 'License updated successfully.' : licensesStore.error.message,
    });
};

const openBulkRevoke = (): void => {
    bulkDialogOpen.value = true;
};

const confirmBulkRevoke = async (): Promise<void> => {
    await licensesStore.bulkRevoke('Bulk revoke by admin dashboard');
    bulkDialogOpen.value = false;

    toastStore.push({
        tone: licensesStore.error === null ? 'success' : 'error',
        title: licensesStore.error === null ? 'Bulk revoke completed' : 'Bulk revoke failed',
        message:
            licensesStore.error === null
                ? `${licensesStore.selectedIds.length} license(s) were processed.`
                : licensesStore.error.message,
    });
};

const exportSelected = (): void => {
    const selectedRows = licensesStore.rows.filter((entry) => licensesStore.selectedIds.includes(entry.id));
    const csvRows = [
        ['license_id', 'purchase_code', 'license_type', 'buyer_username', 'version', 'activated_at', 'status', 'bound_domain'],
        ...selectedRows.map((entry) => [
            entry.id,
            entry.purchase_code,
            entry.license_type,
            entry.buyer_username ?? '',
            entry.version ?? '',
            entry.activated_at ?? '',
            entry.status,
            entry.bound_domain ?? '',
        ]),
    ];
    const csv = csvRows.map((row) => row.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'licenses-export.csv';
    link.click();
    URL.revokeObjectURL(url);
};

const goToDetail = async (row: Record<string, unknown>): Promise<void> => {
    await router.push(`/admin/licenses/${row.id}`);
};

const normalizeLicenseType = (value: string): string =>
    value.toLowerCase().includes('extended') ? 'Extended' : 'Regular';

watch(
    () => [
        query.page,
        query.perPage,
        debouncedSearch.value,
        query.status,
        query.boundState,
        query.marketplace,
        query.resetMin,
        query.resetMax,
        query.lastCheckFrom,
        query.lastCheckTo,
    ],
    async () => {
        await licensesStore.fetchLicenses({
            page: Number(query.page),
            perPage: Number(query.perPage),
            search: String(debouncedSearch.value),
            status: String(query.status),
            boundState: String(query.boundState),
            marketplace: String(query.marketplace),
            resetMin: Number(query.resetMin),
            resetMax: Number(query.resetMax),
            lastCheckFrom: String(query.lastCheckFrom),
            lastCheckTo: String(query.lastCheckTo),
        });
    },
    { immediate: true },
);
</script>
