<template>
    <UiModal :open="open" :title="title" :description="description" @update:open="emit('update:open', $event)">
        <p class="text-sm text-slate-600 dark:text-slate-300">{{ body }}</p>
        <div class="flex justify-end gap-2">
            <UiButton variant="secondary" @click="emit('update:open', false)">Cancel</UiButton>
            <UiButton :variant="confirmVariant" :loading="loading" @click="emit('confirm')">
                {{ confirmLabel }}
            </UiButton>
        </div>
    </UiModal>
</template>

<script setup lang="ts">
import UiButton from '@/admin/components/ui/UiButton.vue';
import UiModal from '@/admin/components/ui/UiModal.vue';

interface Props {
    open: boolean;
    title: string;
    description?: string;
    body: string;
    confirmLabel?: string;
    confirmVariant?: 'primary' | 'secondary' | 'danger' | 'ghost';
    loading?: boolean;
}

withDefaults(defineProps<Props>(), {
    description: '',
    confirmLabel: 'Confirm',
    confirmVariant: 'danger',
    loading: false,
});

const emit = defineEmits<{
    'update:open': [value: boolean];
    confirm: [];
}>();
</script>
