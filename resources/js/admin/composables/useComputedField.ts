import { computed, type WritableComputedRef } from 'vue';

export const useComputedField = <T extends Record<string, unknown>, K extends keyof T>(
    target: T,
    key: K,
): WritableComputedRef<string> =>
    computed({
        get: () => String(target[key]),
        set: (value: string) => {
            target[key] = value as T[K];
        },
    });
