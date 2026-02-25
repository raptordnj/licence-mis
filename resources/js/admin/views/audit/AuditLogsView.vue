<template>
    <section class="space-y-4">
        <PageHeader
            title="Audit Logs"
            eyebrow="Sensitive Actions"
            description="Append-only records for license changes, token rotations, and RBAC updates."
        />

        <FilterBar>
            <div class="md:col-span-4">
                <UiInput v-model="query.search" label="Search" placeholder="action, actor, target" />
            </div>
            <div class="md:col-span-3">
                <UiSelect v-model="query.actionType" label="Action Type" :options="actionOptions" />
            </div>
            <div class="md:col-span-2">
                <UiInput v-model="query.actor" label="Actor" placeholder="email" />
            </div>
            <div class="md:col-span-3">
                <UiInput v-model="query.target" label="Target" placeholder="license_id / purchase_code" />
            </div>
            <div class="md:col-span-2">
                <UiInput v-model="query.from" label="From" type="date" />
            </div>
            <div class="md:col-span-2">
                <UiInput v-model="query.to" label="To" type="date" />
            </div>
        </FilterBar>

        <ErrorBanner v-if="auditLogsStore.error !== null" :message="auditLogsStore.error.message" />

        <DataTable
            :columns="columns"
            :rows="rows"
            :loading="auditLogsStore.loading"
            empty-title="No audit logs found"
            empty-description="Sensitive operations will appear here as they happen."
            @row-click="open"
        >
            <template #cell-created_at="{ value }">
                {{ formatDateTime(value ? String(value) : null) }}
            </template>
            <template #cell-event_type="{ value }">
                <StatusBadge :value="String(value)" />
            </template>
            <template #cell-actor_email="{ value }">
                {{ value || 'System' }}
            </template>
        </DataTable>

        <div class="flex items-center justify-end gap-2">
            <UiButton variant="secondary" :disabled="query.page <= 1" @click="query.page -= 1">Previous</UiButton>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Page {{ auditLogsStore.response.current_page }} of {{ auditLogsStore.response.last_page }}
            </p>
            <UiButton variant="secondary" :disabled="auditLogsStore.response.current_page >= auditLogsStore.response.last_page" @click="query.page += 1">
                Next
            </UiButton>
        </div>

        <UiDrawer
            :open="auditLogsStore.selected !== null"
            title="Audit Entry"
            description="Inspect metadata and compare before/after values when available."
            @update:open="auditLogsStore.close()"
        >
            <div v-if="auditLogsStore.selected !== null" class="space-y-4">
                <div class="grid gap-2 text-sm sm:grid-cols-2">
                    <p><span class="text-slate-500 dark:text-slate-400">Action:</span> {{ auditLogsStore.selected.event_type }}</p>
                    <p><span class="text-slate-500 dark:text-slate-400">Actor:</span> {{ auditLogsStore.selected.actor?.email ?? 'System' }}</p>
                    <p><span class="text-slate-500 dark:text-slate-400">Target:</span> {{ auditLogsStore.selected.target ?? 'N/A' }}</p>
                    <p><span class="text-slate-500 dark:text-slate-400">When:</span> {{ formatDateTime(auditLogsStore.selected.created_at) }}</p>
                </div>
                <div class="grid gap-3 lg:grid-cols-2">
                    <JsonViewer label="Before" :value="beforePayload" />
                    <JsonViewer label="After" :value="afterPayload" />
                </div>
                <JsonViewer label="Full Metadata" :value="auditLogsStore.selected.metadata" />
            </div>
        </UiDrawer>
    </section>
</template>

<script setup lang="ts">
import { computed, watch } from 'vue';

import DataTable, { type DataTableColumn } from '@/admin/components/data/DataTable.vue';
import FilterBar from '@/admin/components/data/FilterBar.vue';
import ErrorBanner from '@/admin/components/feedback/ErrorBanner.vue';
import JsonViewer from '@/admin/components/feedback/JsonViewer.vue';
import StatusBadge from '@/admin/components/feedback/StatusBadge.vue';
import PageHeader from '@/admin/components/layout/PageHeader.vue';
import UiButton from '@/admin/components/ui/UiButton.vue';
import UiDrawer from '@/admin/components/ui/UiDrawer.vue';
import UiInput from '@/admin/components/ui/UiInput.vue';
import UiSelect from '@/admin/components/ui/UiSelect.vue';
import { useComputedField } from '@/admin/composables/useComputedField';
import { useDebounced } from '@/admin/composables/useDebounced';
import { useQuerySync } from '@/admin/composables/useQuerySync';
import { useAuditLogsStore } from '@/admin/stores/auditLogs';
import { formatDateTime } from '@/admin/utils/format';

const auditLogsStore = useAuditLogsStore();

const query = useQuerySync({
    page: 1,
    perPage: 20,
    search: '',
    actionType: '',
    actor: '',
    target: '',
    from: '',
    to: '',
});

const debouncedSearch = useDebounced(useComputedField(query, 'search'), 280);
const debouncedActor = useDebounced(useComputedField(query, 'actor'), 280);
const debouncedTarget = useDebounced(useComputedField(query, 'target'), 280);

const actionOptions = [
    { label: 'All', value: '' },
    { label: 'License Revoked', value: 'license_revoked' },
    { label: 'Domain Reset', value: 'domain_reset' },
    { label: 'Token Changed', value: 'token_changed' },
    { label: 'Role Changed', value: 'role_changed' },
];

const columns: DataTableColumn[] = [
    { key: 'created_at', label: 'Time' },
    { key: 'event_type', label: 'Action Type' },
    { key: 'actor_email', label: 'Actor' },
    { key: 'target', label: 'Target' },
];

const rows = computed(() =>
    auditLogsStore.rows.map((entry) => ({
        ...entry,
        actor_email: entry.actor?.email ?? 'System',
    })),
);

const beforePayload = computed(() => {
    const metadata = auditLogsStore.selected?.metadata;

    if (metadata === null || metadata === undefined || typeof metadata.before !== 'object') {
        return {};
    }

    return metadata.before;
});

const afterPayload = computed(() => {
    const metadata = auditLogsStore.selected?.metadata;

    if (metadata === null || metadata === undefined || typeof metadata.after !== 'object') {
        return {};
    }

    return metadata.after;
});

watch(
    () => [query.page, query.perPage, debouncedSearch.value, query.actionType, debouncedActor.value, debouncedTarget.value],
    async () => {
        await auditLogsStore.fetchAuditLogs({
            page: Number(query.page),
            perPage: Number(query.perPage),
            search: String(debouncedSearch.value),
            actionType: String(query.actionType),
            actor: String(debouncedActor.value),
            target: String(debouncedTarget.value),
            from: String(query.from),
            to: String(query.to),
        });
    },
    { immediate: true },
);

const open = (row: Record<string, unknown>): void => {
    const selected = auditLogsStore.rows.find((entry) => entry.id === Number(row.id));

    if (selected !== undefined) {
        auditLogsStore.open(selected);
    }
};
</script>
