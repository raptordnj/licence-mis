<template>
    <header class="mb-5 flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0 space-y-1">
            <p v-if="eyebrow !== ''" class="font-display text-xs font-semibold uppercase tracking-wide gradient-text">
                {{ eyebrow }}
            </p>
            <h1 class="font-display text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100 lg:text-3xl">{{ title }}</h1>
            <p v-if="description !== ''" class="max-w-3xl text-sm leading-relaxed text-pretty text-slate-500 dark:text-slate-400">
                {{ description }}
            </p>
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>

<script setup lang="ts">
interface Props {
    title: string;
    description?: string;
    eyebrow?: string;
}

withDefaults(defineProps<Props>(), {
    description: '',
    eyebrow: '',
});
</script>
