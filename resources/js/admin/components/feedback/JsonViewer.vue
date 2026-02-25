<template>
    <details class="rounded-xl border border-slate-200 bg-slate-50 p-2 dark:border-slate-800 dark:bg-slate-950/60">
        <summary class="cursor-pointer text-sm font-semibold text-slate-700 dark:text-slate-200">{{ label }}</summary>
        <pre class="mt-2 overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100">{{ formatted }}</pre>
    </details>
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    label?: string;
    value: unknown;
}

const props = withDefaults(defineProps<Props>(), {
    label: 'Raw JSON',
});

const formatted = computed<string>(() => JSON.stringify(props.value, null, 2));
</script>
