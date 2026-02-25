<template>
    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold" :class="toneClass">
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
        return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300';
    }

    if (props.tone === 'danger') {
        return 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300';
    }

    if (props.tone === 'warning') {
        return 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300';
    }

    if (props.tone === 'info') {
        return 'bg-cyan-100 text-cyan-800 dark:bg-cyan-500/15 dark:text-cyan-300';
    }

    return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
});
</script>
