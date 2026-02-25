import { ref, watch, type Ref } from 'vue';

export const useDebounced = <T>(source: Ref<T>, delay = 300): Ref<T> => {
    const debounced = ref(source.value) as Ref<T>;
    let timeoutId: number | null = null;

    watch(
        source,
        (value) => {
            if (timeoutId !== null) {
                window.clearTimeout(timeoutId);
            }

            timeoutId = window.setTimeout(() => {
                debounced.value = value;
            }, delay);
        },
        { immediate: true },
    );

    return debounced;
};
