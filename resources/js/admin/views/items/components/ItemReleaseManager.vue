<template>
    <section class="space-y-4">
        <ErrorBanner v-if="error !== null" :message="error.message" />

        <UiCard class="space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p class="type-label">Release Management</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Manage update packages and semantic versions for item #{{ item.envato_item_id }}.
                    </p>
                </div>
                <UiBadge tone="info">Total {{ sortedReleases.length }}</UiBadge>
            </div>

            <div v-if="canManage" class="grid gap-3 md:grid-cols-3">
                <UiButton size="compact" variant="ghost" :disabled="saving" @click="setVersionPreset('patch')">
                    Next Patch: {{ nextPatchVersion }}
                </UiButton>
                <UiButton size="compact" variant="ghost" :disabled="saving" @click="setVersionPreset('minor')">
                    Next Minor: {{ nextMinorVersion }}
                </UiButton>
                <UiButton size="compact" variant="ghost" :disabled="saving" @click="setVersionPreset('major')">
                    Next Major: {{ nextMajorVersion }}
                </UiButton>
            </div>

            <div v-if="canManage" class="grid gap-3 md:grid-cols-2">
                <UiSelect v-model="form.channel" label="Channel" :options="channelOptions" :disabled="saving" />
                <UiInput v-model="form.version" label="Version" placeholder="1.2.3" :disabled="saving" required />
                <UiInput v-model="form.min_version" label="Min Version" placeholder="1.0.0" :disabled="saving" />
                <UiInput v-model="form.max_version" label="Max Version" placeholder="2.0.0" :disabled="saving" />
                <UiInput
                    v-model="form.published_at"
                    type="datetime-local"
                    label="Published At"
                    :disabled="saving || !form.is_published"
                />
                <label class="grid gap-1.5">
                    <span class="type-label">Publish Status</span>
                    <select
                        v-model="form.is_published"
                        :disabled="saving"
                        class="w-full rounded-xl border border-white/30 bg-white/50 px-3 py-2 text-sm text-slate-800 shadow-sm backdrop-blur transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400/70 focus-visible:shadow-violet-500/10 focus-visible:shadow-md disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700/50 dark:bg-slate-900/50 dark:text-slate-100"
                    >
                        <option :value="true">Published</option>
                        <option :value="false">Draft</option>
                    </select>
                </label>
                <label class="grid gap-1.5 md:col-span-2">
                    <span class="type-label">
                        Package ZIP
                        {{ editingReleaseId === null ? '(Required)' : '(Optional for update)' }}
                    </span>
                    <input
                        type="file"
                        accept=".zip,application/zip,application/x-zip-compressed"
                        :disabled="saving"
                        class="w-full rounded-xl border border-white/30 bg-white/50 px-3 py-2 text-sm text-slate-800 shadow-sm backdrop-blur transition file:mr-3 file:rounded-lg file:border-0 file:bg-violet-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-violet-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400/70 focus-visible:shadow-violet-500/10 focus-visible:shadow-md disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700/50 dark:bg-slate-900/50 dark:text-slate-100"
                        @change="onPackageChange"
                    />
                </label>
                <label class="grid gap-1.5 md:col-span-2">
                    <span class="type-label">Release Notes</span>
                    <textarea
                        v-model="form.release_notes"
                        rows="4"
                        :disabled="saving"
                        class="w-full rounded-xl border border-white/30 bg-white/50 px-3 py-2 text-sm text-slate-800 shadow-sm backdrop-blur transition placeholder:text-slate-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400/70 focus-visible:shadow-violet-500/10 focus-visible:shadow-md disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700/50 dark:bg-slate-900/50 dark:text-slate-100 dark:placeholder:text-slate-500"
                        placeholder="What changed in this release?"
                    ></textarea>
                </label>
            </div>

            <div v-if="canManage" class="flex flex-wrap items-center gap-2">
                <UiButton :loading="saving" :disabled="saving" @click="submitRelease">
                    {{ editingReleaseId === null ? 'Create Release' : 'Update Release' }}
                </UiButton>
                <UiButton variant="secondary" :disabled="saving" @click="cancelEdit">Cancel</UiButton>
            </div>
        </UiCard>

        <DataTable
            :columns="columns"
            :rows="rows"
            :loading="loading"
            empty-title="No releases yet"
            empty-description="Create the first release package for this item."
        >
            <template #cell-status="{ row }">
                <UiBadge :tone="Boolean(row.is_published) ? 'success' : 'neutral'">
                    {{ Boolean(row.is_published) ? 'Published' : 'Draft' }}
                </UiBadge>
            </template>
            <template #cell-size="{ value }">
                {{ formatBytes(Number(value)) }}
            </template>
            <template #cell-published_at="{ value }">
                {{ formatDateTime(String(value ?? '')) }}
            </template>
            <template #cell-updated_at="{ value }">
                {{ formatDateTime(String(value ?? '')) }}
            </template>
            <template #cell-actions="{ row }">
                <div v-if="canManage" class="flex justify-end gap-2">
                    <UiButton size="compact" variant="secondary" :disabled="saving" @click.stop="editRelease(row)">
                        Edit
                    </UiButton>
                    <UiButton
                        size="compact"
                        variant="ghost"
                        :loading="publishingId === Number(row.id)"
                        :disabled="saving"
                        @click.stop="togglePublish(row)"
                    >
                        {{ Boolean(row.is_published) ? 'Unpublish' : 'Publish' }}
                    </UiButton>
                    <UiButton
                        size="compact"
                        variant="danger"
                        :loading="deletingId === Number(row.id)"
                        :disabled="saving"
                        @click.stop="deleteRelease(row)"
                    >
                        Delete
                    </UiButton>
                </div>
                <span v-else class="text-xs text-slate-400">Read only</span>
            </template>
        </DataTable>
    </section>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { z } from 'zod';

import DataTable, { type DataTableColumn } from '@/admin/components/data/DataTable.vue';
import ErrorBanner from '@/admin/components/feedback/ErrorBanner.vue';
import UiBadge from '@/admin/components/ui/UiBadge.vue';
import UiButton from '@/admin/components/ui/UiButton.vue';
import UiCard from '@/admin/components/ui/UiCard.vue';
import UiInput from '@/admin/components/ui/UiInput.vue';
import UiSelect from '@/admin/components/ui/UiSelect.vue';
import { paginatedSchema } from '@/admin/schemas/api';
import { extractApiError, requestData } from '@/admin/services/http';
import { useToastStore } from '@/admin/stores/toast';
import { formatDateTime } from '@/admin/utils/format';
import type { ApiError, EnvatoItem } from '@/admin/types/api';

interface Props {
    item: EnvatoItem;
    canManage: boolean;
}

const props = defineProps<Props>();
const toastStore = useToastStore();

const releaseSchema = z.object({
    id: z.number(),
    product_id: z.number().nullable().default(null),
    envato_item_id: z.number().nullable().default(null),
    channel: z.string(),
    version: z.string(),
    min_version: z.string().nullable().default(null),
    max_version: z.string().nullable().default(null),
    release_notes: z.string().nullable().default(null),
    checksum: z.string(),
    size_bytes: z.number().default(0),
    is_published: z.boolean(),
    published_at: z.string().nullable().default(null),
    download_url: z.string().default(''),
    created_at: z.string().nullable().default(null),
    updated_at: z.string().nullable().default(null),
});

const deleteResponseSchema = z.object({
    deleted: z.boolean(),
    id: z.number(),
});

type UpdateRelease = z.infer<typeof releaseSchema>;

const columns: DataTableColumn[] = [
    { key: 'version', label: 'Version' },
    { key: 'channel', label: 'Channel' },
    { key: 'status', label: 'Status' },
    { key: 'size', label: 'Size' },
    { key: 'published_at', label: 'Published At' },
    { key: 'updated_at', label: 'Updated' },
    { key: 'actions', label: 'Actions', className: 'text-right' },
];

const channelOptions = [
    { label: 'Stable', value: 'stable' },
    { label: 'Beta', value: 'beta' },
    { label: 'Alpha', value: 'alpha' },
];

const loading = ref(false);
const saving = ref(false);
const deletingId = ref<number | null>(null);
const publishingId = ref<number | null>(null);
const error = ref<ApiError | null>(null);
const editingReleaseId = ref<number | null>(null);
const packageFile = ref<File | null>(null);
const releases = ref<UpdateRelease[]>([]);

const form = reactive({
    channel: 'stable',
    version: '',
    min_version: '',
    max_version: '',
    release_notes: '',
    published_at: '',
    is_published: true,
});

const sortedReleases = computed<UpdateRelease[]>(() =>
    [...releases.value].sort((left, right) => {
        if (left.is_published !== right.is_published) {
            return left.is_published ? -1 : 1;
        }

        const semanticComparison = compareVersions(right.version, left.version);
        if (semanticComparison !== 0) {
            return semanticComparison;
        }

        const leftUpdated = Date.parse(left.updated_at ?? left.created_at ?? '') || 0;
        const rightUpdated = Date.parse(right.updated_at ?? right.created_at ?? '') || 0;
        return rightUpdated - leftUpdated;
    }),
);

const rows = computed<Record<string, unknown>[]>(() =>
    sortedReleases.value.map((release) => ({
        ...release,
        status: release.is_published ? 'Published' : 'Draft',
        size: release.size_bytes,
        actions: '',
    })),
);

const baseVersion = computed(() => sortedReleases.value[0]?.version ?? '1.0.0');
const nextPatchVersion = computed(() => bumpVersion(baseVersion.value, 'patch'));
const nextMinorVersion = computed(() => bumpVersion(baseVersion.value, 'minor'));
const nextMajorVersion = computed(() => bumpVersion(baseVersion.value, 'major'));

function normalizeVersion(value: string): [number, number, number] {
    const matches = value.trim().replace(/^v/i, '').match(/^(\d+)\.(\d+)\.(\d+)/);

    return [
        Number(matches?.[1] ?? 0),
        Number(matches?.[2] ?? 0),
        Number(matches?.[3] ?? 0),
    ];
}

function compareVersions(left: string, right: string): number {
    const [leftMajor, leftMinor, leftPatch] = normalizeVersion(left);
    const [rightMajor, rightMinor, rightPatch] = normalizeVersion(right);

    if (leftMajor !== rightMajor) {
        return leftMajor - rightMajor;
    }

    if (leftMinor !== rightMinor) {
        return leftMinor - rightMinor;
    }

    if (leftPatch !== rightPatch) {
        return leftPatch - rightPatch;
    }

    return left.localeCompare(right, undefined, { numeric: true, sensitivity: 'base' });
}

function bumpVersion(version: string, mode: 'patch' | 'minor' | 'major'): string {
    const [major, minor, patch] = normalizeVersion(version);

    if (mode === 'major') {
        return `${major + 1}.0.0`;
    }

    if (mode === 'minor') {
        return `${major}.${minor + 1}.0`;
    }

    return `${major}.${minor}.${patch + 1}`;
}

function toDateTimeLocal(value: string | null): string {
    if (value === null || value === '') {
        return '';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const timezoneOffsetMs = date.getTimezoneOffset() * 60_000;
    const localDate = new Date(date.getTime() - timezoneOffsetMs);
    return localDate.toISOString().slice(0, 16);
}

function formatBytes(value: number): string {
    if (!Number.isFinite(value) || value <= 0) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let unitIndex = 0;
    let size = value;

    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex += 1;
    }

    return `${size.toFixed(size >= 10 || unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
}

function resetForm(): void {
    form.channel = 'stable';
    form.version = '';
    form.min_version = '';
    form.max_version = '';
    form.release_notes = '';
    form.published_at = '';
    form.is_published = true;
    packageFile.value = null;
}

function setVersionPreset(mode: 'patch' | 'minor' | 'major'): void {
    if (mode === 'major') {
        form.version = nextMajorVersion.value;
        return;
    }

    if (mode === 'minor') {
        form.version = nextMinorVersion.value;
        return;
    }

    form.version = nextPatchVersion.value;
}

function onPackageChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    packageFile.value = input.files?.[0] ?? null;
}

async function loadReleases(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        const payload = await requestData(
            {
                method: 'GET',
                url: '/admin/update-releases',
                params: {
                    envato_item_id: props.item.envato_item_id,
                    per_page: 100,
                },
            },
            paginatedSchema(releaseSchema),
        );

        releases.value = payload.data;
    } catch (requestError: unknown) {
        error.value = extractApiError(requestError);
        releases.value = [];
    } finally {
        loading.value = false;
    }
}

function ensureValidRange(): boolean {
    if (form.min_version.trim() === '' || form.max_version.trim() === '') {
        return true;
    }

    const valid = compareVersions(form.min_version, form.max_version) <= 0;
    if (!valid) {
        toastStore.push({
            tone: 'error',
            title: 'Validation failed',
            message: 'Min version cannot be greater than max version.',
        });
    }

    return valid;
}

async function submitRelease(): Promise<void> {
    if (!props.canManage) {
        return;
    }

    if (form.version.trim() === '') {
        toastStore.push({
            tone: 'error',
            title: 'Version required',
            message: 'Please provide a semantic version like 1.2.3.',
        });
        return;
    }

    if (!ensureValidRange()) {
        return;
    }

    if (editingReleaseId.value === null && packageFile.value === null) {
        toastStore.push({
            tone: 'error',
            title: 'Package required',
            message: 'A ZIP package file is required when creating a release.',
        });
        return;
    }

    saving.value = true;
    error.value = null;

    try {
        const formData = new FormData();
        formData.append('envato_item_id', String(props.item.envato_item_id));
        formData.append('channel', form.channel.trim().toLowerCase());
        formData.append('version', form.version.trim());
        formData.append('is_published', form.is_published ? '1' : '0');

        if (form.min_version.trim() !== '') {
            formData.append('min_version', form.min_version.trim());
        }

        if (form.max_version.trim() !== '') {
            formData.append('max_version', form.max_version.trim());
        }

        if (form.release_notes.trim() !== '') {
            formData.append('release_notes', form.release_notes.trim());
        }

        if (form.published_at !== '') {
            formData.append('published_at', form.published_at);
        }

        if (packageFile.value !== null) {
            formData.append('package', packageFile.value);
        }

        if (editingReleaseId.value !== null) {
            formData.append('_method', 'PUT');
        }

        await requestData(
            {
                method: 'POST',
                url:
                    editingReleaseId.value === null
                        ? '/admin/update-releases'
                        : `/admin/update-releases/${editingReleaseId.value}`,
                data: formData,
            },
            releaseSchema,
        );

        toastStore.push({
            tone: 'success',
            title: editingReleaseId.value === null ? 'Release created' : 'Release updated',
            message:
                editingReleaseId.value === null
                    ? 'Update release package has been created.'
                    : 'Update release package has been updated.',
        });

        editingReleaseId.value = null;
        resetForm();
        await loadReleases();
    } catch (requestError: unknown) {
        error.value = extractApiError(requestError);
        toastStore.push({
            tone: 'error',
            title: 'Save failed',
            message: error.value.message,
        });
    } finally {
        saving.value = false;
    }
}

function editRelease(row: Record<string, unknown>): void {
    const selected = sortedReleases.value.find((entry) => entry.id === Number(row.id));
    if (selected === undefined) {
        return;
    }

    editingReleaseId.value = selected.id;
    form.channel = selected.channel;
    form.version = selected.version;
    form.min_version = selected.min_version ?? '';
    form.max_version = selected.max_version ?? '';
    form.release_notes = selected.release_notes ?? '';
    form.published_at = toDateTimeLocal(selected.published_at);
    form.is_published = selected.is_published;
    packageFile.value = null;
}

function cancelEdit(): void {
    editingReleaseId.value = null;
    resetForm();
}

async function togglePublish(row: Record<string, unknown>): Promise<void> {
    if (!props.canManage) {
        return;
    }

    const releaseId = Number(row.id);
    const release = sortedReleases.value.find((entry) => entry.id === releaseId);
    if (release === undefined) {
        return;
    }

    publishingId.value = releaseId;
    error.value = null;

    try {
        const formData = new FormData();
        formData.append('_method', 'PUT');
        formData.append('is_published', release.is_published ? '0' : '1');

        if (!release.is_published) {
            formData.append('published_at', new Date().toISOString());
        }

        await requestData(
            {
                method: 'POST',
                url: `/admin/update-releases/${releaseId}`,
                data: formData,
            },
            releaseSchema,
        );

        toastStore.push({
            tone: 'success',
            title: release.is_published ? 'Release unpublished' : 'Release published',
            message: release.is_published
                ? 'Release is now hidden from manifest checks.'
                : 'Release is now available for manifest checks.',
        });

        await loadReleases();
    } catch (requestError: unknown) {
        error.value = extractApiError(requestError);
        toastStore.push({
            tone: 'error',
            title: 'Update failed',
            message: error.value.message,
        });
    } finally {
        publishingId.value = null;
    }
}

async function deleteRelease(row: Record<string, unknown>): Promise<void> {
    if (!props.canManage) {
        return;
    }

    const releaseId = Number(row.id);
    const release = sortedReleases.value.find((entry) => entry.id === releaseId);
    if (release === undefined) {
        return;
    }

    const confirmed = window.confirm(
        `Delete release ${release.version} (${release.channel})? This cannot be undone.`,
    );
    if (!confirmed) {
        return;
    }

    deletingId.value = releaseId;
    error.value = null;

    try {
        await requestData(
            {
                method: 'DELETE',
                url: `/admin/update-releases/${releaseId}`,
            },
            deleteResponseSchema,
        );

        toastStore.push({
            tone: 'success',
            title: 'Release deleted',
            message: `Release ${release.version} has been removed.`,
        });

        if (editingReleaseId.value === releaseId) {
            editingReleaseId.value = null;
            resetForm();
        }

        await loadReleases();
    } catch (requestError: unknown) {
        error.value = extractApiError(requestError);
        toastStore.push({
            tone: 'error',
            title: 'Delete failed',
            message: error.value.message,
        });
    } finally {
        deletingId.value = null;
    }
}

watch(
    () => props.item.envato_item_id,
    () => {
        editingReleaseId.value = null;
        resetForm();
        void loadReleases();
    },
    { immediate: true },
);
</script>
