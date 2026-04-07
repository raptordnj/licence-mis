<template>
    <section class="space-y-4">
        <PageHeader
            :title="item?.name ?? `Item #${route.params.id}`"
            eyebrow="Envato Item Detail"
            description="Review item-level license, purchase activity, and update releases."
        >
            <template #actions>
                <UiButton variant="secondary" @click="router.push('/admin/items')">Back to Items</UiButton>
            </template>
        </PageHeader>

        <ErrorBanner v-if="itemError !== null" :message="itemError.message" />

        <UiCard v-if="itemLoading" class="p-4">
            <p class="text-sm text-slate-500 dark:text-slate-300">Loading item details...</p>
        </UiCard>

        <div v-if="item !== null" class="grid gap-3 grid-cols-2 xl:grid-cols-4">
            <UiCard class="accent-strip">
                <p class="type-label">Marketplace</p>
                <p class="mt-2 text-sm font-semibold">{{ item.marketplace }}</p>
            </UiCard>
            <UiCard class="accent-strip">
                <p class="type-label">Envato Item ID</p>
                <p class="mt-2 text-sm font-semibold">{{ item.envato_item_id }}</p>
            </UiCard>
            <UiCard class="accent-strip">
                <p class="type-label">Licenses</p>
                <p class="mt-2 text-sm font-semibold">{{ item.licenses_count }}</p>
            </UiCard>
            <UiCard class="accent-strip">
                <p class="type-label">Status</p>
                <StatusBadge :value="item.status" />
            </UiCard>
        </div>

        <UiTabs v-model="activeTab" :tabs="tabs" />

        <UiCard v-if="activeTab === 'licenses'">
            <DataTable
                :columns="licenseColumns"
                :rows="licenseRows"
                :loading="false"
                empty-title="No licenses for item"
                empty-description="Licenses attached to this item will appear here."
            />
        </UiCard>

        <UiCard v-else-if="activeTab === 'purchases'">
            <DataTable
                :columns="purchaseColumns"
                :rows="purchaseRows"
                :loading="false"
                empty-title="No purchases for item"
                empty-description="Purchases linked to this item will appear here."
            />
        </UiCard>

        <UiCard v-else-if="activeTab === 'releases' && item !== null">
            <ItemReleaseManager :item="item" :can-manage="canManage" />
        </UiCard>

        <UiCard v-else-if="activeTab === 'settings'">
            <p class="text-sm text-slate-600 dark:text-slate-300">
                Item-level policies can be managed here when backend policies are enabled.
            </p>
        </UiCard>
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
import UiTabs from '@/admin/components/ui/UiTabs.vue';
import { usePermissions } from '@/admin/composables/usePermissions';
import { envatoItemSchema } from '@/admin/schemas/api';
import { demoLicenses, demoPurchases } from '@/admin/services/demoData';
import { extractApiError, requestData } from '@/admin/services/http';
import type { ApiError, EnvatoItem } from '@/admin/types/api';
import { formatDate } from '@/admin/utils/format';
import ItemReleaseManager from '@/admin/views/items/components/ItemReleaseManager.vue';

const route = useRoute();
const router = useRouter();
const { can } = usePermissions();

const itemId = computed<number>(() => Number(route.params.id));
const canManage = computed<boolean>(() => can('items:manage'));
const item = ref<EnvatoItem | null>(null);
const itemLoading = ref(false);
const itemError = ref<ApiError | null>(null);
const activeTab = ref<'licenses' | 'purchases' | 'releases' | 'settings'>('licenses');

const tabs = [
    { label: 'Licenses for Item', value: 'licenses' },
    { label: 'Purchases for Item', value: 'purchases' },
    { label: 'Release Management', value: 'releases' },
    { label: 'Settings', value: 'settings' },
];

const licenseColumns: DataTableColumn[] = [
    { key: 'id', label: 'License ID' },
    { key: 'purchase_code', label: 'Purchase Code' },
    { key: 'status', label: 'Status' },
    { key: 'bound_domain', label: 'Domain' },
];

const purchaseColumns: DataTableColumn[] = [
    { key: 'purchase_code', label: 'Purchase Code' },
    { key: 'buyer', label: 'Buyer' },
    { key: 'status', label: 'Status' },
    { key: 'purchase_date', label: 'Purchase Date' },
];

const licenseRows = computed(() =>
    demoLicenses
        .filter((entry) => entry.envato_item_id === item.value?.envato_item_id)
        .map((entry) => ({
            id: entry.id,
            purchase_code: entry.purchase_code,
            status: entry.status,
            bound_domain: entry.bound_domain ?? 'Unbound',
        })),
);

const purchaseRows = computed(() =>
    demoPurchases
        .filter((entry) => entry.envato_item_id === item.value?.envato_item_id)
        .map((entry) => ({
            purchase_code: entry.purchase_code,
            buyer: entry.buyer,
            status: entry.status,
            purchase_date: formatDate(entry.purchase_date),
        })),
);

const loadItem = async (): Promise<void> => {
    if (!Number.isFinite(itemId.value) || itemId.value < 1) {
        item.value = null;
        itemError.value = {
            code: 'NOT_FOUND',
            message: 'Invalid item id.',
        };
        return;
    }

    itemLoading.value = true;
    itemError.value = null;

    try {
        const payload = await requestData(
            {
                method: 'GET',
                url: `/admin/items/${itemId.value}`,
            },
            envatoItemSchema,
        );

        item.value = payload;
    } catch (error: unknown) {
        itemError.value = extractApiError(error);
        item.value = null;
    } finally {
        itemLoading.value = false;
    }
};

watch(
    () => itemId.value,
    () => {
        void loadItem();
    },
    { immediate: true },
);
</script>
