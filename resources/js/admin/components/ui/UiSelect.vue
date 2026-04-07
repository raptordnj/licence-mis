<template>
    <label class="grid gap-1.5">
        <span v-if="label !== ''" class="type-label">
            {{ label }}
        </span>
        <select
            :model-value="modelValue"
            :disabled="disabled"
            class="w-full rounded-xl border border-white/30 bg-white/50 px-3 py-2 text-sm text-slate-800 shadow-sm backdrop-blur transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400/70 focus-visible:shadow-violet-500/10 focus-visible:shadow-md disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700/50 dark:bg-slate-900/50 dark:text-slate-100"
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
