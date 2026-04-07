<template>
    <section class="space-y-4">
        <PageHeader
            title="Validation Logs"
            eyebrow="Operational Logs"
            description="Review verification attempts, failure reasons, and request metadata."
        >
            <template #actions>
                <UiButton variant="secondary" @click="applyQuickFilter('success')">Success</UiButton>
                <UiButton variant="secondary" @click="applyQuickFilter('fail')">Failed</UiButton>
                <UiButton variant="secondary" @click="clearQuickFilter">Clear</UiButton>
                <UiButton @click="exportCsv">Export CSV</UiButton>
            </template>
        </PageHeader>

        <FilterBar>
            <div class="md:col-span-4">
                <UiInput v-model="query.purchaseCode" label="Purchase Code" placeholder="P-AX12-9931-ENV" />
            </div>
            <div class="md:col-span-2">
                <UiSelect v-model="query.result" label="Result" :options="resultOptions" />
            </div>
            <div class="md:col-span-2">
                <UiInput v-model="query.failReason" label="Fail Reason" placeholder="DOMAIN_MISMATCH" />
            </div>
            <div class="md:col-span-2">
                <UiInput v-model="query.domain" label="Domain" />
            </div>
            <div class="md:col-span-2">
                <UiInput v-model="query.ip" label="IP Address" />
            </div>
            <div class="md:col-span-2">
                <UiInput v-model="query.from" label="From" type="date" />
            </div>
            <div class="md:col-span-2">
                <UiInput v-model="query.to" label="To" type="date" />
            </div>
        </FilterBar>

        <ErrorBanner v-if="logsStore.error !== null" :message="logsStore.error.message" />

        <DataTable
            :columns="columns"
            :rows="rows"
            :loading="logsStore.loading"
            empty-title="No logs found"
            empty-description="Update your filters or wait for incoming verification traffic."
            @row-click="openDetails"
        >
            <template #cell-result="{ value }">
                <StatusBadge :value="String(value)" />
            </template>
            <template #cell-time="{ value }">
                {{ formatDateTime(String(value)) }}
            </template>
        </DataTable>

        <div class="flex items-center justify-end gap-2">
            <UiButton size="compact" variant="secondary" :disabled="query.page <= 1" @click="query.page -= 1">Previous</UiButton>
            <span class="pagination-glass text-xs text-slate-600 dark:text-slate-300">
                {{ logsStore.response.current_page }} / {{ logsStore.response.last_page }}
            </span>
            <UiButton size="compact" variant="secondary" :disabled="logsStore.response.current_page >= logsStore.response.last_page" @click="query.page += 1">
                Next
            </UiButton>
        </div>

        <UiDrawer
            :open="logsStore.selectedLog !== null"
            title="Log Details"
            description="Request and response summary with correlation metadata."
            @update:open="logsStore.closeDetails()"
        >
            <div v-if="logsStore.selectedLog !== null" class="space-y-4">
                <div class="grid gap-2 text-sm sm:grid-cols-2">
                    <p><span class="text-slate-500 dark:text-slate-400">Time:</span> {{ formatDateTime(logsStore.selectedLog.time) }}</p>
                    <p><span class="text-slate-500 dark:text-slate-400">Result:</span> {{ logsStore.selectedLog.result }}</p>
                    <p><span class="text-slate-500 dark:text-slate-400">Fail Reason:</span> {{ logsStore.selectedLog.fail_reason ?? 'N/A' }}</p>
                    <p><span class="text-slate-500 dark:text-slate-400">Correlation ID:</span> {{ logsStore.selectedLog.correlation_id }}</p>
                    <p><span class="text-slate-500 dark:text-slate-400">Domain:</span> {{ logsStore.selectedLog.domain_requested }}</p>
                    <p><span class="text-slate-500 dark:text-slate-400">Signature:</span> {{ logsStore.selectedLog.signature_present ? 'Present' : 'Missing' }}</p>
                </div>
                <JsonViewer
                    label="Request / Response Summary"
                    :value="{
                        purchase_code: logsStore.selectedLog.purchase_code,
                        item_name: logsStore.selectedLog.item_name,
                        user_agent: logsStore.selectedLog.user_agent,
                        ip: logsStore.selectedLog.ip,
                    }"
                />
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
import { useLogsStore } from '@/admin/stores/logs';
import { formatDateTime } from '@/admin/utils/format';

const logsStore = useLogsStore();

const query = useQuerySync({
    page: 1,
    perPage: 20,
    result: '',
    failReason: '',
    item: '',
    purchaseCode: '',
    domain: '',
    ip: '',
    from: '',
    to: '',
});

const debouncedCode = useDebounced(useComputedField(query, 'purchaseCode'), 280);
const debouncedDomain = useDebounced(useComputedField(query, 'domain'), 280);
const debouncedIp = useDebounced(useComputedField(query, 'ip'), 280);

const columns: DataTableColumn[] = [
    { key: 'time', label: 'Time' },
    { key: 'result', label: 'Result' },
    { key: 'fail_reason', label: 'Fail Reason' },
    { key: 'domain_requested', label: 'Domain Requested' },
    { key: 'ip', label: 'IP' },
    { key: 'purchase_code', label: 'Purchase Code' },
    { key: 'item_name', label: 'Item' },
];

const resultOptions = [
    { label: 'All', value: '' },
    { label: 'Success', value: 'success' },
    { label: 'Fail', value: 'fail' },
];

const rows = computed(() =>
    logsStore.rows.map((entry) => ({
        id: entry.id,
        time: entry.time,
        result: entry.result,
        fail_reason: entry.fail_reason ?? 'N/A',
        domain_requested: entry.domain_requested,
        ip: entry.ip,
        purchase_code: entry.purchase_code,
        item_name: entry.item_name,
    })),
);

watch(
    () => [
        query.page,
        query.perPage,
        query.result,
        query.failReason,
        query.item,
        debouncedCode.value,
        debouncedDomain.value,
        debouncedIp.value,
        query.from,
        query.to,
    ],
    async () => {
        await logsStore.fetchLogs({
            page: Number(query.page),
            perPage: Number(query.perPage),
            result: String(query.result),
            failReason: String(query.failReason),
            item: String(query.item),
            purchaseCode: String(debouncedCode.value),
            domain: String(debouncedDomain.value),
            ip: String(debouncedIp.value),
            from: String(query.from),
            to: String(query.to),
        });
    },
    { immediate: true },
);

const applyQuickFilter = (value: 'success' | 'fail'): void => {
    query.result = value;
};

const clearQuickFilter = (): void => {
    query.result = '';
};

const openDetails = (row: Record<string, unknown>): void => {
    const selected = logsStore.rows.find((entry) => entry.id === String(row.id));

    if (selected !== undefined) {
        logsStore.openDetails(selected);
    }
};

const exportCsv = (): void => {
    const csvRows = [
        ['time', 'result', 'fail_reason', 'domain', 'ip', 'purchase_code', 'item_name'],
        ...logsStore.rows.map((entry) => [
            entry.time,
            entry.result,
            entry.fail_reason ?? '',
            entry.domain_requested,
            entry.ip,
            entry.purchase_code,
            entry.item_name,
        ]),
    ];
    const csv = csvRows.map((row) => row.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'validation-logs.csv';
    link.click();
    URL.revokeObjectURL(url);
};
</script>
