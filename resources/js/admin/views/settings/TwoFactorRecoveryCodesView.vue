<template>
    <section class="space-y-4">
        <PageHeader
            title="Recovery Codes"
            eyebrow="Security"
            description="Store these one-time recovery codes in a safe place. Each code can be used once."
        >
            <template #actions>
                <UiButton variant="secondary" @click="router.push('/admin/settings')">Back to Settings</UiButton>
            </template>
        </PageHeader>

        <UiCard v-if="codes.length > 0" class="space-y-4">
            <ul class="grid gap-2 sm:grid-cols-2">
                <li v-for="code in codes" :key="code" class="glass rounded-lg px-3 py-2 font-mono text-sm">
                    {{ code }}
                </li>
            </ul>
            <div class="flex flex-wrap gap-2">
                <UiButton @click="downloadCodes">Download TXT</UiButton>
                <UiButton variant="secondary" @click="printCodes">Print</UiButton>
            </div>
        </UiCard>

        <UiCard v-else>
            <p class="text-sm text-slate-600 dark:text-slate-300">
                No recovery codes found in memory. Regenerate codes from the 2FA setup page.
            </p>
        </UiCard>
    </section>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRouter } from 'vue-router';

import PageHeader from '@/admin/components/layout/PageHeader.vue';
import UiButton from '@/admin/components/ui/UiButton.vue';
import UiCard from '@/admin/components/ui/UiCard.vue';
import { useSettingsStore } from '@/admin/stores/settings';

const settingsStore = useSettingsStore();
const router = useRouter();

const codes = computed(() => settingsStore.twoFactorSetup?.recovery_codes ?? []);

const downloadCodes = (): void => {
    const body = codes.value.join('\n');
    const blob = new Blob([body], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'recovery-codes.txt';
    link.click();
    URL.revokeObjectURL(url);
};

const printCodes = (): void => {
    const body = codes.value.join('\n');
    const printWindow = window.open('', '_blank', 'width=640,height=720');

    if (printWindow === null) {
        return;
    }

    printWindow.document.write(`<pre>${body}</pre>`);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
    printWindow.close();
};
</script>
