import { defineStore } from 'pinia';
import { z } from 'zod';

import { adminSettingsSchema, twoFactorSetupSchema } from '@/admin/schemas/api';
import { defaultSettings } from '@/admin/services/demoData';
import { extractApiError, requestData } from '@/admin/services/http';
import type {
    AdminSettingsPayload,
    ApiError,
    TwoFactorConfirmRequest,
    TwoFactorSetupPayload,
    UpdateAdminSettingsRequest,
} from '@/admin/types/api';

export const useSettingsStore = defineStore('settingsStore', {
    state: () => ({
        settings: defaultSettings as AdminSettingsPayload,
        loading: false,
        saving: false,
        error: null as ApiError | null,
        twoFactorSetup: null as TwoFactorSetupPayload | null,
    }),
    actions: {
        async fetchSettings(): Promise<void> {
            this.loading = true;
            this.error = null;

            try {
                const payload = await requestData(
                    {
                        method: 'GET',
                        url: '/admin/settings',
                    },
                    adminSettingsSchema.partial(),
                );

                this.settings = {
                    ...defaultSettings,
                    ...payload,
                    domain_policies: {
                        ...defaultSettings.domain_policies,
                        ...(payload.domain_policies ?? {}),
                    },
                    reset_policies: {
                        ...defaultSettings.reset_policies,
                        ...(payload.reset_policies ?? {}),
                    },
                };
            } catch (error: unknown) {
                this.error = extractApiError(error);
                this.settings = defaultSettings;
            } finally {
                this.loading = false;
            }
        },

        async updateSettings(payload: UpdateAdminSettingsRequest): Promise<void> {
            this.saving = true;
            this.error = null;

            try {
                const response = await requestData(
                    {
                        method: 'PUT',
                        url: '/admin/settings',
                        data: payload,
                    },
                    adminSettingsSchema.partial(),
                );

                this.settings = {
                    ...this.settings,
                    ...response,
                    domain_policies: {
                        ...this.settings.domain_policies,
                        ...(response.domain_policies ?? {}),
                    },
                    reset_policies: {
                        ...this.settings.reset_policies,
                        ...(response.reset_policies ?? {}),
                    },
                };
            } catch (error: unknown) {
                this.error = extractApiError(error);
                throw this.error;
            } finally {
                this.saving = false;
            }
        },

        async setupTwoFactor(): Promise<void> {
            this.saving = true;
            this.error = null;

            try {
                const payload = await requestData(
                    {
                        method: 'POST',
                        url: '/admin/2fa/setup',
                    },
                    twoFactorSetupSchema,
                );

                this.twoFactorSetup = payload;
            } catch (error: unknown) {
                this.error = extractApiError(error);
                throw this.error;
            } finally {
                this.saving = false;
            }
        },

        async confirmTwoFactor(payload: TwoFactorConfirmRequest): Promise<void> {
            this.saving = true;
            this.error = null;

            try {
                await requestData(
                    {
                        method: 'POST',
                        url: '/admin/2fa/confirm',
                        data: payload,
                    },
                    z.object({
                        two_factor_enabled: z.boolean(),
                    }),
                );
            } catch (error: unknown) {
                this.error = extractApiError(error);
                throw this.error;
            } finally {
                this.saving = false;
            }
        },

        async testEnvatoToken(): Promise<boolean> {
            this.saving = true;
            this.error = null;

            try {
                await requestData(
                    {
                        method: 'GET',
                        url: '/admin/settings/test-envato-token',
                    },
                    z.object({
                        ok: z.boolean(),
                    }),
                );

                return true;
            } catch (error: unknown) {
                this.error = extractApiError(error);
                return false;
            } finally {
                this.saving = false;
            }
        },
    },
});
