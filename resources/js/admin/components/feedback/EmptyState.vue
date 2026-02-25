<template>
    <div class="grid place-items-center gap-2 py-2 text-center">
        <p class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ title }}</p>
        <p class="max-w-md text-sm text-slate-500 dark:text-slate-400">{{ description }}</p>
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
