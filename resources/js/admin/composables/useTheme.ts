import { computed, ref } from 'vue';

const STORAGE_KEY = 'licence_mis_theme';

type ThemeMode = 'light' | 'dark';

const mode = ref<ThemeMode>('dark');

const applyTheme = (value: ThemeMode): void => {
    const root = document.documentElement;
    root.classList.toggle('dark', value === 'dark');
};

const loadTheme = (): ThemeMode => {
    if (typeof window === 'undefined') {
        return 'dark';
    }

    const stored = window.localStorage.getItem(STORAGE_KEY);

    if (stored === 'light' || stored === 'dark') {
        return stored;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
};

export const useTheme = () => {
    if (typeof window !== 'undefined') {
        mode.value = loadTheme();
        applyTheme(mode.value);
    }

    const isDark = computed<boolean>(() => mode.value === 'dark');

    const toggle = (): void => {
        mode.value = mode.value === 'dark' ? 'light' : 'dark';

        if (typeof window !== 'undefined') {
            window.localStorage.setItem(STORAGE_KEY, mode.value);
            applyTheme(mode.value);
        }
    };

    return {
        mode,
        isDark,
        toggle,
    };
};
