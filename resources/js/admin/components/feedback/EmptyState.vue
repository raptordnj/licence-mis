<template>
    <div class="glass grid place-items-center gap-2 rounded-xl py-6 text-center">
        <div class="mb-1 h-10 w-10 rounded-full bg-gradient-to-br from-violet-500/20 to-indigo-500/20"></div>
        <p class="font-display text-base font-semibold text-slate-800 dark:text-slate-100">{{ title }}</p>
        <p class="max-w-md text-sm text-pretty text-slate-500 dark:text-slate-400">{{ description }}</p>
        <slot />
    </div>
</template>

<script setup lang="ts">
interface Props {
    title: string;
    description: string;
}

defineProps<Props>();
</script>
