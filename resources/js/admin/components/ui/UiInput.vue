<template>
    <label class="grid gap-1.5">
        <span v-if="label !== ''" class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            {{ label }}
        </span>
        <input
            :type="type"
            :model-value="modelValue"
            :placeholder="placeholder"
            :autocomplete="autocomplete"
            :required="required"
            :disabled="disabled"
            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 transition placeholder:text-slate-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400/70 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500"
            @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        />
    </label>
</template>

<script setup lang="ts">
interface Props {
    modelValue: string;
    label?: string;
    placeholder?: string;
    type?: string;
    autocomplete?: string;
    required?: boolean;
    disabled?: boolean;
}

withDefaults(defineProps<Props>(), {
    label: '',
    placeholder: '',
    type: 'text',
    autocomplete: 'off',
    required: false,
    disabled: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>
