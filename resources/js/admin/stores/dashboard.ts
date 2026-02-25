import { defineStore } from 'pinia';
import { z } from 'zod';

import { dashboardPayloadSchema } from '@/admin/schemas/api';
import { demoDashboardPayload } from '@/admin/services/demoData';
import { extractApiError, requestData } from '@/admin/services/http';
import type { ApiError, DashboardPayload } from '@/admin/types/api';

const rangeOptions = ['today', '7d', '30d', 'custom'] as const;

export type DashboardRange = (typeof rangeOptions)[number];

const backendDashboardSchema = z.object({
    metrics: z.object({
        total_licenses: z.number(),
        active_licenses: z.number(),
        revoked_licenses: z.number(),
        expired_licenses: z.number(),
    }),
    recent_licenses: z.array(
        z.object({
            id: z.number(),
            purchase_code: z.string(),
            status: z.string(),
            bound_domain: z.string().nullable(),
            envato_item_id: z.number().nullable(),
            verified_at: z.string().nullable(),
        }),
    ),
    recent_audit_logs: z.array(
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
        }),
    ),
});

const mapBackendDashboard = (payload: z.infer<typeof backendDashboardSchema>): DashboardPayload => {
    const recentBound = payload.recent_licenses.filter((entry) => entry.bound_domain !== null).length;
    const recentUnbound = payload.recent_licenses.filter((entry) => entry.bound_domain === null).length;
    const checksToday = payload.recent_audit_logs.filter((entry) => {
        if (entry.created_at === null) {
            return false;
        }

        const date = new Date(entry.created_at);
        const today = new Date();

        return date.toDateString() === today.toDateString();
    }).length;

    const activity = payload.recent_audit_logs.map((entry) => ({
        id: `activity-${entry.id}`,
        type: entry.event_type,
        title: `${entry.event_type.replace(/_/g, ' ')} on ${entry.license?.purchase_code ?? 'n/a'}`,
        actor: entry.actor?.email ?? null,
        timestamp: entry.created_at ?? new Date().toISOString(),
    }));

    const merged = {
        ...demoDashboardPayload,
        metrics: {
            ...demoDashboardPayload.metrics,
            total_licenses: payload.metrics.total_licenses,
            active_licenses: payload.metrics.active_licenses,
            revoked_licenses: payload.metrics.revoked_licenses,
            bound_licenses: recentBound,
            unbound_licenses: recentUnbound,
            checks_today: checksToday,
        },
        recent_activity: activity.length > 0 ? activity : demoDashboardPayload.recent_activity,
    };

    return dashboardPayloadSchema.parse(merged);
};

export const useDashboardStore = defineStore('dashboardStore', {
    state: () => ({
        range: '7d' as DashboardRange,
        customRange: {
            from: '',
            to: '',
        },
        payload: demoDashboardPayload as DashboardPayload,
        loading: false,
        error: null as ApiError | null,
    }),
    actions: {
        setRange(range: DashboardRange): void {
            this.range = range;
        },

        async fetchDashboard(): Promise<void> {
            this.loading = true;
            this.error = null;

            try {
                const backendPayload = await requestData(
                    {
                        method: 'GET',
                        url: '/admin/dashboard',
                    },
                    backendDashboardSchema,
                );

                this.payload = mapBackendDashboard(backendPayload);
            } catch (error: unknown) {
                this.error = extractApiError(error);
                this.payload = demoDashboardPayload;
            } finally {
                this.loading = false;
            }
        },
    },
});
