<template>
    <div class="pointer-events-none fixed bottom-4 right-4 z-[60] grid w-full max-w-sm gap-2">
        <TransitionGroup name="toast">
            <article
                v-for="message in toastStore.messages"
                :key="message.id"
                class="pointer-events-auto rounded-xl border px-3 py-3 shadow-lg backdrop-blur-xl"
                :class="toneClass(message.tone)"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold">{{ message.title }}</p>
                        <p class="mt-1 text-xs">{{ message.message }}</p>
                    </div>
                    <button type="button" class="text-xs font-semibold opacity-80 hover:opacity-100" @click="toastStore.remove(message.id)">
                        Close
                    </button>
                </div>
            </article>
        </TransitionGroup>
    </div>
</template>

<script setup lang="ts">
import { useToastStore } from '@/admin/stores/toast';

const toastStore = useToastStore();

const toneClass = (tone: 'success' | 'error' | 'info'): string => {
    if (tone === 'success') {
        return 'border-emerald-300/40 bg-emerald-50/80 text-emerald-900 shadow-emerald-500/10 dark:border-emerald-400/20 dark:bg-emerald-500/15 dark:text-emerald-100';
    }

    if (tone === 'error') {
        return 'border-rose-300/40 bg-rose-50/80 text-rose-900 shadow-rose-500/10 dark:border-rose-400/20 dark:bg-rose-500/15 dark:text-rose-100';
    }

    return 'border-violet-300/40 bg-violet-50/80 text-violet-900 shadow-violet-500/10 dark:border-violet-400/20 dark:bg-violet-500/15 dark:text-violet-100';
};
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 250ms ease;
}

.toast-enter-from {
    opacity: 0;
    transform: translateY(12px) scale(0.95);
}

.toast-leave-to {
    opacity: 0;
    transform: translateX(12px) scale(0.95);
}
</style>
