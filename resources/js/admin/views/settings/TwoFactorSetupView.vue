<template>
    <section class="space-y-4">
        <PageHeader
            title="Two-Factor Setup Wizard"
            eyebrow="Security"
            description="Generate a TOTP secret, scan QR, and confirm with a one-time code."
        >
            <template #actions>
                <UiButton variant="secondary" @click="router.push('/admin/settings')">Back to Settings</UiButton>
            </template>
        </PageHeader>

        <ErrorBanner v-if="settingsStore.error !== null" :message="settingsStore.error.message" />

        <UiCard class="space-y-4">
            <div class="flex flex-wrap gap-2">
                <UiButton :loading="settingsStore.saving" @click="generateSecret">Generate New Secret</UiButton>
            </div>

            <div v-if="settingsStore.twoFactorSetup !== null" class="grid gap-4 lg:grid-cols-2">
                <div class="glass rounded-xl p-4">
                    <p class="type-label">Authenticator QR</p>
                    <img v-if="qrDataUrl !== ''" :src="qrDataUrl" alt="TOTP QR code" class="mt-2 w-full max-w-[280px] rounded-lg bg-white p-2" />
                    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">Secret: <code>{{ settingsStore.twoFactorSetup.secret }}</code></p>
                </div>
                <div class="glass rounded-xl p-4">
                    <UiInput v-model="code" label="Enter 6-digit code" placeholder="123456" />
                    <UiButton class="mt-3" :loading="settingsStore.saving" @click="confirmTwoFactor">Confirm 2FA</UiButton>
                </div>
            </div>
        </UiCard>
    </section>
</template>

<script setup lang="ts">
import QRCode from 'qrcode';
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';

import ErrorBanner from '@/admin/components/feedback/ErrorBanner.vue';
import PageHeader from '@/admin/components/layout/PageHeader.vue';
import UiButton from '@/admin/components/ui/UiButton.vue';
import UiCard from '@/admin/components/ui/UiCard.vue';
import UiInput from '@/admin/components/ui/UiInput.vue';
import { useAuthStore } from '@/admin/stores/auth';
import { useSettingsStore } from '@/admin/stores/settings';
import { useToastStore } from '@/admin/stores/toast';

const settingsStore = useSettingsStore();
const authStore = useAuthStore();
const toastStore = useToastStore();
const router = useRouter();

const code = ref('');
const qrDataUrl = ref('');

const otpauthUri = computed(() => {
    if (settingsStore.twoFactorSetup === null) {
        return '';
    }

    const label = encodeURIComponent(`Licence MIS:${authStore.admin?.email ?? 'admin'}`);
    const issuer = encodeURIComponent('Licence MIS');

    return `otpauth://totp/${label}?secret=${settingsStore.twoFactorSetup.secret}&issuer=${issuer}`;
});

const generateSecret = async (): Promise<void> => {
    await settingsStore.setupTwoFactor();

    if (otpauthUri.value !== '') {
        qrDataUrl.value = await QRCode.toDataURL(otpauthUri.value, {
            width: 320,
            margin: 1,
        });
    }
};

const confirmTwoFactor = async (): Promise<void> => {
    await settingsStore.confirmTwoFactor({
        code: code.value,
    });
    await authStore.fetchProfile();

    toastStore.push({
        tone: settingsStore.error === null ? 'success' : 'error',
        title: settingsStore.error === null ? '2FA enabled' : 'Confirmation failed',
        message:
            settingsStore.error === null
                ? 'Two-factor authentication is now active.'
                : settingsStore.error.message,
    });

    if (settingsStore.error === null) {
        await router.push('/admin/settings/2fa/recovery-codes');
    }
};
</script>
