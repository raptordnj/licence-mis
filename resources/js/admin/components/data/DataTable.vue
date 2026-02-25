<template>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 dark:bg-slate-900/40">
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            scope="col"
                            class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                            :class="column.className"
                        >
                            {{ column.label }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td :colspan="columns.length" class="px-4 py-10">
                            <div class="grid gap-2">
                                <UiSkeleton height="0.95rem" />
                                <UiSkeleton height="0.95rem" />
                                <UiSkeleton height="0.95rem" />
                            </div>
                        </td>
                    </tr>
                    <tr v-else-if="rows.length === 0">
                        <td :colspan="columns.length" class="px-4 py-10">
                            <EmptyState :title="emptyTitle" :description="emptyDescription" />
                        </td>
                    </tr>
                    <tr
                        v-for="row in rows"
                        v-else
                        :key="String(row[rowKey])"
                        class="transition hover:bg-slate-50/90 dark:hover:bg-slate-800/30"
                        @click="emit('rowClick', row)"
                    >
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            class="px-4 py-3 align-middle text-slate-700 dark:text-slate-200"
                            :class="column.className"
                        >
                            <slot :name="`cell-${column.key}`" :row="row" :value="row[column.key]">
                                {{ row[column.key] }}
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup lang="ts">
import EmptyState from '@/admin/components/feedback/EmptyState.vue';
import UiSkeleton from '@/admin/components/ui/UiSkeleton.vue';

export interface DataTableColumn {
    key: string;
    label: string;
    className?: string;
}

interface Props {
    columns: DataTableColumn[];
    rows: Record<string, unknown>[];
    rowKey?: string;
    loading?: boolean;
    emptyTitle?: string;
    emptyDescription?: string;
}

withDefaults(defineProps<Props>(), {
    rowKey: 'id',
    loading: false,
    emptyTitle: 'No results',
    emptyDescription: 'Try adjusting filters or search.',
});

const emit = defineEmits<{
    rowClick: [row: Record<string, unknown>];
}>();
</script>
