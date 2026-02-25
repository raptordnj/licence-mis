<template>
    <UiBadge :tone="tone">{{ label }}</UiBadge>
</template>

<script setup lang="ts">
import { computed } from 'vue';

import UiBadge from '@/admin/components/ui/UiBadge.vue';
import { toTitleCase } from '@/admin/utils/format';

interface Props {
    value: string;
}

const props = defineProps<Props>();

const label = computed<string>(() => toTitleCase(props.value));

const tone = computed<'neutral' | 'success' | 'danger' | 'warning' | 'info'>(() => {
    const normalized = props.value.toLowerCase();

    if (['active', 'success', 'valid', 'enabled'].includes(normalized)) {
        return 'success';
    }

    if (['revoked', 'fail', 'disabled', 'forbidden'].includes(normalized)) {
        return 'danger';
    }

    if (['expired', 'pending'].includes(normalized)) {
        return 'warning';
    }

    if (['info', 'unknown'].includes(normalized)) {
        return 'info';
    }

    return 'neutral';
});
</script>
