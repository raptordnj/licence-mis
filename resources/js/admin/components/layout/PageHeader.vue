<template>
    <header class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div class="space-y-1">
            <p v-if="eyebrow !== ''" class="text-xs font-semibold uppercase tracking-wide text-cyan-600 dark:text-cyan-400">
                {{ eyebrow }}
            </p>
            <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ title }}</h1>
            <p v-if="description !== ''" class="max-w-3xl text-sm text-slate-500 dark:text-slate-400">
                {{ description }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
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
