<template>
    <Teleport to="body">
        <Transition name="drawer">
            <div
                v-if="open"
                class="fixed inset-0 z-50 backdrop-blur-sm bg-slate-950/40"
                role="dialog"
                aria-modal="true"
                @click.self="emit('update:open', false)"
            >
                <aside
                    class="absolute inset-y-0 right-0 w-full max-w-xl overflow-y-auto border-l border-violet-500/20 bg-white/80 p-5 shadow-2xl shadow-violet-500/5 backdrop-blur-2xl dark:border-violet-400/10 dark:bg-slate-900/80"
                >
                    <header class="mb-4 flex items-start justify-between gap-4">
                        <div>
                            <h3 class="font-display text-lg font-semibold text-slate-900 dark:text-slate-100">{{ title }}</h3>
                            <p v-if="description !== ''" class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                {{ description }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg p-1 text-slate-500 transition hover:bg-white/60 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800/60 dark:hover:text-slate-100"
                            @click="emit('update:open', false)"
                        >
                            ✕
                        </button>
                    </header>
                    <slot />
                </aside>
            </div>
        </Transition>
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

<style scoped>
.drawer-enter-active,
.drawer-leave-active {
    transition: all 250ms ease;
}

.drawer-enter-active aside,
.drawer-leave-active aside {
    transition: transform 250ms ease;
}

.drawer-enter-from,
.drawer-leave-to {
    opacity: 0;
}

.drawer-enter-from aside,
.drawer-leave-to aside {
    transform: translateX(100%);
}
</style>
