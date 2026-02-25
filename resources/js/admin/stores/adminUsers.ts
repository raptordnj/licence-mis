import { defineStore } from 'pinia';
import { z } from 'zod';

import { adminUserSchema, paginatedSchema } from '@/admin/schemas/api';
import { demoAdminUsers, paginate } from '@/admin/services/demoData';
import { extractApiError, requestData } from '@/admin/services/http';
import type { AdminUser, ApiError, DataListQuery, PaginatedResponse } from '@/admin/types/api';

interface AdminUserQuery extends DataListQuery {
    role: string;
    twoFactor: string;
}

const defaultQuery = (): AdminUserQuery => ({
    page: 1,
    perPage: 15,
    search: '',
    sortBy: 'name',
    sortDir: 'asc',
    role: '',
    twoFactor: '',
});

const userListSchema = paginatedSchema(adminUserSchema);

const upsertUserSchema = z.object({
    id: z.number(),
});

const filterUsers = (query: AdminUserQuery): AdminUser[] =>
    demoAdminUsers.filter((entry) => {
        if (query.search !== '') {
            const value = query.search.toLowerCase();

            if (!entry.name.toLowerCase().includes(value) && !entry.email.toLowerCase().includes(value)) {
                return false;
            }
        }

        if (query.role !== '' && entry.role !== query.role) {
            return false;
        }

        if (query.twoFactor === 'enabled' && !entry.two_factor_enabled) {
            return false;
        }

        if (query.twoFactor === 'disabled' && entry.two_factor_enabled) {
            return false;
        }

        return true;
    });

export const useAdminUsersStore = defineStore('adminUsersStore', {
    state: () => ({
        query: defaultQuery() as AdminUserQuery,
        response: paginate(demoAdminUsers, 1, 15, '/api/v1/admin/users') as PaginatedResponse<AdminUser>,
        loading: false,
        saving: false,
        error: null as ApiError | null,
        cache: new Map<string, PaginatedResponse<AdminUser>>(),
        abortController: null as AbortController | null,
    }),
    getters: {
        rows: (state): AdminUser[] => state.response.data,
    },
    actions: {
        setQuery(partial: Partial<AdminUserQuery>): void {
            this.query = {
                ...this.query,
                ...partial,
            };
        },

        async fetchUsers(partial: Partial<AdminUserQuery> = {}): Promise<void> {
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
                        url: '/admin/users',
                        signal: controller.signal,
                        params: {
                            page: this.query.page,
                            per_page: this.query.perPage,
                            search: this.query.search || undefined,
                            role: this.query.role || undefined,
                        },
                    },
                    userListSchema,
                );

                this.response = payload;
                this.cache.set(cacheKey, payload);
            } catch (error: unknown) {
                this.error = extractApiError(error);
                const filtered = filterUsers(this.query);
                this.response = paginate(filtered, this.query.page, this.query.perPage, '/api/v1/admin/users');
            } finally {
                this.loading = false;
            }
        },

        async createUser(payload: Partial<AdminUser>): Promise<void> {
            this.saving = true;
            this.error = null;

            try {
                await requestData(
                    {
                        method: 'POST',
                        url: '/admin/users',
                        data: payload,
                    },
                    upsertUserSchema,
                );
            } catch (error: unknown) {
                this.error = extractApiError(error);
            } finally {
                this.saving = false;
                this.cache.clear();
                await this.fetchUsers();
            }
        },

        async updateRole(userId: number, role: string): Promise<void> {
            this.saving = true;
            this.error = null;

            try {
                await requestData(
                    {
                        method: 'PATCH',
                        url: `/admin/users/${userId}/role`,
                        data: { role },
                    },
                    upsertUserSchema,
                );
            } catch (error: unknown) {
                this.error = extractApiError(error);
            } finally {
                this.saving = false;
                this.cache.clear();
                await this.fetchUsers();
            }
        },
    },
});
