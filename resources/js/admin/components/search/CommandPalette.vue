<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[70] bg-slate-950/55 p-4"
            role="dialog"
            aria-modal="true"
            aria-label="Global search"
            @click.self="close"
        >
            <div class="mx-auto mt-[8vh] w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 p-3 dark:border-slate-800">
                    <input
                        ref="searchInput"
                        v-model.trim="search"
                        type="text"
                        placeholder="Search purchase code, domain, license, item..."
                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400/70 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                    />
                </div>

                <div class="max-h-[60vh] overflow-y-auto p-3">
                    <p v-if="loading" class="text-sm text-slate-500 dark:text-slate-400">Searching...</p>
                    <p v-else-if="flatResults.length === 0" class="text-sm text-slate-500 dark:text-slate-400">
                        No matches found.
                    </p>

                    <template v-for="group in groupedResults" :key="group.label">
                        <section v-if="group.items.length > 0" class="mb-4">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                {{ group.label }}
                            </p>
                            <button
                                v-for="item in group.items"
                                :key="`${group.label}-${item.to}`"
                                type="button"
                                class="mb-1 w-full rounded-lg px-3 py-2 text-left transition hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400/70 dark:hover:bg-slate-800"
                                :class="
                                    flatResults[activeIndex]?.to === item.to
                                        ? 'bg-slate-100 dark:bg-slate-800'
                                        : 'bg-transparent'
                                "
                                @click="select(item.to)"
                            >
                                <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ item.title }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ item.subtitle }}</p>
                            </button>
                        </section>
                    </template>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';

import type { GlobalSearchGroupedResult } from '@/admin/types/api';

interface SearchEntry {
    id: number;
    title: string;
    subtitle: string;
    to: string;
}

interface Props {
    open: boolean;
    loading: boolean;
    results: GlobalSearchGroupedResult;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    close: [];
    search: [query: string];
    select: [to: string];
}>();

const search = ref('');
const activeIndex = ref(0);
const searchInput = ref<HTMLInputElement | null>(null);

const groupedResults = computed(() => [
    { label: 'Purchases', items: props.results.purchases },
    { label: 'Licenses', items: props.results.licenses },
    { label: 'Domains', items: props.results.domains },
    { label: 'Items', items: props.results.items },
]);

const flatResults = computed<SearchEntry[]>(() =>
    groupedResults.value
        .flatMap((group) => group.items)
        .filter((entry): entry is SearchEntry => entry !== undefined),
);

const close = (): void => {
    emit('close');
};

const select = (to: string): void => {
    emit('select', to);
};

const onKeyDown = (event: KeyboardEvent): void => {
    if (!props.open) {
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        close();
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        activeIndex.value = Math.min(flatResults.value.length - 1, activeIndex.value + 1);
        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeIndex.value = Math.max(0, activeIndex.value - 1);
        return;
    }

    if (event.key === 'Enter') {
        const selected = flatResults.value[activeIndex.value];

        if (selected !== undefined) {
            event.preventDefault();
            select(selected.to);
        }
    }
};

watch(search, (value) => {
    activeIndex.value = 0;
    emit('search', value);
});

watch(
    () => props.open,
    async (value) => {
        if (!value) {
            search.value = '';
            return;
        }

        await nextTick();
        searchInput.value?.focus();
    },
);

watch(
    () => props.results,
    () => {
        activeIndex.value = 0;
    },
);

window.addEventListener('keydown', onKeyDown);
onUnmounted(() => {
    window.removeEventListener('keydown', onKeyDown);
});
</script>
