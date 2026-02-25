import { defineStore } from 'pinia';
import { z } from 'zod';

import { licenseDetailSchema, licenseSchema, paginatedSchema } from '@/admin/schemas/api';
import { demoLicenses, paginate } from '@/admin/services/demoData';
import { extractApiError, requestData } from '@/admin/services/http';
import type { ApiError, DataListQuery, License, LicenseDetail, PaginatedResponse } from '@/admin/types/api';

interface LicenseQuery extends DataListQuery {
    status: string;
    boundState: string;
    marketplace: string;
    resetMin: number;
    resetMax: number;
    lastCheckFrom: string;
    lastCheckTo: string;
}

const defaultQuery = (): LicenseQuery => ({
    page: 1,
    perPage: 15,
    search: '',
    sortBy: 'id',
    sortDir: 'desc',
    status: '',
    boundState: '',
    marketplace: '',
    resetMin: 0,
    resetMax: 20,
    lastCheckFrom: '',
    lastCheckTo: '',
});

const backendLicenseSchema = z.object({
    id: z.number(),
    item_name: z.string().optional().default('Unassigned Item'),
    purchase_code: z.string(),
    marketplace: z.string().default('envato'),
    envato_item_id: z.number().nullable(),
    status: z.string(),
    license_type: z.string().optional().default('regular'),
    buyer_username: z.string().nullable().optional().default(null),
    version: z.string().nullable().optional().default(null),
    bound_domain: z.string().nullable(),
    bound_domain_original: z.string().nullable().optional().default(null),
    bound_at: z.string().nullable().optional().default(null),
    activated_at: z.string().nullable().optional().default(null),
    supported_until: z.string().nullable().optional().default(null),
    verified_at: z.string().nullable().optional().default(null),
    last_check_at: z.string().nullable().optional().default(null),
    reset_count: z.number().optional().default(0),
    created_at: z.string().optional().default(new Date().toISOString()),
    updated_at: z.string().optional().default(new Date().toISOString()),
});

const backendListSchema = paginatedSchema(backendLicenseSchema);

const mapBackendLicense = (entry: z.infer<typeof backendLicenseSchema>): License =>
    licenseSchema.parse({
        id: entry.id,
        item_name: entry.item_name || (entry.envato_item_id !== null ? `Item ${entry.envato_item_id}` : 'Unassigned Item'),
        envato_item_id: entry.envato_item_id,
        purchase_code: entry.purchase_code,
        status: entry.status,
        license_type: entry.license_type,
        buyer_username: entry.buyer_username,
        version: entry.version,
        bound_domain: entry.bound_domain,
        bound_domain_original: entry.bound_domain_original ?? entry.bound_domain,
        bound_at: entry.bound_at ?? entry.verified_at,
        activated_at: entry.activated_at ?? entry.verified_at,
        last_check_at: entry.last_check_at ?? entry.verified_at,
        reset_count: entry.reset_count,
        created_at: entry.created_at,
        updated_at: entry.updated_at,
    });

const filterDemo = (source: License[], query: LicenseQuery): License[] =>
    source
        .filter((entry) => {
            if (query.search !== '') {
                const value = query.search.toLowerCase();
                const matches =
                    entry.purchase_code.toLowerCase().includes(value) ||
                    String(entry.id).includes(value) ||
                    (entry.bound_domain ?? '').toLowerCase().includes(value) ||
                    entry.item_name.toLowerCase().includes(value) ||
                    (entry.buyer_username ?? '').toLowerCase().includes(value) ||
                    (entry.version ?? '').toLowerCase().includes(value);

                if (!matches) {
                    return false;
                }
            }

            if (query.status !== '' && entry.status !== query.status) {
                return false;
            }

            if (query.boundState === 'bound' && entry.bound_domain === null) {
                return false;
            }

            if (query.boundState === 'unbound' && entry.bound_domain !== null) {
                return false;
            }

            if (query.marketplace !== '' && query.marketplace !== 'envato') {
                return false;
            }

            if (entry.reset_count < query.resetMin || entry.reset_count > query.resetMax) {
                return false;
            }

            return true;
        })
        .sort((left, right) => {
            const leftValue = left[query.sortBy as keyof License];
            const rightValue = right[query.sortBy as keyof License];

            const direction = query.sortDir === 'asc' ? 1 : -1;

            if (typeof leftValue === 'number' && typeof rightValue === 'number') {
                return (leftValue - rightValue) * direction;
            }

            return String(leftValue).localeCompare(String(rightValue)) * direction;
        });

export const useLicensesStore = defineStore('licensesStore', {
    state: () => ({
        query: defaultQuery() as LicenseQuery,
        response: paginate(demoLicenses, 1, 15, '/api/v1/admin/licenses') as PaginatedResponse<License>,
        loading: false,
        error: null as ApiError | null,
        detail: null as LicenseDetail | null,
        detailLoading: false,
        detailError: null as ApiError | null,
        selectedIds: [] as number[],
        cache: new Map<string, PaginatedResponse<License>>(),
        abortController: null as AbortController | null,
        actionLoading: false,
    }),
    getters: {
        rows: (state): License[] => state.response.data,
        total: (state): number => state.response.total,
        hasSelection: (state): boolean => state.selectedIds.length > 0,
    },
    actions: {
        toggleSelection(id: number): void {
            if (this.selectedIds.includes(id)) {
                this.selectedIds = this.selectedIds.filter((value) => value !== id);
                return;
            }

            this.selectedIds.push(id);
        },

        clearSelection(): void {
            this.selectedIds = [];
        },

        setQuery(partial: Partial<LicenseQuery>): void {
            this.query = {
                ...this.query,
                ...partial,
            };
        },

        async fetchLicenseDetail(id: number): Promise<void> {
            this.detailLoading = true;
            this.detailError = null;

            try {
                const payload = await requestData(
                    {
                        method: 'GET',
                        url: `/admin/licenses/${id}`,
                    },
                    licenseDetailSchema,
                );

                this.detail = payload;
            } catch (error: unknown) {
                this.detailError = extractApiError(error);
                this.detail = null;
            } finally {
                this.detailLoading = false;
            }
        },

        async fetchLicenses(partial: Partial<LicenseQuery> = {}): Promise<void> {
            this.setQuery(partial);
            this.loading = true;
            this.error = null;

            const cacheKey = JSON.stringify(this.query);
            const cached = this.cache.get(cacheKey);

            if (cached !== undefined) {
                this.response = cached;
                this.loading = false;
                return;
            }

            if (this.abortController !== null) {
                this.abortController.abort();
            }

            const controller = new AbortController();
            this.abortController = controller;

            try {
                const payload = await requestData(
                    {
                        method: 'GET',
                        url: '/admin/licenses',
                        signal: controller.signal,
                        params: {
                            page: this.query.page,
                            per_page: this.query.perPage,
                            search: this.query.search || undefined,
                            status: this.query.status || undefined,
                            item_id: undefined,
                        },
                    },
                    backendListSchema,
                );

                const mapped: PaginatedResponse<License> = {
                    ...payload,
                    data: payload.data.map(mapBackendLicense),
                };

                this.response = mapped;
                this.cache.set(cacheKey, mapped);
            } catch (error: unknown) {
                this.error = extractApiError(error);

                const filtered = filterDemo(demoLicenses, this.query);
                this.response = paginate(filtered, this.query.page, this.query.perPage, '/api/v1/admin/licenses');
            } finally {
                this.loading = false;
            }
        },

        async revokeLicense(id: number, reason: string): Promise<void> {
            this.actionLoading = true;
            this.error = null;

            try {
                await requestData(
                    {
                        method: 'POST',
                        url: `/admin/licenses/${id}/revoke`,
                        data: { reason },
                    },
                    backendLicenseSchema,
                );
            } catch (error: unknown) {
                this.error = extractApiError(error);
            } finally {
                this.cache.clear();
                await this.fetchLicenses();
                if (this.detail?.id === id) {
                    await this.fetchLicenseDetail(id);
                }
                this.actionLoading = false;
            }
        },

        async resetDomain(id: number, reason: string): Promise<void> {
            this.actionLoading = true;
            this.error = null;

            try {
                await requestData(
                    {
                        method: 'POST',
                        url: `/admin/licenses/${id}/reset-domain`,
                        data: { reason },
                    },
                    backendLicenseSchema,
                );
            } catch (error: unknown) {
                this.error = extractApiError(error);
            } finally {
                this.cache.clear();
                await this.fetchLicenses();
                if (this.detail?.id === id) {
                    await this.fetchLicenseDetail(id);
                }
                this.actionLoading = false;
            }
        },

        async bulkRevoke(reason: string): Promise<void> {
            const ids = [...this.selectedIds];

            for (const id of ids) {
                await this.revokeLicense(id, reason);
            }

            this.clearSelection();
        },
    },
});
