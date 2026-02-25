import { defineStore } from 'pinia';

import { paginatedSchema, validationLogSchema } from '@/admin/schemas/api';
import { demoValidationLogs, paginate } from '@/admin/services/demoData';
import { extractApiError, requestData } from '@/admin/services/http';
import type { ApiError, DataListQuery, PaginatedResponse, ValidationLog } from '@/admin/types/api';

interface LogQuery extends DataListQuery {
    result: string;
    failReason: string;
    item: string;
    purchaseCode: string;
    domain: string;
    ip: string;
    from: string;
    to: string;
}

const defaultQuery = (): LogQuery => ({
    page: 1,
    perPage: 20,
    search: '',
    sortBy: 'time',
    sortDir: 'desc',
    result: '',
    failReason: '',
    item: '',
    purchaseCode: '',
    domain: '',
    ip: '',
    from: '',
    to: '',
});

const logsListSchema = paginatedSchema(validationLogSchema);

const filterLogs = (query: LogQuery): ValidationLog[] =>
    demoValidationLogs.filter((entry) => {
        if (query.result !== '' && entry.result !== query.result) {
            return false;
        }

        if (query.failReason !== '' && entry.fail_reason !== query.failReason) {
            return false;
        }

        if (query.item !== '' && !entry.item_name.toLowerCase().includes(query.item.toLowerCase())) {
            return false;
        }

        if (query.purchaseCode !== '' && !entry.purchase_code.toLowerCase().includes(query.purchaseCode.toLowerCase())) {
            return false;
        }

        if (query.domain !== '' && !entry.domain_requested.toLowerCase().includes(query.domain.toLowerCase())) {
            return false;
        }

        if (query.ip !== '' && !entry.ip.includes(query.ip)) {
            return false;
        }

        return true;
    });

export const useLogsStore = defineStore('logsStore', {
    state: () => ({
        query: defaultQuery() as LogQuery,
        response: paginate(demoValidationLogs, 1, 20, '/api/v1/admin/validation-logs') as PaginatedResponse<ValidationLog>,
        loading: false,
        error: null as ApiError | null,
        cache: new Map<string, PaginatedResponse<ValidationLog>>(),
        abortController: null as AbortController | null,
        selectedLog: null as ValidationLog | null,
    }),
    getters: {
        rows: (state): ValidationLog[] => state.response.data,
    },
    actions: {
        setQuery(partial: Partial<LogQuery>): void {
            this.query = {
                ...this.query,
                ...partial,
            };
        },

        openDetails(log: ValidationLog): void {
            this.selectedLog = log;
        },

        closeDetails(): void {
            this.selectedLog = null;
        },

        async fetchLogs(partial: Partial<LogQuery> = {}): Promise<void> {
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
                        url: '/admin/validation-logs',
                        signal: controller.signal,
                        params: {
                            page: this.query.page,
                            per_page: this.query.perPage,
                            result: this.query.result || undefined,
                            fail_reason: this.query.failReason || undefined,
                            item: this.query.item || undefined,
                            purchase_code: this.query.purchaseCode || undefined,
                            domain: this.query.domain || undefined,
                            ip: this.query.ip || undefined,
                            from: this.query.from || undefined,
                            to: this.query.to || undefined,
                        },
                    },
                    logsListSchema,
                );

                this.response = payload;
                this.cache.set(cacheKey, payload);
            } catch (error: unknown) {
                this.error = extractApiError(error);
                const filtered = filterLogs(this.query);
                this.response = paginate(filtered, this.query.page, this.query.perPage, '/api/v1/admin/validation-logs');
            } finally {
                this.loading = false;
            }
        },
    },
});
