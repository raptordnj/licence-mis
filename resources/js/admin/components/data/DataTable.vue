<template>
    <div class="glass overflow-hidden rounded-2xl shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-white/10 text-sm dark:divide-slate-700/30">
                <thead class="bg-gradient-to-r from-violet-500/5 to-indigo-500/5 dark:from-violet-500/10 dark:to-indigo-500/10">
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            scope="col"
                            class="type-label px-4 py-3 text-left"
                            :class="column.className"
                        >
                            {{ column.label }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10 dark:divide-slate-700/20">
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
                        class="transition hover:bg-white/40 dark:hover:bg-slate-800/30"
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
