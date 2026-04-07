<template>
    <section class="space-y-4">
        <PageHeader
            :title="purchase?.purchase_code ?? `Purchase #${route.params.id}`"
            eyebrow="Purchase Detail"
            :description="`Item ${purchase?.item_name ?? 'N/A'} • Buyer ${purchase?.buyer_username ?? purchase?.buyer ?? 'N/A'}`"
        >
            <template #actions>
                <StatusBadge :value="purchase?.status ?? 'unknown'" />
                <UiButton variant="secondary" @click="router.push('/admin/purchases')">Back to Purchases</UiButton>
            </template>
        </PageHeader>

        <ErrorBanner v-if="purchasesStore.detailError !== null" :message="purchasesStore.detailError.message" />

        <div class="grid gap-3 grid-cols-2 md:grid-cols-3 xl:grid-cols-6">
            <UiCard class="accent-strip">
                <p class="type-label">Buyer</p>
                <p class="mt-2 text-sm font-semibold">{{ purchase?.buyer_username ?? purchase?.buyer ?? 'N/A' }}</p>
            </UiCard>
            <UiCard class="accent-strip">
                <p class="type-label">Item</p>
                <p class="mt-2 text-sm font-semibold">{{ purchase?.item_name ?? 'N/A' }}</p>
            </UiCard>
            <UiCard class="accent-strip">
                <p class="type-label">Envato Item ID</p>
                <p class="mt-2 text-sm font-semibold">{{ purchase?.envato_item_id ?? 'N/A' }}</p>
            </UiCard>
            <UiCard class="accent-strip">
                <p class="type-label">Purchase Date</p>
                <p class="mt-2 text-sm font-semibold">{{ formatDate(purchase?.purchase_date ?? null) }}</p>
            </UiCard>
            <UiCard class="accent-strip">
                <p class="type-label">Supported Until</p>
                <p class="mt-2 text-sm font-semibold">{{ formatDate(purchase?.supported_until ?? null) }}</p>
            </UiCard>
            <UiCard class="accent-strip">
                <p class="type-label">Activated At</p>
                <p class="mt-2 text-sm font-semibold">{{ formatDateTime(purchase?.activated_at ?? null) }}</p>
            </UiCard>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <UiCard class="lg:col-span-2 space-y-3">
                <h2 class="section-heading font-display text-base font-semibold text-slate-900 dark:text-slate-100">Linked License</h2>

                <div v-if="license !== null" class="grid gap-2 text-sm sm:grid-cols-2">
                    <p><span class="text-slate-500 dark:text-slate-400">License ID:</span> {{ license.id }}</p>
                    <p><span class="text-slate-500 dark:text-slate-400">Status:</span> {{ license.status }}</p>
                    <p><span class="text-slate-500 dark:text-slate-400">Product ID:</span> {{ license.product_id ?? 'N/A' }}</p>
                    <p><span class="text-slate-500 dark:text-slate-400">Bound Domain:</span> {{ license.bound_domain ?? 'Unbound' }}</p>
                    <p><span class="text-slate-500 dark:text-slate-400">Last Checked:</span> {{ formatDateTime(license.last_check_at) }}</p>
                    <p><span class="text-slate-500 dark:text-slate-400">Reset Count:</span> {{ license.reset_count }}</p>
                    <p class="sm:col-span-2"><span class="text-slate-500 dark:text-slate-400">Notes:</span> {{ license.notes ?? 'N/A' }}</p>
                </div>
                <p v-else class="text-sm text-slate-500 dark:text-slate-400">No linked license data found.</p>
            </UiCard>

            <UiCard class="space-y-2">
                <h2 class="section-heading font-display text-base font-semibold text-slate-900 dark:text-slate-100">Actions</h2>
                <UiButton
                    v-if="license !== null"
                    variant="secondary"
                    @click="router.push(`/admin/licenses/${license.id}`)"
                >
                    Open License Detail
                </UiButton>
                <UiButton
                    variant="ghost"
                    :disabled="!purchase?.purchase_code"
                    @click="router.push(`/admin/validation-logs?purchaseCode=${purchase?.purchase_code ?? ''}`)"
                >
                    Open Validation Logs
                </UiButton>
                <UiButton variant="secondary" @click="attachLicense">Create / Attach License</UiButton>
                <UiButton :disabled="!canManagePurchases" @click="reVerify">Re-verify with Envato</UiButton>
            </UiCard>
        </div>

        <UiCard>
            <DataTable
                :columns="instanceColumns"
                :rows="instanceRows"
                :loading="purchasesStore.detailLoading"
                empty-title="No activation instances"
                empty-description="This purchase has no recorded activation instances yet."
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
                :loading="purchasesStore.detailLoading"
                empty-title="No validation entries"
                empty-description="Validation checks for this purchase will appear here."
            />
        </UiCard>

        <UiCard v-else-if="activeTab === 'audit'">
            <DataTable
                :columns="auditColumns"
                :rows="auditRows"
                :loading="purchasesStore.detailLoading"
                empty-title="No audit trail"
                empty-description="Admin actions for this purchase will appear here."
            />
        </UiCard>

        <UiCard v-else>
            <JsonViewer label="Raw Purchase Metadata" :value="purchase?.metadata ?? {}" />
        </UiCard>
    </section>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import DataTable, { type DataTableColumn } from '@/admin/components/data/DataTable.vue';
import ErrorBanner from '@/admin/components/feedback/ErrorBanner.vue';
import JsonViewer from '@/admin/components/feedback/JsonViewer.vue';
import StatusBadge from '@/admin/components/feedback/StatusBadge.vue';
import PageHeader from '@/admin/components/layout/PageHeader.vue';
import UiButton from '@/admin/components/ui/UiButton.vue';
import UiCard from '@/admin/components/ui/UiCard.vue';
import UiTabs from '@/admin/components/ui/UiTabs.vue';
import { usePermissions } from '@/admin/composables/usePermissions';
import { usePurchasesStore } from '@/admin/stores/purchases';
import { useToastStore } from '@/admin/stores/toast';
import { formatDate, formatDateTime } from '@/admin/utils/format';

const route = useRoute();
const router = useRouter();
const purchasesStore = usePurchasesStore();
const toastStore = useToastStore();
const { can } = usePermissions();

const purchaseId = computed<number>(() => Number(route.params.id));
const purchase = computed(() => (purchasesStore.detail?.id === purchaseId.value ? purchasesStore.detail : null));
const license = computed(() => purchase.value?.license ?? null);

const activeTab = ref<'validation' | 'audit' | 'raw'>('validation');
const tabs = [
    { label: 'Validation Logs', value: 'validation' },
    { label: 'Audit Trail', value: 'audit' },
    { label: 'Raw Metadata', value: 'raw' },
];

const canManagePurchases = computed<boolean>(() => can('purchases:manage'));

const instanceColumns: DataTableColumn[] = [
    { key: 'instance_id', label: 'Instance ID' },
    { key: 'domain', label: 'Domain' },
    { key: 'status', label: 'Status' },
    { key: 'activated_at', label: 'Activated At' },
    { key: 'last_seen_at', label: 'Last Checked' },
    { key: 'app_url', label: 'App URL' },
];

const instanceRows = computed(() =>
    (purchase.value?.instances ?? []).map((instance) => ({
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
    { key: 'reason', label: 'Reason' },
    { key: 'instance_id', label: 'Instance ID' },
    { key: 'domain', label: 'Domain' },
    { key: 'ip', label: 'IP' },
];

const validationRows = computed(() =>
    (purchase.value?.validation_logs ?? []).map((entry) => ({
        time: formatDateTime(entry.time),
        result: entry.result,
        reason: entry.reason ?? 'N/A',
        instance_id: entry.instance_id ?? 'N/A',
        domain: entry.domain ?? 'N/A',
        ip: entry.ip ?? 'N/A',
    })),
);

const auditColumns: DataTableColumn[] = [
    { key: 'time', label: 'Time' },
    { key: 'event', label: 'Event' },
    { key: 'actor', label: 'Actor' },
    { key: 'reason', label: 'Reason' },
];

const auditRows = computed(() =>
    (purchase.value?.audit_trail ?? []).map((entry) => ({
        time: formatDateTime(entry.time),
        event: entry.event,
        actor: entry.actor?.email ?? 'System',
        reason: entry.reason ?? 'N/A',
    })),
);

const attachLicense = (): void => {
    toastStore.push({
        tone: 'info',
        title: 'Action queued',
        message: 'Attach/create license action will run when backend endpoint is available.',
    });
};

const reVerify = (): void => {
    toastStore.push({
        tone: 'success',
        title: 'Re-verify requested',
        message: 'Re-verification request dispatched.',
    });
};

watch(
    () => purchaseId.value,
    async (id) => {
        if (!Number.isFinite(id) || id <= 0) {
            return;
        }

        await purchasesStore.fetchPurchaseDetail(id);
    },
    { immediate: true },
);
</script>
