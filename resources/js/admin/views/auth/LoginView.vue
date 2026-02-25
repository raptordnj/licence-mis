<template>
    <div class="grid min-h-dvh place-items-center bg-slate-100 px-4 py-10 dark:bg-slate-950">
        <UiCard class="w-full max-w-md">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-cyan-600 dark:text-cyan-400">Envato License MIS</p>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">Admin Login</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Sign in to manage licenses, monitor validation logs, and review audit activity.
            </p>

            <form class="mt-5 grid gap-3" @submit.prevent="submit">
                <UiInput v-model="form.email" type="email" label="Email" autocomplete="email" required />
                <UiInput v-model="form.password" type="password" label="Password" autocomplete="current-password" required />

                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input
                        v-model="form.rememberMe"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-cyan-500 focus:ring-cyan-400 dark:border-slate-700 dark:bg-slate-900"
                    />
                    Remember me
                </label>

                <ErrorBanner v-if="activeError !== null" :message="activeError.message" />

                <UiButton type="submit" :loading="authStore.loading" class="justify-center">Sign In</UiButton>
            </form>
        </UiCard>
    </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';

import ErrorBanner from '@/admin/components/feedback/ErrorBanner.vue';
import UiButton from '@/admin/components/ui/UiButton.vue';
import UiCard from '@/admin/components/ui/UiCard.vue';
import UiInput from '@/admin/components/ui/UiInput.vue';
import { useAuthStore } from '@/admin/stores/auth';
import { useToastStore } from '@/admin/stores/toast';
import type { ApiError } from '@/admin/types/api';

const authStore = useAuthStore();
const toastStore = useToastStore();
const router = useRouter();

const localError = ref<ApiError | null>(null);

const form = reactive({
    email: 'admin@example.com',
    password: 'password',
    rememberMe: true,
});

const activeError = computed<ApiError | null>(() => localError.value ?? authStore.error);

const submit = async (): Promise<void> => {
    localError.value = null;

    try {
        await authStore.login({
            email: form.email,
            password: form.password,
            remember_me: form.rememberMe,
        });

        toastStore.push({
            tone: 'success',
            title: 'Welcome back',
            message: 'Authentication successful.',
        });

        await router.push('/admin/dashboard');
    } catch {
        const error = authStore.error;

        if (error !== null && (error.code === 'TWO_FACTOR_REQUIRED' || error.code === 'TWO_FACTOR_INVALID')) {
            await router.push('/admin/login/2fa');
            return;
        }

        localError.value = error ?? {
            code: 'INTERNAL_ERROR',
            message: 'Unable to authenticate.',
        };
    }
};
</script>
