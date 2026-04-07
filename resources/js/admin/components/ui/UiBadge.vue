<template>
    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold backdrop-blur-sm" :class="toneClass">
        <slot />
    </span>
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    tone?: 'neutral' | 'success' | 'danger' | 'warning' | 'info';
}

const props = withDefaults(defineProps<Props>(), {
    tone: 'neutral',
});

const toneClass = computed<string>(() => {
    if (props.tone === 'success') {
        return 'bg-emerald-100/80 text-emerald-800 shadow-emerald-500/10 shadow-sm dark:bg-emerald-500/15 dark:text-emerald-300 dark:shadow-emerald-500/5';
    }

    if (props.tone === 'danger') {
        return 'bg-rose-100/80 text-rose-800 shadow-rose-500/10 shadow-sm dark:bg-rose-500/15 dark:text-rose-300 dark:shadow-rose-500/5';
    }

    if (props.tone === 'warning') {
        return 'bg-amber-100/80 text-amber-800 shadow-amber-500/10 shadow-sm dark:bg-amber-500/15 dark:text-amber-300 dark:shadow-amber-500/5';
    }

    if (props.tone === 'info') {
        return 'bg-violet-100/80 text-violet-800 shadow-violet-500/10 shadow-sm dark:bg-violet-500/15 dark:text-violet-300 dark:shadow-violet-500/5';
    }

    return 'bg-slate-100/80 text-slate-700 shadow-sm dark:bg-slate-800/60 dark:text-slate-300';
});
</script>
