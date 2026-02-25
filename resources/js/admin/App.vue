<template>
    <RouterView v-slot="{ Component }">
        <Transition name="fade-rise" mode="out-in">
            <component :is="Component" />
        </Transition>
    </RouterView>
    <ToastViewport />
</template>

<script setup lang="ts">
import ToastViewport from '@/admin/components/feedback/ToastViewport.vue';
</script>

<style scoped>
.fade-rise-enter-active,
.fade-rise-leave-active {
    transition: opacity 180ms ease, transform 180ms ease;
}

.fade-rise-enter-from,
.fade-rise-leave-to {
    opacity: 0;
    transform: translateY(6px);
}
</style>
