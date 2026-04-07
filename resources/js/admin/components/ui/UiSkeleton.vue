<template>
    <span class="block rounded-md shimmer" :style="style"></span>
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    width?: string;
    height?: string;
}

const props = withDefaults(defineProps<Props>(), {
    width: '100%',
    height: '1rem',
});

const style = computed(() => ({
    width: props.width,
    height: props.height,
}));
</script>
