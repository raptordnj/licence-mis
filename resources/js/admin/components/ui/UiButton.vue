<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        class="inline-flex items-center justify-center gap-2 border font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400/70 disabled:cursor-not-allowed disabled:opacity-60"
        :class="[sizeClass, variantClass]"
    >
        <span
            v-if="loading"
            :class="spinnerClass"
            aria-hidden="true"
        ></span>
        <slot />
    </button>
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    type?: 'button' | 'submit' | 'reset';
    variant?: 'primary' | 'secondary' | 'danger' | 'ghost';
    size?: 'default' | 'compact';
    disabled?: boolean;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    type: 'button',
    variant: 'primary',
    size: 'default',
    disabled: false,
    loading: false,
});

const sizeClass = computed<string>(() => {
    if (props.size === 'compact') {
        return 'rounded-lg px-2.5 py-1.5 text-xs';
    }

    return 'rounded-xl px-3.5 py-2 text-sm';
});

const spinnerClass = computed<string>(() => {
    if (props.size === 'compact') {
        return 'h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-r-transparent';
    }

    return 'h-4 w-4 animate-spin rounded-full border-2 border-current border-r-transparent';
});

const variantClass = computed<string>(() => {
    if (props.variant === 'secondary') {
        return 'border-slate-300 bg-white text-slate-800 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800';
    }

    if (props.variant === 'danger') {
        return 'border-rose-300 bg-rose-500 text-white hover:bg-rose-600 dark:border-rose-400/60 dark:bg-rose-500 dark:hover:bg-rose-600';
    }

    if (props.variant === 'ghost') {
        return 'border-transparent bg-transparent text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800';
    }

    return 'border-cyan-500 bg-cyan-500 text-slate-950 hover:bg-cyan-400 dark:border-cyan-400 dark:bg-cyan-400 dark:hover:bg-cyan-300';
});
</script>
