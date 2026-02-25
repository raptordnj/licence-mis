<template>
    <div class="grid min-h-dvh place-items-center bg-slate-100 px-4 py-10 dark:bg-slate-950">
        <UiCard class="w-full max-w-md">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-cyan-600 dark:text-cyan-400">Two-Factor Required</p>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">Verify Your Login</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Enter a one-time code from your authenticator app, or use a recovery code.
            </p>

            <form class="mt-5 grid gap-3" @submit.prevent="submit">
                <UiTabs v-model="mode" :tabs="tabs" />

                <UiInput
                    v-if="mode === 'totp'"
                    v-model="totpCode"
                    label="Authenticator Code"
                    placeholder="123456"
                    required
                />
                <UiInput
                    v-else
                    v-model="recoveryCode"
                    label="Recovery Code"
                    placeholder="RECOVERYCODE"
                    required
                />

                <ErrorBanner v-if="activeError !== null" :message="activeError.message" />

                <div class="flex items-center justify-between gap-2">
                    <UiButton variant="secondary" @click="backToLogin">Back</UiButton>
                    <UiButton type="submit" :loading="authStore.loading">Continue</UiButton>
                </div>
            </form>
        </UiCard>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';

import ErrorBanner from '@/admin/components/feedback/ErrorBanner.vue';
import UiButton from '@/admin/components/ui/UiButton.vue';
import UiCard from '@/admin/components/ui/UiCard.vue';
import UiInput from '@/admin/components/ui/UiInput.vue';
import UiTabs from '@/admin/components/ui/UiTabs.vue';
import { useAuthStore } from '@/admin/stores/auth';
import { useToastStore } from '@/admin/stores/toast';
import type { ApiError } from '@/admin/types/api';

const authStore = useAuthStore();
const toastStore = useToastStore();
const router = useRouter();

const mode = ref<'totp' | 'recovery'>('totp');
const totpCode = ref('');
const recoveryCode = ref('');
const localError = ref<ApiError | null>(null);

const tabs = [
    { label: 'Authenticator Code', value: 'totp' },
    { label: 'Recovery Code', value: 'recovery' },
];

const activeError = computed(() => localError.value ?? authStore.error);

const backToLogin = async (): Promise<void> => {
    authStore.pendingTwoFactor = null;
    await router.push('/admin/login');
};

const submit = async (): Promise<void> => {
    localError.value = null;

    try {
        await authStore.submitTwoFactor({
            code: mode.value === 'totp' ? totpCode.value : undefined,
            recoveryCode: mode.value === 'recovery' ? recoveryCode.value : undefined,
        });

        toastStore.push({
            tone: 'success',
            title: '2FA passed',
            message: 'You are now signed in.',
        });

        await router.push('/admin/dashboard');
    } catch {
        localError.value = authStore.error ?? {
            code: 'TWO_FACTOR_INVALID',
            message: 'Unable to validate two-factor challenge.',
        };
    }
};
</script>
