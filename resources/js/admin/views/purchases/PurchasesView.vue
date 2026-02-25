<template>
    <section class="space-y-4">
        <PageHeader
            title="Purchases"
            eyebrow="Transactions"
            description="Search Envato purchases by purchase code, buyer identity, or item."
        >
            <template #actions>
                <UiButton variant="secondary" @click="router.push('/admin/checker')">Open Checker</UiButton>
            </template>
        </PageHeader>

        <FilterBar>
            <div class="md:col-span-5">
                <UiInput v-model="query.search" label="Search" placeholder="Purchase code, buyer, item" />
            </div>
            <div class="md:col-span-3">
                <UiInput v-model="query.itemId" label="Envato Item ID" placeholder="1001" />
            </div>
            <div class="md:col-span-2">
                <UiInput v-model="query.buyer" label="Buyer" placeholder="username" />
            </div>
            <div class="md:col-span-2">
                <UiSelect v-model="query.status" label="Status" :options="statusOptions" />
            </div>
        </FilterBar>

        <ErrorBanner v-if="purchasesStore.error !== null" :message="purchasesStore.error.message" />

        <DataTable
            :columns="columns"
            :rows="rows"
            :loading="purchasesStore.loading"
            empty-title="No purchases found"
            empty-description="Try clearing filters or verify purchase ingestion jobs."
            @row-click="goToDetail"
        >
            <template #cell-status="{ value }">
                <StatusBadge :value="String(value)" />
            </template>
            <template #cell-license_type="{ value }">
                {{ normalizeLicenseType(String(value)) }}
            </template>
            <template #cell-purchase_date="{ value }">
                {{ formatDate(String(value)) }}
            </template>
            <template #cell-activated_at="{ value }">
                {{ formatDateTime(value ? String(value) : null) }}
            </template>
            <template #cell-supported_until="{ value }">
                {{ formatDate(value ? String(value) : null) }}
            </template>
            <template #cell-created_at="{ value }">
                {{ formatDate(String(value)) }}
            </template>
        </DataTable>

        <div class="flex items-center justify-end gap-2">
            <UiButton variant="secondary" :disabled="query.page <= 1" @click="query.page -= 1">Previous</UiButton>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Page {{ purchasesStore.response.current_page }} of {{ purchasesStore.response.last_page }}
            </p>
            <UiButton
                variant="secondary"
                :disabled="purchasesStore.response.current_page >= purchasesStore.response.last_page"
                @click="query.page += 1"
            >
                Next
            </UiButton>
        </div>
    </section>
</template>

<script setup lang="ts">
import { computed, watch } from 'vue';
import { useRouter } from 'vue-router';

import DataTable, { type DataTableColumn } from '@/admin/components/data/DataTable.vue';
import FilterBar from '@/admin/components/data/FilterBar.vue';
import ErrorBanner from '@/admin/components/feedback/ErrorBanner.vue';
import StatusBadge from '@/admin/components/feedback/StatusBadge.vue';
import PageHeader from '@/admin/components/layout/PageHeader.vue';
import UiButton from '@/admin/components/ui/UiButton.vue';
import UiInput from '@/admin/components/ui/UiInput.vue';
import UiSelect from '@/admin/components/ui/UiSelect.vue';
import { useComputedField } from '@/admin/composables/useComputedField';
import { useDebounced } from '@/admin/composables/useDebounced';
import { useQuerySync } from '@/admin/composables/useQuerySync';
import { usePurchasesStore } from '@/admin/stores/purchases';
import { formatDate, formatDateTime } from '@/admin/utils/format';

const purchasesStore = usePurchasesStore();
const router = useRouter();

const statusOptions = [
    { label: 'All', value: '' },
    { label: 'Valid', value: 'valid' },
    { label: 'Expired', value: 'expired' },
    { label: 'Revoked', value: 'revoked' },
    { label: 'Unknown', value: 'unknown' },
];

const query = useQuerySync({
    page: 1,
    perPage: 15,
    search: '',
    itemId: '',
    buyer: '',
    status: '',
});

const columns: DataTableColumn[] = [
    { key: 'purchase_code', label: 'Purchase Code' },
    { key: 'item_name', label: 'Item' },
    { key: 'buyer_username', label: 'Buyer Username' },
    { key: 'buyer', label: 'Buyer' },
    { key: 'license_type', label: 'License Type' },
    { key: 'version', label: 'Version' },
    { key: 'activated_at', label: 'Activated Time' },
    { key: 'purchase_date', label: 'Purchase Date' },
    { key: 'supported_until', label: 'Supported Until' },
    { key: 'status', label: 'Status' },
    { key: 'created_at', label: 'Created' },
];

const rows = computed(() =>
    purchasesStore.rows.map((entry) => ({
        id: entry.id,
        purchase_code: entry.purchase_code,
        item_name: entry.item_name,
        buyer_username: entry.buyer_username ?? entry.buyer,
        buyer: entry.buyer,
        license_type: entry.license_type,
        version: entry.version ?? 'N/A',
        activated_at: entry.activated_at,
        purchase_date: entry.purchase_date,
        supported_until: entry.supported_until,
        status: entry.status,
        created_at: entry.created_at,
    })),
);

const normalizeLicenseType = (value: string): string =>
    value.toLowerCase().includes('extended') ? 'Extended' : 'Regular';
const debouncedSearch = useDebounced(useComputedField(query, 'search'), 280);

watch(
    () => [query.page, query.perPage, debouncedSearch.value, query.itemId, query.buyer, query.status],
    async () => {
        await purchasesStore.fetchPurchases({
            page: Number(query.page),
            perPage: Number(query.perPage),
            search: String(debouncedSearch.value),
            itemId: String(query.itemId),
            buyer: String(query.buyer),
            status: String(query.status),
        });
    },
    { immediate: true },
);

const goToDetail = async (row: Record<string, unknown>): Promise<void> => {
    await router.push(`/admin/purchases/${row.id}`);
};
</script>
