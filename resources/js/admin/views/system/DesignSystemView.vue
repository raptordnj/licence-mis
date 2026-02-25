<template>
    <section class="space-y-4">
        <PageHeader
            title="Design System"
            eyebrow="UI Foundations"
            description="Reference page for typography, spacing, colors, and reusable admin components."
        />

        <UiCard class="space-y-3">
            <h2 class="text-base font-semibold">Typography</h2>
            <div class="space-y-2">
                <p class="text-3xl font-semibold">Heading 1 - SaaS Admin</p>
                <p class="text-xl font-semibold">Heading 2 - Section Title</p>
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Body text should remain concise and readable with clear hierarchy.
                </p>
                <p class="font-mono text-xs text-slate-500 dark:text-slate-400">Monospace for codes and identifiers</p>
            </div>
        </UiCard>

        <UiCard class="space-y-3">
            <h2 class="text-base font-semibold">Colors & Badges</h2>
            <div class="flex flex-wrap items-center gap-2">
                <UiBadge tone="neutral">Neutral</UiBadge>
                <UiBadge tone="success">Success</UiBadge>
                <UiBadge tone="danger">Danger</UiBadge>
                <UiBadge tone="warning">Warning</UiBadge>
                <UiBadge tone="info">Info</UiBadge>
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <StatusBadge value="active" />
                <StatusBadge value="revoked" />
                <StatusBadge value="expired" />
                <StatusBadge value="unknown" />
            </div>
        </UiCard>

        <UiCard class="space-y-3">
            <h2 class="text-base font-semibold">Buttons, Inputs, Selects</h2>
            <div class="flex flex-wrap gap-2">
                <UiButton>Primary</UiButton>
                <UiButton variant="secondary">Secondary</UiButton>
                <UiButton variant="danger">Danger</UiButton>
                <UiButton variant="ghost">Ghost</UiButton>
            </div>
            <div class="grid gap-3 md:grid-cols-2">
                <UiInput v-model="inputSample" label="Input Label" placeholder="Type here" />
                <UiSelect v-model="selectSample" label="Select Label" :options="selectOptions" />
            </div>
        </UiCard>

        <UiCard class="space-y-3">
            <h2 class="text-base font-semibold">Tabs, Skeleton, Empty State</h2>
            <UiTabs v-model="tabSample" :tabs="tabOptions" />
            <div class="grid gap-2 md:grid-cols-3">
                <UiSkeleton height="1.2rem" />
                <UiSkeleton height="1.2rem" />
                <UiSkeleton height="1.2rem" />
            </div>
            <EmptyState title="EmptyState Component" description="Use for no results or no content states." />
        </UiCard>

        <UiCard class="space-y-3">
            <h2 class="text-base font-semibold">DataTable</h2>
            <DataTable :columns="tableColumns" :rows="tableRows" />
        </UiCard>

        <UiCard class="space-y-3">
            <h2 class="text-base font-semibold">JSON Viewer & Error Banner</h2>
            <ErrorBanner message="Example error banner with actionable content." />
            <JsonViewer :value="{ key: 'value', nested: { feature: true } }" />
        </UiCard>
    </section>
</template>

<script setup lang="ts">
import { ref } from 'vue';

import DataTable, { type DataTableColumn } from '@/admin/components/data/DataTable.vue';
import EmptyState from '@/admin/components/feedback/EmptyState.vue';
import ErrorBanner from '@/admin/components/feedback/ErrorBanner.vue';
import JsonViewer from '@/admin/components/feedback/JsonViewer.vue';
import StatusBadge from '@/admin/components/feedback/StatusBadge.vue';
import PageHeader from '@/admin/components/layout/PageHeader.vue';
import UiBadge from '@/admin/components/ui/UiBadge.vue';
import UiButton from '@/admin/components/ui/UiButton.vue';
import UiCard from '@/admin/components/ui/UiCard.vue';
import UiInput from '@/admin/components/ui/UiInput.vue';
import UiSelect from '@/admin/components/ui/UiSelect.vue';
import UiSkeleton from '@/admin/components/ui/UiSkeleton.vue';
import UiTabs from '@/admin/components/ui/UiTabs.vue';

const inputSample = ref('');
const selectSample = ref('option-a');
const tabSample = ref('tab-a');

const selectOptions = [
    { label: 'Option A', value: 'option-a' },
    { label: 'Option B', value: 'option-b' },
];

const tabOptions = [
    { label: 'Tab A', value: 'tab-a' },
    { label: 'Tab B', value: 'tab-b' },
];

const tableColumns: DataTableColumn[] = [
    { key: 'name', label: 'Name' },
    { key: 'role', label: 'Role' },
    { key: 'status', label: 'Status' },
];

const tableRows = [
    { id: 1, name: 'Admin User', role: 'admin', status: 'active' },
    { id: 2, name: 'Support Analyst', role: 'support', status: 'active' },
];
</script>
