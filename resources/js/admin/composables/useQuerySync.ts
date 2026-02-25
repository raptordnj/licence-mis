import { reactive, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

type Primitive = string | number | boolean;
type QueryShape = Record<string, Primitive>;

export const useQuerySync = <T extends QueryShape>(defaults: T) => {
    const route = useRoute();
    const router = useRouter();

    const state = reactive({ ...defaults }) as T;

    Object.entries(defaults).forEach(([key, fallback]) => {
        const raw = route.query[key];

        if (raw === undefined) {
            return;
        }

        if (typeof fallback === 'number') {
            const parsed = Number(raw);
            state[key as keyof T] = (Number.isFinite(parsed) ? parsed : fallback) as T[keyof T];
            return;
        }

        if (typeof fallback === 'boolean') {
            state[key as keyof T] = (raw === 'true') as T[keyof T];
            return;
        }

        state[key as keyof T] = String(raw) as T[keyof T];
    });

    watch(
        state,
        async (value) => {
            const query = Object.fromEntries(
                Object.entries(value)
                    .filter(([, stateValue]) => {
                        if (typeof stateValue === 'number') {
                            return stateValue > 0;
                        }

                        if (typeof stateValue === 'boolean') {
                            return true;
                        }

                        return stateValue !== '';
                    })
                    .map(([key, stateValue]) => {
                        if (typeof stateValue === 'boolean') {
                            return [key, String(stateValue)];
                        }

                        return [key, stateValue];
                    }),
            );

            await router.replace({ query });
        },
        { deep: true },
    );

    return state;
};
