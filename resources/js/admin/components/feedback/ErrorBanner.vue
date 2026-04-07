<template>
    <div
        class="rounded-xl border border-rose-300/40 bg-rose-50/80 px-3 py-2 text-sm text-rose-700 shadow-sm shadow-rose-500/5 backdrop-blur dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200"
        role="alert"
    >
        <p class="font-semibold">{{ title }}</p>
        <p class="mt-0.5">{{ message }}</p>
    </div>
</template>

<script setup lang="ts">
interface Props {
    title?: string;
    message: string;
}

withDefaults(defineProps<Props>(), {
    title: 'Something went wrong',
});
</script>
