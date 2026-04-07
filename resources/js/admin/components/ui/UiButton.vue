<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        class="inline-flex items-center justify-center gap-2 border font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400/70 disabled:cursor-not-allowed disabled:opacity-60 active:scale-[0.97]"
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
        return 'border-white/20 bg-white/60 text-slate-800 backdrop-blur hover:bg-white/80 dark:border-slate-700/50 dark:bg-slate-800/60 dark:text-slate-200 dark:hover:bg-slate-800/80';
    }

    if (props.variant === 'danger') {
        return 'border-rose-400/50 bg-gradient-to-r from-rose-500 to-rose-600 text-white shadow-sm hover:shadow-rose-500/25 hover:shadow-lg dark:border-rose-400/30 dark:from-rose-500 dark:to-rose-600';
    }

    if (props.variant === 'ghost') {
        return 'border-transparent bg-transparent text-slate-600 hover:bg-white/60 hover:backdrop-blur dark:text-slate-300 dark:hover:bg-slate-800/60';
    }

    return 'border-violet-400/50 bg-gradient-to-r from-violet-500 to-indigo-500 text-white shadow-sm hover:shadow-violet-500/25 hover:shadow-lg dark:border-violet-400/30 dark:from-violet-500 dark:to-indigo-500';
});
</script>
