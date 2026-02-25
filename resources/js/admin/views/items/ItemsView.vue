<template>
    <section class="space-y-4">
        <PageHeader
            title="Envato Items"
            eyebrow="Catalog"
            description="Manage mapped marketplace items and monitor license volume by product."
        >
            <template #actions>
                <UiButton v-if="canManage" @click="openCreate">Create Item</UiButton>
            </template>
        </PageHeader>

        <FilterBar>
            <div class="md:col-span-6">
                <UiInput v-model="query.search" label="Search" placeholder="Item name or envato_item_id" />
            </div>
            <div class="md:col-span-3">
                <UiSelect v-model="query.marketplace" label="Marketplace" :options="marketplaceOptions" />
            </div>
            <div class="md:col-span-3">
                <UiSelect v-model="query.status" label="Status" :options="statusOptions" />
            </div>
        </FilterBar>

        <ErrorBanner v-if="itemsStore.error !== null" :message="itemsStore.error.message" />

        <DataTable
            :columns="columns"
            :rows="rows"
            :loading="itemsStore.loading"
            empty-title="No items found"
            empty-description="Create your first Envato item to start attaching purchases and licenses."
            @row-click="goToDetail"
        >
            <template #cell-status="{ value }">
                <StatusBadge :value="String(value)" />
            </template>
            <template #cell-actions="{ row }">
                <div class="flex justify-end">
                    <UiButton v-if="canManage" variant="secondary" @click.stop="openEdit(row)">Edit</UiButton>
                </div>
            </template>
        </DataTable>

        <div class="flex items-center justify-end gap-2">
            <UiButton variant="secondary" :disabled="query.page <= 1" @click="query.page -= 1">Previous</UiButton>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Page {{ itemsStore.response.current_page }} of {{ itemsStore.response.last_page }}
            </p>
            <UiButton variant="secondary" :disabled="itemsStore.response.current_page >= itemsStore.response.last_page" @click="query.page += 1">
                Next
            </UiButton>
        </div>

        <UiModal :open="editorOpen" :title="editorTitle" description="Add or update item metadata." @update:open="editorOpen = $event">
            <div class="grid gap-3">
                <UiInput v-model="editor.name" label="Name" required />
                <UiInput v-model="editor.envatoItemId" label="Envato Item ID" required />
                <UiSelect v-model="editor.status" label="Status" :options="statusOptions.filter((option) => option.value !== '')" />
            </div>
            <div class="mt-2 flex justify-end gap-2">
                <UiButton variant="secondary" @click="editorOpen = false">Cancel</UiButton>
                <UiButton :loading="itemsStore.saving" @click="saveItem">Save Item</UiButton>
            </div>
        </UiModal>
    </section>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';

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
import { usePermissions } from '@/admin/composables/usePermissions';
import { useQuerySync } from '@/admin/composables/useQuerySync';
import { useItemsStore } from '@/admin/stores/items';
import { useToastStore } from '@/admin/stores/toast';
import type { EnvatoItem } from '@/admin/types/api';

const itemsStore = useItemsStore();
const toastStore = useToastStore();
const router = useRouter();
const { can } = usePermissions();

const canManage = computed<boolean>(() => can('items:manage'));

const query = useQuerySync({
    page: 1,
    perPage: 10,
    search: '',
    marketplace: '',
    status: '',
});

const debouncedSearch = useDebounced(useComputedField(query, 'search'), 280);

const columns: DataTableColumn[] = [
    { key: 'marketplace', label: 'Marketplace' },
    { key: 'envato_item_id', label: 'Envato Item ID' },
    { key: 'name', label: 'Name' },
    { key: 'status', label: 'Status' },
    { key: 'licenses_count', label: 'Licenses' },
    { key: 'created_at', label: 'Created' },
    { key: 'actions', label: 'Actions', className: 'text-right' },
];

const marketplaceOptions = [
    { label: 'All', value: '' },
    { label: 'Envato', value: 'envato' },
];

const statusOptions = [
    { label: 'All', value: '' },
    { label: 'Active', value: 'active' },
    { label: 'Disabled', value: 'disabled' },
];

const rows = computed(() =>
    itemsStore.rows.map((entry) => ({
        ...entry,
        actions: '',
    })),
);

const editorOpen = ref(false);
const editingId = ref<number | null>(null);
const editor = reactive({
    name: '',
    envatoItemId: '',
    status: 'active',
});

const editorTitle = computed(() => (editingId.value === null ? 'Create Item' : 'Edit Item'));

const openCreate = (): void => {
    editingId.value = null;
    editor.name = '';
    editor.envatoItemId = '';
    editor.status = 'active';
    editorOpen.value = true;
};

const openEdit = (row: Record<string, unknown>): void => {
    editingId.value = Number(row.id);
    editor.name = String(row.name ?? '');
    editor.envatoItemId = String(row.envato_item_id ?? '');
    editor.status = String(row.status ?? 'active');
    editorOpen.value = true;
};

const saveItem = async (): Promise<void> => {
    await itemsStore.saveItem({
        id: editingId.value ?? undefined,
        name: editor.name,
        envato_item_id: Number(editor.envatoItemId),
        status: editor.status as 'active' | 'disabled',
        marketplace: 'envato',
        licenses_count: 0,
        created_at: new Date().toISOString(),
    } as Partial<EnvatoItem>);

    editorOpen.value = false;

    toastStore.push({
        tone: itemsStore.error === null ? 'success' : 'error',
        title: itemsStore.error === null ? 'Saved' : 'Save failed',
        message: itemsStore.error === null ? 'Item saved successfully.' : itemsStore.error.message,
    });
};

const goToDetail = async (row: Record<string, unknown>): Promise<void> => {
    await router.push(`/admin/items/${row.id}`);
};

watch(
    () => [query.page, query.perPage, query.marketplace, query.status, debouncedSearch.value],
    async () => {
        await itemsStore.fetchItems({
            page: Number(query.page),
            perPage: Number(query.perPage),
            search: String(debouncedSearch.value),
            marketplace: String(query.marketplace),
            status: String(query.status),
        });
    },
    { immediate: true },
);
</script>
