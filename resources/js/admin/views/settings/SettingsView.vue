<template>
    <section class="space-y-4">
        <PageHeader
            title="Settings"
            eyebrow="System Configuration"
            description="Manage integration secrets, security controls, and license policy behavior."
        />

        <ErrorBanner v-if="settingsStore.error !== null" :message="settingsStore.error.message" />

        <UiCard class="space-y-3">
            <h2 class="section-heading font-display text-base font-semibold text-slate-900 dark:text-slate-100">1) Integration</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <p class="text-sm">
                    Envato token:
                    <StatusBadge :value="settingsStore.settings.has_envato_api_token ? 'present' : 'missing'" />
                </p>
                <p class="text-sm text-slate-600 dark:text-slate-300">Envato API Base URL: {{ settingsStore.settings.envato_api_base_url }}</p>
                <p class="text-sm">
                    Mock mode:
                    <StatusBadge :value="integration.mockMode ? 'enabled' : 'disabled'" />
                </p>
            </div>
            <div class="grid gap-3 lg:grid-cols-2">
                <UiInput v-model="integration.envatoToken" label="Rotate Envato Token" type="password" placeholder="Paste new token" />
                <UiInput
                    v-model="integration.hmacKey"
                    label="Rotate HMAC Secret"
                    type="password"
                    placeholder="32+ characters"
                />
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input
                    v-model="integration.mockMode"
                    type="checkbox"
                    class="h-4 w-4 rounded border-slate-300 text-violet-500 focus:ring-violet-400 dark:border-slate-700 dark:bg-slate-900"
                />
                Enable Envato mock mode
            </label>
            <div class="flex flex-wrap gap-2">
                <UiButton :disabled="!canManage" :loading="settingsStore.saving" @click="saveIntegration">Save Integration Secrets</UiButton>
                <UiButton variant="secondary" :disabled="!canManage" :loading="settingsStore.saving" @click="saveMockMode">Save Mock Mode</UiButton>
                <UiButton variant="secondary" :disabled="!canManage" :loading="testingToken" @click="testToken">Test Token</UiButton>
            </div>
        </UiCard>

        <UiCard class="space-y-3">
            <h2 class="section-heading font-display text-base font-semibold text-slate-900 dark:text-slate-100">2) Security</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Active secret version:
                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ settingsStore.settings.active_secret_version }}</span>
                </p>
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Rate limit/minute:
                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ security.rateLimit }}</span>
                </p>
            </div>
            <div class="grid gap-3 lg:grid-cols-2">
                <UiInput v-model="security.rateLimit" label="Rate Limit (view-only for non super-admin)" type="number" :disabled="!isSuperAdmin" />
                <UiInput
                    v-model="security.maxResets"
                    label="Max Resets Allowed"
                    type="number"
                    :disabled="!isSuperAdmin"
                />
            </div>
            <div class="flex flex-wrap gap-2">
                <UiButton :disabled="!isSuperAdmin" @click="saveSecurity">Save Security Settings</UiButton>
                <UiButton variant="secondary" :disabled="!canManage" @click="goToTwoFactorSetup">2FA Setup Wizard</UiButton>
                <UiButton variant="secondary" :disabled="!canManage" @click="goToRecoveryCodes">Recovery Codes</UiButton>
                <UiButton variant="danger" :disabled="!canManage" @click="logoutOtherDevices">Logout Other Devices</UiButton>
            </div>
        </UiCard>

        <UiCard class="space-y-3">
            <h2 class="section-heading font-display text-base font-semibold text-slate-900 dark:text-slate-100">3) License Policies</h2>
            <div class="grid gap-3 md:grid-cols-3">
                <label class="flex items-center gap-2 text-sm">
                    <input
                        v-model="policies.treatWwwAsSame"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-violet-500 focus:ring-violet-400 dark:border-slate-700 dark:bg-slate-900"
                    />
                    Treat `www` as same domain
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input
                        v-model="policies.allowLocalhost"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-violet-500 focus:ring-violet-400 dark:border-slate-700 dark:bg-slate-900"
                    />
                    Allow localhost
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input
                        v-model="policies.allowIpDomains"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-violet-500 focus:ring-violet-400 dark:border-slate-700 dark:bg-slate-900"
                    />
                    Allow IP domains
                </label>
            </div>
            <UiButton :disabled="!canManage" @click="savePolicies">Save License Policies</UiButton>
        </UiCard>

        <UiCard class="space-y-3">
            <h2 class="section-heading font-display text-base font-semibold text-slate-900 dark:text-slate-100">4) System</h2>
            <div class="grid gap-2 sm:grid-cols-3">
                <p class="text-sm text-slate-600 dark:text-slate-300">Queue status: <span class="font-medium">Healthy</span></p>
                <p class="text-sm text-slate-600 dark:text-slate-300">Cache status: <span class="font-medium">Warm</span></p>
                <p class="text-sm text-slate-600 dark:text-slate-300">Last cron run: <span class="font-medium">{{ lastCronRun }}</span></p>
            </div>
        </UiCard>
    </section>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';

import ErrorBanner from '@/admin/components/feedback/ErrorBanner.vue';
import StatusBadge from '@/admin/components/feedback/StatusBadge.vue';
import PageHeader from '@/admin/components/layout/PageHeader.vue';
import UiButton from '@/admin/components/ui/UiButton.vue';
import UiCard from '@/admin/components/ui/UiCard.vue';
import UiInput from '@/admin/components/ui/UiInput.vue';
import { useAuthStore } from '@/admin/stores/auth';
import { useSettingsStore } from '@/admin/stores/settings';
import { useToastStore } from '@/admin/stores/toast';
import { formatDateTime } from '@/admin/utils/format';

const settingsStore = useSettingsStore();
const authStore = useAuthStore();
const toastStore = useToastStore();
const router = useRouter();

const testingToken = ref(false);

const integration = reactive({
    envatoToken: '',
    hmacKey: '',
    mockMode: settingsStore.settings.envato_mock_mode,
});

const security = reactive({
    rateLimit: String(settingsStore.settings.rate_limit_per_minute),
    maxResets: String(settingsStore.settings.reset_policies.max_resets_allowed),
});

const policies = reactive({
    treatWwwAsSame: settingsStore.settings.domain_policies.treat_www_as_same,
    allowLocalhost: settingsStore.settings.domain_policies.allow_localhost,
    allowIpDomains: settingsStore.settings.domain_policies.allow_ip_domains,
});

const canManage = computed<boolean>(() => authStore.can('settings:manage'));
const isSuperAdmin = computed<boolean>(() => authStore.role === 'super-admin');
const lastCronRun = computed<string>(() => formatDateTime(new Date().toISOString()));

const saveIntegration = async (): Promise<void> => {
    if (!integration.envatoToken && !integration.hmacKey) {
        toastStore.push({
            tone: 'info',
            title: 'Nothing to save',
            message: 'Provide an Envato token or HMAC key, or use Save Mock Mode.',
        });
        return;
    }

    try {
        await settingsStore.updateSettings({
            envato_api_token: integration.envatoToken || undefined,
            license_hmac_key: integration.hmacKey || undefined,
        });

        integration.envatoToken = '';
        integration.hmacKey = '';

        toastStore.push({
            tone: 'success',
            title: 'Integration saved',
            message: 'Integration secrets rotated successfully.',
        });
    } catch {
        toastStore.push({
            tone: 'error',
            title: 'Save failed',
            message: settingsStore.error?.message ?? 'Unable to update integration settings.',
        });
    }
};

const saveMockMode = async (): Promise<void> => {
    try {
        await settingsStore.updateSettings({
            envato_mock_mode: integration.mockMode,
        });

        toastStore.push({
            tone: 'success',
            title: 'Mock mode updated',
            message: 'Envato mock mode setting saved.',
        });
    } catch {
        toastStore.push({
            tone: 'error',
            title: 'Update failed',
            message: settingsStore.error?.message ?? 'Unable to update mock mode setting.',
        });
    }
};

const saveSecurity = async (): Promise<void> => {
    try {
        await settingsStore.updateSettings({
            rate_limit_per_minute: Number(security.rateLimit),
            reset_policies: {
                max_resets_allowed: Number(security.maxResets),
            },
        });

        toastStore.push({
            tone: 'success',
            title: 'Security updated',
            message: 'Security settings saved.',
        });
    } catch {
        toastStore.push({
            tone: 'error',
            title: 'Update failed',
            message: settingsStore.error?.message ?? 'Unable to update security settings.',
        });
    }
};

const savePolicies = async (): Promise<void> => {
    try {
        await settingsStore.updateSettings({
            domain_policies: {
                treat_www_as_same: policies.treatWwwAsSame,
                allow_localhost: policies.allowLocalhost,
                allow_ip_domains: policies.allowIpDomains,
            },
        });

        toastStore.push({
            tone: 'success',
            title: 'Policies updated',
            message: 'License policy settings saved.',
        });
    } catch {
        toastStore.push({
            tone: 'error',
            title: 'Update failed',
            message: settingsStore.error?.message ?? 'Unable to update license policy settings.',
        });
    }
};

const testToken = async (): Promise<void> => {
    testingToken.value = true;
    const passed = await settingsStore.testEnvatoToken();
    testingToken.value = false;

    toastStore.push({
        tone: passed ? 'success' : 'error',
        title: passed ? 'Token valid' : 'Token check failed',
        message: passed ? 'Envato token health check passed.' : settingsStore.error?.message ?? 'Token test failed.',
    });
};

const logoutOtherDevices = async (): Promise<void> => {
    try {
        const count = await authStore.logoutOtherDevices();
        toastStore.push({
            tone: 'success',
            title: 'Other sessions ended',
            message: `${count} token(s) revoked on other devices.`,
        });
    } catch {
        toastStore.push({
            tone: 'error',
            title: 'Action failed',
            message: authStore.error?.message ?? 'Unable to revoke other sessions.',
        });
    }
};

const goToTwoFactorSetup = async (): Promise<void> => {
    await router.push('/admin/settings/2fa/setup');
};

const goToRecoveryCodes = async (): Promise<void> => {
    await router.push('/admin/settings/2fa/recovery-codes');
};

onMounted(async () => {
    await settingsStore.fetchSettings();

    security.rateLimit = String(settingsStore.settings.rate_limit_per_minute);
    security.maxResets = String(settingsStore.settings.reset_policies.max_resets_allowed);
    policies.treatWwwAsSame = settingsStore.settings.domain_policies.treat_www_as_same;
    policies.allowLocalhost = settingsStore.settings.domain_policies.allow_localhost;
    policies.allowIpDomains = settingsStore.settings.domain_policies.allow_ip_domains;
    integration.mockMode = settingsStore.settings.envato_mock_mode;
});
</script>
