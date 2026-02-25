<template>
    <label class="grid gap-1.5">
        <span v-if="label !== ''" class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            {{ label }}
        </span>
        <select
            :model-value="modelValue"
            :disabled="disabled"
            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400/70 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
            @change="emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
        >
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
    </label>
</template>

<script setup lang="ts">
interface SelectOption {
    label: string;
    value: string;
}

interface Props {
    modelValue: string;
    options: SelectOption[];
    label?: string;
    disabled?: boolean;
}

withDefaults(defineProps<Props>(), {
    label: '',
    disabled: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>
