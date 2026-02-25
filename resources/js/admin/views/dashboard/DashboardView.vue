<template>
    <section class="space-y-4">
        <PageHeader
            title="Dashboard"
            eyebrow="Overview"
            description="Monitor license health, validation outcomes, and high-priority operational events."
        >
            <template #actions>
                <UiTabs v-model="selectedRange" :tabs="rangeTabs" />
            </template>
        </PageHeader>

        <ErrorBanner v-if="dashboardStore.error !== null" :message="dashboardStore.error.message" />

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <UiCard v-for="metric in metrics" :key="metric.label">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ metric.label }}</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ metric.value }}</p>
            </UiCard>
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <UiCard class="xl:col-span-2">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Checks Over Time</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Success vs failures</p>
                </div>
                <svg viewBox="0 0 600 260" class="h-64 w-full">
                    <polyline
                        fill="none"
                        stroke="currentColor"
                        class="text-cyan-500"
                        stroke-width="3"
                        :points="checksPolyline"
                    />
                    <polyline
                        fill="none"
                        stroke="currentColor"
                        class="text-rose-500"
                        stroke-width="2"
                        :points="failuresPolyline"
                    />
                </svg>
                <div class="mt-2 flex items-center gap-4 text-xs text-slate-500 dark:text-slate-400">
                    <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-cyan-500"></span>Checks</span>
                    <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-rose-500"></span>Failures</span>
                </div>
            </UiCard>

            <UiCard>
                <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Top Failure Reasons</h2>
                <ul class="mt-3 space-y-2">
                    <li v-for="reason in dashboardStore.payload.top_failure_reasons" :key="reason.reason" class="space-y-1">
                        <div class="flex items-center justify-between text-xs text-slate-600 dark:text-slate-300">
                            <span>{{ reason.reason }}</span>
                            <span>{{ reason.count }}</span>
                        </div>
                        <div class="h-2 rounded-full bg-slate-200 dark:bg-slate-800">
                            <span
                                class="block h-2 rounded-full bg-rose-500"
                                :style="{ width: `${(reason.count / maxFailureCount) * 100}%` }"
                            ></span>
                        </div>
                    </li>
                </ul>
            </UiCard>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <UiCard>
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Top Domains by Checks</h2>
                </div>
                <DataTable
                    :columns="domainColumns"
                    :rows="domainRows"
                    :loading="dashboardStore.loading"
                    empty-title="No domain activity"
                    empty-description="Run validations to populate domain activity trends."
                />
            </UiCard>

            <UiCard>
                <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Recent Activity</h2>
                <ul class="mt-3 space-y-2">
                    <li
                        v-for="activity in dashboardStore.payload.recent_activity"
                        :key="activity.id"
                        class="rounded-xl border border-slate-200 p-3 text-sm dark:border-slate-800"
                    >
                        <p class="font-medium text-slate-800 dark:text-slate-100">{{ activity.title }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ activity.actor ?? 'System' }} • {{ formatDateTime(activity.timestamp) }}
                        </p>
                    </li>
                </ul>
            </UiCard>
        </div>

        <UiCard>
            <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Quick Actions</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                <UiButton variant="secondary" @click="goTo('/admin/checker')">Verify Purchase Code</UiButton>
                <UiButton variant="secondary" @click="goTo('/admin/items')">Create Item</UiButton>
                <UiButton variant="secondary" @click="goTo('/admin/licenses')">Search License</UiButton>
            </div>
        </UiCard>
    </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';

import DataTable, { type DataTableColumn } from '@/admin/components/data/DataTable.vue';
import ErrorBanner from '@/admin/components/feedback/ErrorBanner.vue';
import PageHeader from '@/admin/components/layout/PageHeader.vue';
import UiCard from '@/admin/components/ui/UiCard.vue';
import UiTabs from '@/admin/components/ui/UiTabs.vue';
import UiButton from '@/admin/components/ui/UiButton.vue';
import { useDashboardStore, type DashboardRange } from '@/admin/stores/dashboard';
import { formatDateTime, formatPercent } from '@/admin/utils/format';

const dashboardStore = useDashboardStore();
const router = useRouter();

const selectedRange = ref<DashboardRange>(dashboardStore.range);
const rangeTabs = [
    { label: 'Today', value: 'today' },
    { label: '7d', value: '7d' },
    { label: '30d', value: '30d' },
    { label: 'Custom', value: 'custom' },
];

const metrics = computed(() => [
    { label: 'Total Items', value: dashboardStore.payload.metrics.total_items.toLocaleString() },
    { label: 'Total Licenses', value: dashboardStore.payload.metrics.total_licenses.toLocaleString() },
    {
        label: 'Active vs Revoked',
        value: `${dashboardStore.payload.metrics.active_licenses} / ${dashboardStore.payload.metrics.revoked_licenses}`,
    },
    {
        label: 'Bound vs Unbound',
        value: `${dashboardStore.payload.metrics.bound_licenses} / ${dashboardStore.payload.metrics.unbound_licenses}`,
    },
    { label: 'Checks (Today)', value: dashboardStore.payload.metrics.checks_today.toLocaleString() },
    { label: 'Checks (7 Days)', value: dashboardStore.payload.metrics.checks_last_7_days.toLocaleString() },
    { label: 'Failure Rate', value: formatPercent(dashboardStore.payload.metrics.failure_rate_percent) },
]);

const maxChecks = computed(() =>
    Math.max(...dashboardStore.payload.checks_over_time.map((point) => point.checks), 1),
);
const maxFailures = computed(() =>
    Math.max(...dashboardStore.payload.checks_over_time.map((point) => point.failures), 1),
);
const maxFailureCount = computed(() =>
    Math.max(...dashboardStore.payload.top_failure_reasons.map((entry) => entry.count), 1),
);

const createPolyline = (values: number[], max: number, height = 220, width = 580): string => {
    if (values.length === 0) {
        return '';
    }

    return values
        .map((value, index) => {
            const x = (index / Math.max(values.length - 1, 1)) * width + 10;
            const y = height - (value / max) * (height - 20);
            return `${x},${y}`;
        })
        .join(' ');
};

const checksPolyline = computed(() =>
    createPolyline(
        dashboardStore.payload.checks_over_time.map((point) => point.checks),
        maxChecks.value,
    ),
);
const failuresPolyline = computed(() =>
    createPolyline(
        dashboardStore.payload.checks_over_time.map((point) => point.failures),
        maxFailures.value,
    ),
);

const domainColumns: DataTableColumn[] = [
    { key: 'domain', label: 'Domain' },
    { key: 'checks', label: 'Checks' },
    { key: 'failures', label: 'Failures' },
];

const domainRows = computed(() =>
    dashboardStore.payload.top_domains.map((entry) => ({
        id: entry.domain,
        domain: entry.domain,
        checks: entry.checks,
        failures: entry.failures,
    })),
);

watch(selectedRange, (value) => {
    dashboardStore.setRange(value);
});

onMounted(async () => {
    await dashboardStore.fetchDashboard();
});

const goTo = async (to: string): Promise<void> => {
    await router.push(to);
};
</script>
