import { defineStore } from 'pinia';
import { z } from 'zod';

import { auditLogSchema, paginatedSchema } from '@/admin/schemas/api';
import { demoAuditLogs, paginate } from '@/admin/services/demoData';
import { extractApiError, requestData } from '@/admin/services/http';
import type { ApiError, AuditLog, DataListQuery, PaginatedResponse } from '@/admin/types/api';

interface AuditQuery extends DataListQuery {
    actionType: string;
    actor: string;
    target: string;
    from: string;
    to: string;
}

const defaultQuery = (): AuditQuery => ({
    page: 1,
    perPage: 20,
    search: '',
    sortBy: 'created_at',
    sortDir: 'desc',
    actionType: '',
    actor: '',
    target: '',
    from: '',
    to: '',
});

const backendAuditSchema = paginatedSchema(
    z.object({
        id: z.number(),
        event_type: z.string(),
        created_at: z.string().nullable(),
        actor: z
            .object({
                id: z.number(),
                name: z.string(),
                email: z.string(),
            })
            .nullable(),
        license: z
            .object({
                id: z.number(),
                purchase_code: z.string(),
            })
            .nullable(),
        metadata: z.record(z.string(), z.unknown()).nullable().optional(),
    }),
);

const filterDemo = (query: AuditQuery): AuditLog[] =>
    demoAuditLogs.filter((entry) => {
        if (query.actionType !== '' && entry.event_type !== query.actionType) {
            return false;
        }

        if (query.actor !== '' && !entry.actor?.email.toLowerCase().includes(query.actor.toLowerCase())) {
            return false;
        }

        if (query.target !== '' && !(entry.target ?? '').toLowerCase().includes(query.target.toLowerCase())) {
            return false;
        }

        return true;
    });

export const useAuditLogsStore = defineStore('auditLogsStore', {
    state: () => ({
        query: defaultQuery() as AuditQuery,
        response: paginate(demoAuditLogs, 1, 20, '/api/v1/admin/audit-logs') as PaginatedResponse<AuditLog>,
        loading: false,
        error: null as ApiError | null,
        selected: null as AuditLog | null,
        cache: new Map<string, PaginatedResponse<AuditLog>>(),
        abortController: null as AbortController | null,
    }),
    getters: {
        rows: (state): AuditLog[] => state.response.data,
    },
    actions: {
        setQuery(partial: Partial<AuditQuery>): void {
            this.query = {
                ...this.query,
                ...partial,
            };
        },

        open(entry: AuditLog): void {
            this.selected = entry;
        },

        close(): void {
            this.selected = null;
        },

        async fetchAuditLogs(partial: Partial<AuditQuery> = {}): Promise<void> {
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
                        url: '/admin/audit-logs',
                        signal: controller.signal,
                        params: {
                            page: this.query.page,
                            per_page: this.query.perPage,
                            search: this.query.search || undefined,
                            event_type: this.query.actionType || undefined,
                        },
                    },
                    backendAuditSchema,
                );

                const mapped: PaginatedResponse<AuditLog> = {
                    ...payload,
                    data: payload.data.map((entry) =>
                        auditLogSchema.parse({
                            id: entry.id,
                            event_type: entry.event_type,
                            created_at: entry.created_at,
                            actor: entry.actor,
                            target: entry.license?.purchase_code ?? null,
                            metadata: entry.metadata ?? null,
                        }),
                    ),
                };

                this.response = mapped;
                this.cache.set(cacheKey, mapped);
            } catch (error: unknown) {
                this.error = extractApiError(error);
                const filtered = filterDemo(this.query);
                this.response = paginate(filtered, this.query.page, this.query.perPage, '/api/v1/admin/audit-logs');
            } finally {
                this.loading = false;
            }
        },
    },
});
