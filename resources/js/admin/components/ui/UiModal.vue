<template>
    <Teleport to="body">
        <Transition name="modal">
            <div
                v-if="open"
                class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                @click.self="emit('update:open', false)"
            >
                <div
                    class="glass-heavy w-full max-w-lg rounded-2xl p-5 shadow-xl shadow-violet-500/5"
                >
                    <div class="mb-4 h-0.5 w-full rounded-full bg-gradient-to-r from-violet-500 to-indigo-500"></div>
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

                    <div class="grid gap-4">
                        <slot />
                    </div>
                </div>
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
.modal-enter-active,
.modal-leave-active {
    transition: all 200ms ease;
}

.modal-enter-active > div:last-child,
.modal-leave-active > div:last-child {
    transition: all 200ms ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-from > div:last-child,
.modal-leave-to > div:last-child {
    opacity: 0;
    transform: scale(0.95);
}
</style>
