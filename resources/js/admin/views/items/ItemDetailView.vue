<template>
    <section class="space-y-4">
        <PageHeader
            :title="item?.name ?? `Item #${route.params.id}`"
            eyebrow="Envato Item Detail"
            description="Review item-level license and purchase activity."
        >
            <template #actions>
                <UiButton variant="secondary" @click="router.push('/admin/items')">Back to Items</UiButton>
            </template>
        </PageHeader>

        <UiCard v-if="item !== null" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Marketplace</p>
                <p class="mt-1 text-sm font-medium">{{ item.marketplace }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Envato Item ID</p>
                <p class="mt-1 text-sm font-medium">{{ item.envato_item_id }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Licenses</p>
                <p class="mt-1 text-sm font-medium">{{ item.licenses_count }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</p>
                <StatusBadge :value="item.status" />
            </div>
        </UiCard>

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

        <UiCard v-else>
            <p class="text-sm text-slate-600 dark:text-slate-300">
                Item-level policies can be managed here when backend policies are enabled.
            </p>
        </UiCard>
    </section>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import DataTable, { type DataTableColumn } from '@/admin/components/data/DataTable.vue';
import StatusBadge from '@/admin/components/feedback/StatusBadge.vue';
import PageHeader from '@/admin/components/layout/PageHeader.vue';
import UiButton from '@/admin/components/ui/UiButton.vue';
import UiCard from '@/admin/components/ui/UiCard.vue';
import UiTabs from '@/admin/components/ui/UiTabs.vue';
import { demoLicenses, demoItems, demoPurchases } from '@/admin/services/demoData';
import { formatDate } from '@/admin/utils/format';

const route = useRoute();
const router = useRouter();

const itemId = computed<number>(() => Number(route.params.id));
const item = computed(() => demoItems.find((entry) => entry.id === itemId.value) ?? null);
const activeTab = ref<'licenses' | 'purchases' | 'settings'>('licenses');

const tabs = [
    { label: 'Licenses for Item', value: 'licenses' },
    { label: 'Purchases for Item', value: 'purchases' },
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
</script>
