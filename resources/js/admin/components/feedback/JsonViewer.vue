<template>
    <details class="glass rounded-xl p-2">
        <summary class="cursor-pointer text-sm font-semibold text-slate-700 dark:text-slate-200">{{ label }}</summary>
        <pre class="mt-2 overflow-x-auto rounded-lg bg-slate-900/95 p-3 text-xs text-emerald-300 backdrop-blur shadow-inner">{{ formatted }}</pre>
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
