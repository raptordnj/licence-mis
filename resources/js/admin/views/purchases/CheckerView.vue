<template>
    <section class="space-y-4">
        <PageHeader
            title="Checker"
            eyebrow="Smart Verification"
            description="Check a purchase code instantly and jump to the exact purchase, license, and logs."
        />

        <UiCard>
            <div class="grid gap-3 lg:grid-cols-[1fr_auto_auto]">
                <UiInput
                    v-model="purchaseCode"
                    label="Purchase Code"
                    placeholder="Paste purchase code and run checker"
                    @keyup.enter="checkPurchase"
                />
                <UiButton class="self-end" :loading="loading" :disabled="purchaseCode.trim() === ''" @click="checkPurchase">
                    Check
                </UiButton>
                <UiButton class="self-end" variant="secondary" :disabled="loading" @click="clearChecker">Clear</UiButton>
            </div>

            <div v-if="recentCodes.length > 0" class="mt-3 flex flex-wrap items-center gap-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Recent</p>
                <button
                    v-for="code in recentCodes"
                    :key="code"
                    type="button"
                    class="rounded-full border border-slate-300 px-2.5 py-1 text-xs text-slate-600 transition hover:border-cyan-400 hover:text-cyan-700 dark:border-slate-700 dark:text-slate-300 dark:hover:border-cyan-400 dark:hover:text-cyan-200"
                    @click="runRecentCheck(code)"
                >
                    {{ code }}
                </button>
            </div>
        </UiCard>

        <ErrorBanner v-if="error !== null" :message="error.message" />

        <UiCard v-if="loading">
            <div class="grid gap-2">
                <UiSkeleton height="1rem" />
                <UiSkeleton height="1rem" />
                <UiSkeleton height="1rem" />
            </div>
        </UiCard>

        <template v-else-if="purchase !== null">
            <div class="grid gap-4 xl:grid-cols-4">
                <UiCard>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Purchase Status</p>
                    <div class="mt-2">
                        <StatusBadge :value="purchase.status" />
                    </div>
                </UiCard>
                <UiCard>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Buyer</p>
                    <p class="mt-2 text-base font-semibold text-slate-900 dark:text-slate-100">{{ purchase.buyer }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ purchase.buyer_email ?? 'No buyer email' }}</p>
                </UiCard>
                <UiCard>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Bound Domain</p>
                    <p class="mt-2 text-base font-semibold text-slate-900 dark:text-slate-100">{{ license?.bound_domain ?? 'Unbound' }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Last check: {{ formatDateTime(license?.last_check_at ?? null) }}
                    </p>
                </UiCard>
                <UiCard>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Support</p>
                    <p class="mt-2 text-base font-semibold text-slate-900 dark:text-slate-100">
                        {{ supportRelativeLabel }}
                    </p>
                    <div class="mt-1">
                        <StatusBadge :value="supportBadgeValue" />
                    </div>
                </UiCard>
            </div>

            <UiCard>
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Sale Snapshot</h2>
                    <UiButton variant="secondary" @click="copyPurchaseCode">Copy Purchase Code</UiButton>
                </div>
                <DataTable
                    :columns="saleColumns"
                    :rows="saleRows"
                    empty-title="No sale data"
                    empty-description="No matching purchase was found."
                >
                    <template #cell-activated_at="{ value }">
                        {{ formatDateTime(String(value)) }}
                    </template>
                    <template #cell-supported="{ value }">
                        <StatusBadge :value="String(value)" />
                    </template>
                </DataTable>
            </UiCard>

            <div class="grid gap-4 lg:grid-cols-2">
                <UiCard>
                    <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Purchase Details</h2>
                    <dl class="mt-3 grid gap-2 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Item</dt>
                            <dd class="text-sm">{{ purchase.item_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Envato Item ID</dt>
                            <dd class="text-sm">{{ purchase.envato_item_id }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Purchase Date</dt>
                            <dd class="text-sm">{{ formatDate(purchase.purchase_date) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Created</dt>
                            <dd class="text-sm">{{ formatDateTime(purchase.created_at) }}</dd>
                        </div>
                    </dl>
                </UiCard>

                <UiCard>
                    <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Smart Actions</h2>
                    <div class="mt-3 grid gap-2">
                        <UiButton variant="secondary" @click="openPurchaseDetail">Open Purchase Detail</UiButton>
                        <UiButton variant="secondary" :disabled="license === null" @click="openLicenseDetail">
                            Open License Detail
                        </UiButton>
                        <UiButton variant="secondary" @click="openValidationLogs">Open Validation Logs</UiButton>
                    </div>
                </UiCard>
            </div>
        </template>

        <UiCard v-else-if="hasSearched">
            <EmptyState
                title="No purchase found"
                description="No purchase matched this code. Check spacing/typos or try a full exact purchase code."
            />
        </UiCard>
    </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { z } from 'zod';

import DataTable, { type DataTableColumn } from '@/admin/components/data/DataTable.vue';
import EmptyState from '@/admin/components/feedback/EmptyState.vue';
import ErrorBanner from '@/admin/components/feedback/ErrorBanner.vue';
import StatusBadge from '@/admin/components/feedback/StatusBadge.vue';
import PageHeader from '@/admin/components/layout/PageHeader.vue';
import UiButton from '@/admin/components/ui/UiButton.vue';
import UiCard from '@/admin/components/ui/UiCard.vue';
import UiInput from '@/admin/components/ui/UiInput.vue';
import UiSkeleton from '@/admin/components/ui/UiSkeleton.vue';
import { paginatedSchema, purchaseSchema } from '@/admin/schemas/api';
import { extractApiError, requestData } from '@/admin/services/http';
import { useToastStore } from '@/admin/stores/toast';
import type { ApiError, Purchase } from '@/admin/types/api';
import { formatDate, formatDateTime } from '@/admin/utils/format';

interface CheckerLicense {
    id: number;
    purchase_code: string;
    status: string;
    license_type: 'regular' | 'extended' | string;
    buyer_username: string | null;
    version: string | null;
    bound_domain: string | null;
    envato_item_id: number | null;
    last_check_at: string | null;
    activated_at: string | null;
}

const checkerLicenseSchema = z.object({
    id: z.number(),
    purchase_code: z.string(),
    status: z.string(),
    license_type: z.string().default('regular'),
    buyer_username: z.string().nullable().default(null),
    version: z.string().nullable().default(null),
    bound_domain: z.string().nullable(),
    envato_item_id: z.number().nullable(),
    last_check_at: z.string().nullable().default(null),
    activated_at: z.string().nullable().default(null),
});

const checkerLicenseListSchema = paginatedSchema(checkerLicenseSchema);
const purchaseListSchema = paginatedSchema(purchaseSchema);

const RECENT_CODES_STORAGE_KEY = 'admin-checker-recent-codes';
const RECENT_CODES_LIMIT = 8;

const route = useRoute();
const router = useRouter();
const toastStore = useToastStore();

const purchaseCode = ref('');
const loading = ref(false);
const hasSearched = ref(false);
const error = ref<ApiError | null>(null);
const purchase = ref<Purchase | null>(null);
const license = ref<CheckerLicense | null>(null);
const recentCodes = ref<string[]>(loadRecentCodes());

const saleColumns: DataTableColumn[] = [
    { key: 'license_type', label: 'License Type' },
    { key: 'item', label: 'Item' },
    { key: 'buyer_username', label: 'Buyer Username' },
    { key: 'version', label: 'Version' },
    { key: 'activated_at', label: 'Activated Time' },
    { key: 'amount', label: 'Amount' },
    { key: 'support_amount', label: 'Support Amount' },
    { key: 'purchase_count', label: 'Purchase Count' },
    { key: 'sold', label: 'Sold' },
    { key: 'supported_until', label: 'Supported Until' },
    { key: 'supported', label: 'Supported' },
];

const supportBadgeValue = computed<string>(() => {
    if (purchase.value?.supported_until === null || purchase.value?.supported_until === undefined) {
        return 'unknown';
    }

    const supportedUntil = new Date(purchase.value.supported_until).getTime();

    if (Number.isNaN(supportedUntil)) {
        return 'unknown';
    }

    return supportedUntil >= Date.now() ? 'supported' : 'expired';
});

const supportRelativeLabel = computed<string>(() => toRelativeLabel(purchase.value?.supported_until ?? null));

const saleRows = computed<Record<string, string>[]>(() => {
    if (purchase.value === null) {
        return [];
    }

    return [
        {
            id: 'sale',
            license_type: normalizeLicenseTypeLabel(license.value?.license_type ?? purchase.value.license_type),
            item: purchase.value.item_name,
            buyer_username: license.value?.buyer_username ?? purchase.value.buyer_username ?? purchase.value.buyer,
            version: license.value?.version ?? purchase.value.version ?? 'N/A',
            activated_at: license.value?.activated_at ?? purchase.value.activated_at ?? purchase.value.created_at,
            amount: 'N/A',
            support_amount: 'N/A',
            purchase_count: '1',
            sold: toRelativeLabel(purchase.value.purchase_date),
            supported_until: supportRelativeLabel.value,
            supported: supportBadgeValue.value,
        },
    ];
});

const runRecentCheck = async (code: string): Promise<void> => {
    purchaseCode.value = code;
    await checkPurchase();
};

const clearChecker = async (): Promise<void> => {
    purchaseCode.value = '';
    loading.value = false;
    hasSearched.value = false;
    error.value = null;
    purchase.value = null;
    license.value = null;

    const nextQuery = {
        ...route.query,
        purchase_code: undefined,
        purchaseCode: undefined,
    };

    await router.replace({ query: nextQuery });
};

const checkPurchase = async (): Promise<void> => {
    const normalizedCode = purchaseCode.value.trim();

    if (normalizedCode === '') {
        error.value = {
            code: 'VALIDATION_ERROR',
            message: 'Purchase code is required.',
        };
        return;
    }

    loading.value = true;
    hasSearched.value = true;
    error.value = null;
    purchase.value = null;
    license.value = null;

    try {
        const purchaseResponse = await requestData(
            {
                method: 'GET',
                url: '/admin/purchases',
                params: {
                    page: 1,
                    per_page: 15,
                    search: normalizedCode,
                },
            },
            purchaseListSchema,
        );

        const matchedPurchase =
            purchaseResponse.data.find((entry) => entry.purchase_code.toLowerCase() === normalizedCode.toLowerCase()) ??
            purchaseResponse.data[0] ??
            null;

        if (matchedPurchase === null) {
            await updateQueryCode(normalizedCode);
            return;
        }

        purchase.value = matchedPurchase;

        const licenseResponse = await requestData(
            {
                method: 'GET',
                url: '/admin/licenses',
                params: {
                    page: 1,
                    per_page: 15,
                    search: matchedPurchase.purchase_code,
                },
            },
            checkerLicenseListSchema,
        );

        license.value =
            licenseResponse.data.find((entry) => entry.purchase_code.toLowerCase() === matchedPurchase.purchase_code.toLowerCase()) ??
            licenseResponse.data[0] ??
            null;

        updateRecentCodes(matchedPurchase.purchase_code);
        await updateQueryCode(matchedPurchase.purchase_code);
    } catch (requestError: unknown) {
        error.value = extractApiError(requestError);
    } finally {
        loading.value = false;
    }
};

const openPurchaseDetail = async (): Promise<void> => {
    if (purchase.value === null) {
        return;
    }

    await router.push(`/admin/purchases/${purchase.value.id}`);
};

const openLicenseDetail = async (): Promise<void> => {
    if (license.value === null) {
        return;
    }

    await router.push(`/admin/licenses/${license.value.id}`);
};

const openValidationLogs = async (): Promise<void> => {
    if (purchase.value === null) {
        return;
    }

    await router.push(`/admin/validation-logs?purchaseCode=${purchase.value.purchase_code}`);
};

const copyPurchaseCode = async (): Promise<void> => {
    if (purchase.value === null) {
        return;
    }

    try {
        await navigator.clipboard.writeText(purchase.value.purchase_code);
        toastStore.push({
            tone: 'success',
            title: 'Copied',
            message: 'Purchase code copied to clipboard.',
        });
    } catch {
        toastStore.push({
            tone: 'error',
            title: 'Copy failed',
            message: 'Could not copy purchase code.',
        });
    }
};

onMounted(async () => {
    const queryCode = resolveInitialQueryCode();

    if (queryCode === '') {
        return;
    }

    purchaseCode.value = queryCode;
    await checkPurchase();
});

async function updateQueryCode(code: string): Promise<void> {
    const nextQuery = {
        ...route.query,
        purchase_code: code,
        purchaseCode: undefined,
    };

    await router.replace({ query: nextQuery });
}

function resolveInitialQueryCode(): string {
    if (typeof route.query.purchase_code === 'string') {
        return route.query.purchase_code;
    }

    if (typeof route.query.purchaseCode === 'string') {
        return route.query.purchaseCode;
    }

    return '';
}

function toRelativeLabel(value: string | null): string {
    if (value === null || value === '') {
        return 'N/A';
    }

    const targetDate = new Date(value).getTime();

    if (Number.isNaN(targetDate)) {
        return 'N/A';
    }

    const diffDays = Math.round((targetDate - Date.now()) / (24 * 60 * 60 * 1000));
    const absoluteDays = Math.abs(diffDays);

    if (absoluteDays === 0) {
        return 'today';
    }

    if (diffDays > 0) {
        return `in ${absoluteDays} day${absoluteDays === 1 ? '' : 's'}`;
    }

    return `${absoluteDays} day${absoluteDays === 1 ? '' : 's'} ago`;
}

function normalizeLicenseTypeLabel(value: string): string {
    return value.toLowerCase().includes('extended') ? 'Extended' : 'Regular';
}

function loadRecentCodes(): string[] {
    if (typeof window === 'undefined') {
        return [];
    }

    try {
        const raw = window.localStorage.getItem(RECENT_CODES_STORAGE_KEY);

        if (raw === null) {
            return [];
        }

        const decoded = JSON.parse(raw);

        if (!Array.isArray(decoded)) {
            return [];
        }

        return decoded
            .filter((entry): entry is string => typeof entry === 'string' && entry.trim() !== '')
            .slice(0, RECENT_CODES_LIMIT);
    } catch {
        return [];
    }
}

function updateRecentCodes(code: string): void {
    const normalized = code.trim();

    if (normalized === '') {
        return;
    }

    recentCodes.value = [normalized, ...recentCodes.value.filter((entry) => entry !== normalized)].slice(0, RECENT_CODES_LIMIT);

    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(RECENT_CODES_STORAGE_KEY, JSON.stringify(recentCodes.value));
}
</script>
