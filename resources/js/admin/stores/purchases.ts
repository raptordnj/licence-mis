import { defineStore } from 'pinia';

import { paginatedSchema, purchaseDetailSchema, purchaseSchema } from '@/admin/schemas/api';
import { demoPurchases, paginate } from '@/admin/services/demoData';
import { extractApiError, requestData } from '@/admin/services/http';
import type { ApiError, DataListQuery, PaginatedResponse, Purchase, PurchaseDetail } from '@/admin/types/api';

interface PurchaseQuery extends DataListQuery {
    itemId: string;
    buyer: string;
    status: string;
}

const defaultQuery = (): PurchaseQuery => ({
    page: 1,
    perPage: 15,
    search: '',
    sortBy: 'created_at',
    sortDir: 'desc',
    itemId: '',
    buyer: '',
    status: '',
});

const purchaseListSchema = paginatedSchema(purchaseSchema);

const filterPurchases = (query: PurchaseQuery): Purchase[] =>
    demoPurchases.filter((entry) => {
        if (query.search !== '') {
            const value = query.search.toLowerCase();
            const matches =
                entry.purchase_code.toLowerCase().includes(value) ||
                entry.item_name.toLowerCase().includes(value) ||
                entry.buyer.toLowerCase().includes(value);

            if (!matches) {
                return false;
            }
        }

        if (query.itemId !== '' && String(entry.envato_item_id) !== query.itemId) {
            return false;
        }

        if (query.buyer !== '' && !entry.buyer.toLowerCase().includes(query.buyer.toLowerCase())) {
            return false;
        }

        if (query.status !== '' && entry.status !== query.status) {
            return false;
        }

        return true;
    });

export const usePurchasesStore = defineStore('purchasesStore', {
    state: () => ({
        query: defaultQuery() as PurchaseQuery,
        response: paginate(demoPurchases, 1, 15, '/api/v1/admin/purchases') as PaginatedResponse<Purchase>,
        loading: false,
        error: null as ApiError | null,
        detail: null as PurchaseDetail | null,
        detailLoading: false,
        detailError: null as ApiError | null,
        cache: new Map<string, PaginatedResponse<Purchase>>(),
        abortController: null as AbortController | null,
    }),
    getters: {
        rows: (state): Purchase[] => state.response.data,
    },
    actions: {
        setQuery(partial: Partial<PurchaseQuery>): void {
            this.query = {
                ...this.query,
                ...partial,
            };
        },

        async fetchPurchaseDetail(id: number): Promise<void> {
            this.detailLoading = true;
            this.detailError = null;

            try {
                const payload = await requestData(
                    {
                        method: 'GET',
                        url: `/admin/purchases/${id}`,
                    },
                    purchaseDetailSchema,
                );

                this.detail = payload;
            } catch (error: unknown) {
                this.detailError = extractApiError(error);
                this.detail = null;
            } finally {
                this.detailLoading = false;
            }
        },

        async fetchPurchases(partial: Partial<PurchaseQuery> = {}): Promise<void> {
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
                        url: '/admin/purchases',
                        signal: controller.signal,
                        params: {
                            page: this.query.page,
                            per_page: this.query.perPage,
                            search: this.query.search || undefined,
                            item_id: this.query.itemId || undefined,
                            buyer: this.query.buyer || undefined,
                            status: this.query.status || undefined,
                        },
                    },
                    purchaseListSchema,
                );

                this.response = payload;
                this.cache.set(cacheKey, payload);
            } catch (error: unknown) {
                this.error = extractApiError(error);
                const filtered = filterPurchases(this.query);
                this.response = paginate(filtered, this.query.page, this.query.perPage, '/api/v1/admin/purchases');
            } finally {
                this.loading = false;
            }
        },
    },
});
