<template>
    <Teleport to="body">
        <Transition name="palette">
            <div
                v-if="open"
                class="fixed inset-0 z-[70] bg-slate-950/40 p-4 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                aria-label="Global search"
                @click.self="close"
            >
                <div class="mx-auto mt-[8vh] w-full max-w-2xl overflow-hidden rounded-2xl border border-white/20 bg-white/80 shadow-2xl shadow-violet-500/10 backdrop-blur-xl dark:border-slate-700/30 dark:bg-slate-900/80">
                    <div class="border-b border-white/10 p-3 dark:border-slate-700/20">
                        <input
                            ref="searchInput"
                            v-model.trim="search"
                            type="text"
                            placeholder="Search purchase code, domain, license, item..."
                            class="w-full rounded-xl border border-white/30 bg-white/50 px-3 py-2 text-sm text-slate-800 shadow-sm backdrop-blur focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400/70 focus-visible:shadow-violet-500/10 dark:border-slate-700/50 dark:bg-slate-900/50 dark:text-slate-100"
                        />
                    </div>

                    <div class="max-h-[60vh] overflow-y-auto p-3">
                        <p v-if="loading" class="text-sm text-slate-500 dark:text-slate-400">Searching...</p>
                        <p v-else-if="flatResults.length === 0" class="text-sm text-slate-500 dark:text-slate-400">
                            No matches found.
                        </p>

                        <template v-for="group in groupedResults" :key="group.label">
                            <section v-if="group.items.length > 0" class="mb-4">
                                <p class="type-label mb-2 gradient-text">
                                    {{ group.label }}
                                </p>
                                <button
                                    v-for="item in group.items"
                                    :key="`${group.label}-${item.to}`"
                                    type="button"
                                    class="mb-1 w-full rounded-lg px-3 py-2 text-left transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400/70"
                                    :class="
                                        flatResults[activeIndex]?.to === item.to
                                            ? 'bg-gradient-to-r from-violet-500/10 to-indigo-500/10 shadow-sm dark:from-violet-500/20 dark:to-indigo-500/20'
                                            : 'hover:bg-white/60 dark:hover:bg-slate-800/60'
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
        </Transition>
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

<style scoped>
.palette-enter-active,
.palette-leave-active {
    transition: all 200ms ease;
}

.palette-enter-active > div:last-child,
.palette-leave-active > div:last-child {
    transition: all 200ms ease;
}

.palette-enter-from,
.palette-leave-to {
    opacity: 0;
}

.palette-enter-from > div:last-child,
.palette-leave-to > div:last-child {
    opacity: 0;
    transform: scale(0.95) translateY(-10px);
}
</style>
