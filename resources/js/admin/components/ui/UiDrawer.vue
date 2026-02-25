<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 bg-slate-950/45"
            role="dialog"
            aria-modal="true"
            @click.self="emit('update:open', false)"
        >
            <aside
                class="absolute inset-y-0 right-0 w-full max-w-xl overflow-y-auto border-l border-slate-200 bg-white p-5 shadow-2xl dark:border-slate-800 dark:bg-slate-900"
            >
                <header class="mb-4 flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ title }}</h3>
                        <p v-if="description !== ''" class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{ description }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100"
                        @click="emit('update:open', false)"
                    >
                        ✕
                    </button>
                </header>
                <slot />
            </aside>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
interface Props {
    open: boolean;
    title: string;
    description?: string;
}

withDefaults(defineProps<Props>(), {
    description: '',
});

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();
</script>
