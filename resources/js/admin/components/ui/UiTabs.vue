<template>
    <div class="glass inline-flex rounded-xl p-1">
        <button
            v-for="tab in tabs"
            :key="tab.value"
            type="button"
            class="rounded-lg px-3 py-1.5 text-sm font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400/70"
            :class="
                modelValue === tab.value
                    ? 'bg-gradient-to-r from-violet-500 to-indigo-500 text-white shadow-sm shadow-violet-500/20'
                    : 'text-slate-600 hover:bg-white/60 hover:backdrop-blur dark:text-slate-300 dark:hover:bg-slate-800/60'
            "
            @click="emit('update:modelValue', tab.value)"
        >
            {{ tab.label }}
        </button>
    </div>
</template>

<script setup lang="ts">
interface TabOption {
    label: string;
    value: string;
}

interface Props {
    modelValue: string;
    tabs: TabOption[];
}

defineProps<Props>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>
