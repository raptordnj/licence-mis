<template>
    <section class="space-y-4">
        <PageHeader
            :title="`License #${license?.id ?? route.params.id}`"
            eyebrow="License Detail"
            :description="`Purchase code ${license?.purchase_code ?? 'N/A'} • ${license?.item_name ?? 'N/A'}`"
        >
            <template #actions>
                <StatusBadge :value="license?.status ?? 'unknown'" />
                <UiButton variant="secondary" @click="router.push('/admin/licenses')">Back to Licenses</UiButton>
            </template>
        </PageHeader>

        <ErrorBanner v-if="licensesStore.detailError !== null" :message="licensesStore.detailError.message" />

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            <UiCard>
                <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Bound Domain</p>
                <p class="mt-2 text-sm font-medium">{{ license?.bound_domain ?? 'Unbound' }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ license?.bound_domain_original ?? 'N/A' }}</p>
            </UiCard>
            <UiCard>
                <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Bound At</p>
                <p class="mt-2 text-sm font-medium">{{ formatDateTime(license?.bound_at ?? null) }}</p>
            </UiCard>
            <UiCard>
                <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Instance Count</p>
                <p class="mt-2 text-sm font-medium">{{ license?.instances.length ?? 0 }}</p>
            </UiCard>
            <UiCard>
                <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Reset Count</p>
                <p class="mt-2 text-sm font-medium">{{ license?.reset_count ?? 0 }}</p>
            </UiCard>
            <UiCard>
                <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Last Checked</p>
                <p class="mt-2 text-sm font-medium">{{ formatDateTime(license?.last_check_at ?? null) }}</p>
            </UiCard>
            <UiCard>
                <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Revocation Info</p>
                <p class="mt-2 text-sm font-medium">{{ revocationInfo }}</p>
            </UiCard>
        </div>

        <UiCard>
            <div class="flex flex-wrap gap-2">
                <UiButton v-if="canRevoke" variant="danger" @click="openAction('revoke')">Revoke License</UiButton>
                <UiButton v-if="canResetDomain" variant="secondary" @click="openAction('reset')">Reset Domain</UiButton>
                <UiButton v-if="canRevoke" @click="openAction('reactivate')">Reactivate</UiButton>
            </div>
        </UiCard>

        <UiCard>
            <DataTable
                :columns="instanceColumns"
                :rows="instanceRows"
                :loading="licensesStore.detailLoading"
                empty-title="No activation instances"
                empty-description="Instance activations will appear after a successful verify call."
            >
                <template #cell-status="{ value }">
                    <StatusBadge :value="String(value)" />
                </template>
                <template #cell-activated_at="{ value }">
                    {{ formatDateTime(value ? String(value) : null) }}
                </template>
                <template #cell-last_seen_at="{ value }">
                    {{ formatDateTime(value ? String(value) : null) }}
                </template>
            </DataTable>
        </UiCard>

        <UiTabs v-model="activeTab" :tabs="tabs" />

        <UiCard v-if="activeTab === 'validation'">
            <DataTable
                :columns="validationColumns"
                :rows="validationRows"
                :loading="licensesStore.detailLoading"
                empty-title="No validation entries"
                empty-description="Validation logs for this license will appear here."
            />
        </UiCard>

        <UiCard v-else-if="activeTab === 'notes'">
            <p class="text-sm text-slate-600 dark:text-slate-300">Internal comments support can be added here if enabled.</p>
        </UiCard>

        <UiCard v-else>
            <DataTable
                :columns="auditColumns"
                :rows="auditRows"
                :loading="licensesStore.detailLoading"
                empty-title="No audit trail"
                empty-description="Sensitive admin actions for this license will be listed here."
            />
        </UiCard>

        <UiModal :open="modalOpen" :title="modalTitle" :description="modalDescription" @update:open="modalOpen = $event">
            <UiInput v-model="reason" label="Reason" placeholder="Required reason for audit trail" />
            <div class="flex justify-end gap-2">
                <UiButton variant="secondary" @click="modalOpen = false">Cancel</UiButton>
                <UiButton variant="danger" :loading="licensesStore.actionLoading" @click="confirmAction">Confirm</UiButton>
            </div>
        </UiModal>
    </section>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import DataTable, { type DataTableColumn } from '@/admin/components/data/DataTable.vue';
import ErrorBanner from '@/admin/components/feedback/ErrorBanner.vue';
import StatusBadge from '@/admin/components/feedback/StatusBadge.vue';
import PageHeader from '@/admin/components/layout/PageHeader.vue';
import UiButton from '@/admin/components/ui/UiButton.vue';
import UiCard from '@/admin/components/ui/UiCard.vue';
import UiInput from '@/admin/components/ui/UiInput.vue';
import UiModal from '@/admin/components/ui/UiModal.vue';
import UiTabs from '@/admin/components/ui/UiTabs.vue';
import { usePermissions } from '@/admin/composables/usePermissions';
import { useLicensesStore } from '@/admin/stores/licenses';
import { useToastStore } from '@/admin/stores/toast';
import { formatDateTime } from '@/admin/utils/format';

type ActionKind = 'revoke' | 'reset' | 'reactivate' | null;

const route = useRoute();
const router = useRouter();
const licensesStore = useLicensesStore();
const toastStore = useToastStore();
const { can } = usePermissions();

const licenseId = computed<number>(() => Number(route.params.id));
const license = computed(() => (licensesStore.detail?.id === licenseId.value ? licensesStore.detail : null));

const canRevoke = computed<boolean>(() => can('licenses:revoke'));
const canResetDomain = computed<boolean>(() => can('licenses:reset-domain'));

const revocationInfo = computed<string>(() => {
    if (license.value?.status !== 'revoked') {
        return 'Not revoked';
    }

    return 'Revoked by admin';
});

const activeTab = ref<'validation' | 'notes' | 'audit'>('validation');
const tabs = [
    { label: 'Validation Logs', value: 'validation' },
    { label: 'Notes', value: 'notes' },
    { label: 'Audit Trail', value: 'audit' },
];

const instanceColumns: DataTableColumn[] = [
    { key: 'instance_id', label: 'Instance ID' },
    { key: 'domain', label: 'Domain' },
    { key: 'status', label: 'Status' },
    { key: 'activated_at', label: 'Activated At' },
    { key: 'last_seen_at', label: 'Last Checked' },
    { key: 'app_url', label: 'App URL' },
];

const instanceRows = computed(() =>
    (license.value?.instances ?? []).map((instance) => ({
        instance_id: instance.instance_id,
        domain: instance.domain,
        status: instance.status,
        activated_at: instance.activated_at,
        last_seen_at: instance.last_seen_at,
        app_url: instance.app_url,
    })),
);

const validationColumns: DataTableColumn[] = [
    { key: 'time', label: 'Time' },
    { key: 'result', label: 'Result' },
    { key: 'reason', label: 'Failure Reason' },
    { key: 'instance_id', label: 'Instance ID' },
    { key: 'domain', label: 'Domain' },
];

const validationRows = computed(() =>
    (license.value?.validation_logs ?? []).map((entry) => ({
        time: formatDateTime(entry.time),
        result: entry.result,
        reason: entry.reason ?? 'N/A',
        instance_id: entry.instance_id ?? 'N/A',
        domain: entry.domain ?? 'N/A',
    })),
);

const auditColumns: DataTableColumn[] = [
    { key: 'time', label: 'Time' },
    { key: 'event', label: 'Event' },
    { key: 'actor', label: 'Actor' },
    { key: 'reason', label: 'Reason' },
];

const auditRows = computed(() =>
    (license.value?.audit_trail ?? []).map((entry) => ({
        time: formatDateTime(entry.time),
        event: entry.event,
        actor: entry.actor?.email ?? 'System',
        reason: entry.reason ?? 'N/A',
    })),
);

const action = ref<ActionKind>(null);
const modalOpen = ref(false);
const reason = ref('');

const modalTitle = computed(() => {
    if (action.value === 'revoke') {
        return 'Revoke this license?';
    }

    if (action.value === 'reset') {
        return 'Reset bound domain?';
    }

    return 'Reactivate this license?';
});

const modalDescription = computed(() =>
    action.value === 'reactivate'
        ? 'Reactivation is available only if business rules permit.'
        : 'This operation will be written to audit logs.',
);

const openAction = (value: ActionKind): void => {
    action.value = value;
    reason.value = '';
    modalOpen.value = true;
};

const confirmAction = async (): Promise<void> => {
    if (license.value === null) {
        return;
    }

    if (action.value === 'revoke') {
        await licensesStore.revokeLicense(license.value.id, reason.value);
    } else if (action.value === 'reset') {
        await licensesStore.resetDomain(license.value.id, reason.value);
    } else {
        toastStore.push({
            tone: 'info',
            title: 'Not implemented',
            message: 'Reactivation flow will be enabled after backend policy support.',
        });
    }

    modalOpen.value = false;

    if (action.value !== 'reactivate') {
        toastStore.push({
            tone: licensesStore.error === null ? 'success' : 'error',
            title: licensesStore.error === null ? 'License updated' : 'Action failed',
            message: licensesStore.error === null ? 'Operation completed successfully.' : licensesStore.error.message,
        });
    }
};

watch(
    () => licenseId.value,
    async (id) => {
        if (!Number.isFinite(id) || id <= 0) {
            return;
        }

        await licensesStore.fetchLicenseDetail(id);
    },
    { immediate: true },
);
</script>
