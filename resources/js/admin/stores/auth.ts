import { defineStore } from 'pinia';
import { z } from 'zod';

import { hasPermission, type Permission } from '@/admin/constants/rbac';
import { adminLoginResponseSchema, adminProfileSchema, logoutOtherDevicesSchema } from '@/admin/schemas/api';
import { extractApiError, requestData, setAuthToken } from '@/admin/services/http';
import type {
    AdminLoginRequest,
    AdminProfile,
    ApiError,
    TwoFactorChallengeState,
} from '@/admin/types/api';

const LOCAL_STORAGE_KEY = 'licence_mis_admin_token';
const SESSION_STORAGE_KEY = 'licence_mis_admin_token_session';

interface PersistedToken {
    token: string | null;
    remember: boolean;
}

const readToken = (): PersistedToken => {
    if (typeof window === 'undefined') {
        return {
            token: null,
            remember: false,
        };
    }

    const localToken = window.localStorage.getItem(LOCAL_STORAGE_KEY);

    if (localToken !== null && localToken !== '') {
        return {
            token: localToken,
            remember: true,
        };
    }

    const sessionToken = window.sessionStorage.getItem(SESSION_STORAGE_KEY);

    return {
        token: sessionToken,
        remember: false,
    };
};

const persistToken = (token: string | null, remember: boolean): void => {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.removeItem(LOCAL_STORAGE_KEY);
    window.sessionStorage.removeItem(SESSION_STORAGE_KEY);

    if (token === null || token === '') {
        return;
    }

    if (remember) {
        window.localStorage.setItem(LOCAL_STORAGE_KEY, token);
        return;
    }

    window.sessionStorage.setItem(SESSION_STORAGE_KEY, token);
};

const persisted = readToken();

export const useAuthStore = defineStore('authStore', {
    state: () => ({
        token: persisted.token as string | null,
        rememberMe: persisted.remember,
        admin: null as AdminProfile | null,
        initialized: false,
        loading: false,
        error: null as ApiError | null,
        pendingTwoFactor: null as TwoFactorChallengeState | null,
    }),
    getters: {
        isAuthenticated: (state): boolean => state.token !== null && state.admin !== null,
        role: (state): string | null => state.admin?.role ?? null,
    },
    actions: {
        can(permission: Permission): boolean {
            return hasPermission(this.admin?.role ?? null, permission);
        },

        async bootstrap(): Promise<void> {
            if (this.initialized) {
                return;
            }

            this.loading = true;
            this.error = null;

            if (this.token !== null && this.token !== '') {
                setAuthToken(this.token);

                try {
                    await this.fetchProfile();
                } catch {
                    this.clearSession();
                }
            }

            this.loading = false;
            this.initialized = true;
        },

        async login(payload: AdminLoginRequest): Promise<void> {
            this.loading = true;
            this.error = null;

            try {
                const response = await requestData(
                    {
                        method: 'POST',
                        url: '/admin/auth/login',
                        data: payload,
                    },
                    adminLoginResponseSchema,
                );

                this.token = response.token;
                this.rememberMe = Boolean(payload.remember_me);
                this.pendingTwoFactor = null;

                setAuthToken(this.token);
                persistToken(this.token, this.rememberMe);

                await this.fetchProfile();
            } catch (error: unknown) {
                this.error = extractApiError(error);

                if (this.error.code === 'TWO_FACTOR_REQUIRED' || this.error.code === 'TWO_FACTOR_INVALID') {
                    this.pendingTwoFactor = {
                        email: payload.email,
                        password: payload.password,
                        remember_me: Boolean(payload.remember_me),
                    };
                }

                throw this.error;
            } finally {
                this.loading = false;
            }
        },

        async submitTwoFactor(payload: { code?: string; recoveryCode?: string }): Promise<void> {
            if (this.pendingTwoFactor === null) {
                throw new Error('Two-factor challenge is not initialized.');
            }

            await this.login({
                email: this.pendingTwoFactor.email,
                password: this.pendingTwoFactor.password,
                remember_me: this.pendingTwoFactor.remember_me,
                two_factor_code: payload.code,
                recovery_code: payload.recoveryCode,
            });
        },

        async fetchProfile(): Promise<void> {
            const profile = await requestData(
                {
                    method: 'GET',
                    url: '/admin/auth/me',
                },
                adminProfileSchema,
            );

            this.admin = profile;
            this.error = null;
        },

        async logoutOtherDevices(): Promise<number> {
            try {
                const payload = await requestData(
                    {
                        method: 'POST',
                        url: '/admin/auth/logout-other-devices',
                    },
                    logoutOtherDevicesSchema,
                );

                return payload.revoked_tokens_count;
            } catch (error: unknown) {
                this.error = extractApiError(error);
                throw this.error;
            }
        },

        async logout(): Promise<void> {
            this.loading = true;

            try {
                if (this.token !== null) {
                    await requestData(
                        {
                            method: 'POST',
                            url: '/admin/auth/logout',
                        },
                        z.object({
                            logged_out: z.boolean(),
                        }),
                    );
                }
            } catch {
                // Intentionally ignored. Local session still gets cleared.
            } finally {
                this.clearSession();
                this.loading = false;
            }
        },

        clearSession(): void {
            this.token = null;
            this.admin = null;
            this.pendingTwoFactor = null;
            this.error = null;
            setAuthToken(null);
            persistToken(null, false);
        },
    },
});
