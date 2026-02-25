import { defineStore } from 'pinia';
import { z } from 'zod';

import { envatoItemSchema, paginatedSchema } from '@/admin/schemas/api';
import { demoItems, paginate } from '@/admin/services/demoData';
import { extractApiError, requestData } from '@/admin/services/http';
import type { ApiError, DataListQuery, EnvatoItem, PaginatedResponse } from '@/admin/types/api';

interface ItemQuery extends DataListQuery {
    marketplace: string;
    status: string;
}

const defaultQuery = (): ItemQuery => ({
    page: 1,
    perPage: 10,
    search: '',
    sortBy: 'created_at',
    sortDir: 'desc',
    marketplace: '',
    status: '',
});

const itemListSchema = paginatedSchema(envatoItemSchema);

const filterItems = (query: ItemQuery): EnvatoItem[] =>
    demoItems.filter((item) => {
        if (query.search !== '') {
            const value = query.search.toLowerCase();
            const matches =
                item.name.toLowerCase().includes(value) ||
                String(item.envato_item_id).includes(value) ||
                item.marketplace.toLowerCase().includes(value);

            if (!matches) {
                return false;
            }
        }

        if (query.marketplace !== '' && query.marketplace !== item.marketplace) {
            return false;
        }

        if (query.status !== '' && query.status !== item.status) {
            return false;
        }

        return true;
    });

const upsertItemSchema = z.object({
    id: z.number(),
});

export const useItemsStore = defineStore('itemsStore', {
    state: () => ({
        query: defaultQuery() as ItemQuery,
        response: paginate(demoItems, 1, 10, '/api/v1/admin/items') as PaginatedResponse<EnvatoItem>,
        loading: false,
        error: null as ApiError | null,
        saving: false,
        cache: new Map<string, PaginatedResponse<EnvatoItem>>(),
        abortController: null as AbortController | null,
    }),
    getters: {
        rows: (state): EnvatoItem[] => state.response.data,
    },
    actions: {
        setQuery(partial: Partial<ItemQuery>): void {
            this.query = {
                ...this.query,
                ...partial,
            };
        },

        async fetchItems(partial: Partial<ItemQuery> = {}): Promise<void> {
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
                        url: '/admin/items',
                        signal: controller.signal,
                        params: {
                            page: this.query.page,
                            per_page: this.query.perPage,
                            search: this.query.search || undefined,
                            marketplace: this.query.marketplace || undefined,
                            status: this.query.status || undefined,
                        },
                    },
                    itemListSchema,
                );

                this.response = payload;
                this.cache.set(cacheKey, payload);
            } catch (error: unknown) {
                this.error = extractApiError(error);
                const filtered = filterItems(this.query);
                this.response = paginate(filtered, this.query.page, this.query.perPage, '/api/v1/admin/items');
            } finally {
                this.loading = false;
            }
        },

        async saveItem(payload: Partial<EnvatoItem>): Promise<void> {
            this.saving = true;
            this.error = null;

            try {
                await requestData(
                    {
                        method: payload.id !== undefined ? 'PUT' : 'POST',
                        url: payload.id !== undefined ? `/admin/items/${payload.id}` : '/admin/items',
                        data: payload,
                    },
                    upsertItemSchema,
                );
            } catch (error: unknown) {
                this.error = extractApiError(error);
            } finally {
                this.cache.clear();
                this.saving = false;
                await this.fetchItems();
            }
        },
    },
});
