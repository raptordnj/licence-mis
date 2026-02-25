import type {
    AdminSettingsPayload,
    AdminUser,
    AuditLog,
    DashboardActivityItem,
    DashboardFailureReason,
    DashboardPayload,
    DashboardSeriesPoint,
    DashboardTopDomain,
    EnvatoItem,
    GlobalSearchGroupedResult,
    License,
    PaginatedResponse,
    Purchase,
    ValidationLog,
} from '@/admin/types/api';

const now = new Date();

const dateOffset = (days: number): string => {
    const value = new Date(now);
    value.setDate(value.getDate() + days);
    return value.toISOString();
};

export const demoItems: EnvatoItem[] = [
    {
        id: 1,
        marketplace: 'envato',
        envato_item_id: 1001,
        name: 'Apex CRM SaaS Template',
        status: 'active',
        licenses_count: 142,
        created_at: dateOffset(-210),
    },
    {
        id: 2,
        marketplace: 'envato',
        envato_item_id: 1002,
        name: 'Pulse Booking System',
        status: 'active',
        licenses_count: 87,
        created_at: dateOffset(-140),
    },
    {
        id: 3,
        marketplace: 'envato',
        envato_item_id: 1003,
        name: 'Atlas Learning Platform',
        status: 'disabled',
        licenses_count: 29,
        created_at: dateOffset(-95),
    },
];

export const demoPurchases: Purchase[] = [
    {
        id: 1101,
        purchase_code: 'P-AX12-9931-ENV',
        item_name: 'Apex CRM SaaS Template',
        envato_item_id: 1001,
        buyer: 'jane.smith',
        buyer_username: 'jane.smith',
        buyer_email: 'jane.smith@example.com',
        license_type: 'regular',
        version: '4.7.11',
        purchase_date: dateOffset(-45),
        supported_until: dateOffset(145),
        activated_at: dateOffset(-42),
        status: 'valid',
        created_at: dateOffset(-44),
    },
    {
        id: 1102,
        purchase_code: 'P-AX12-9932-ENV',
        item_name: 'Pulse Booking System',
        envato_item_id: 1002,
        buyer: 'omar.brown',
        buyer_username: 'omar.brown',
        buyer_email: 'omar@example.com',
        license_type: 'extended',
        version: '4.7.11',
        purchase_date: dateOffset(-75),
        supported_until: dateOffset(-5),
        activated_at: dateOffset(-71),
        status: 'expired',
        created_at: dateOffset(-74),
    },
    {
        id: 1103,
        purchase_code: 'P-AX12-9933-ENV',
        item_name: 'Atlas Learning Platform',
        envato_item_id: 1003,
        buyer: 'nina.w',
        buyer_username: 'nina.w',
        buyer_email: null,
        license_type: 'regular',
        version: '4.7.11',
        purchase_date: dateOffset(-12),
        supported_until: dateOffset(340),
        activated_at: dateOffset(-11),
        status: 'valid',
        created_at: dateOffset(-11),
    },
];

export const demoLicenses: License[] = [
    {
        id: 501,
        item_name: 'Apex CRM SaaS Template',
        envato_item_id: 1001,
        purchase_code: 'P-AX12-9931-ENV',
        status: 'active',
        license_type: 'regular',
        buyer_username: 'jane.smith',
        version: '4.7.11',
        bound_domain: 'client-alpha.com',
        bound_domain_original: 'www.client-alpha.com',
        bound_at: dateOffset(-42),
        activated_at: dateOffset(-42),
        last_check_at: dateOffset(-1),
        reset_count: 1,
        created_at: dateOffset(-44),
        updated_at: dateOffset(-1),
    },
    {
        id: 502,
        item_name: 'Pulse Booking System',
        envato_item_id: 1002,
        purchase_code: 'P-AX12-9932-ENV',
        status: 'revoked',
        license_type: 'extended',
        buyer_username: 'omar.brown',
        version: '4.7.11',
        bound_domain: 'demo-hotel.app',
        bound_domain_original: 'demo-hotel.app',
        bound_at: dateOffset(-71),
        activated_at: dateOffset(-71),
        last_check_at: dateOffset(-3),
        reset_count: 2,
        created_at: dateOffset(-74),
        updated_at: dateOffset(-3),
    },
    {
        id: 503,
        item_name: 'Atlas Learning Platform',
        envato_item_id: 1003,
        purchase_code: 'P-AX12-9933-ENV',
        status: 'active',
        license_type: 'regular',
        buyer_username: 'nina.w',
        version: '4.7.11',
        bound_domain: null,
        bound_domain_original: null,
        bound_at: null,
        activated_at: dateOffset(-11),
        last_check_at: dateOffset(-1),
        reset_count: 0,
        created_at: dateOffset(-11),
        updated_at: dateOffset(-1),
    },
];

export const demoValidationLogs: ValidationLog[] = [
    {
        id: 'vlog_1',
        time: dateOffset(-1),
        result: 'success',
        fail_reason: null,
        domain_requested: 'client-alpha.com',
        ip: '203.0.113.4',
        user_agent: 'WordPress/6.8',
        purchase_code: 'P-AX12-9931-ENV',
        item_name: 'Apex CRM SaaS Template',
        correlation_id: 'corr-d5f0d01',
        signature_present: true,
    },
    {
        id: 'vlog_2',
        time: dateOffset(-1),
        result: 'fail',
        fail_reason: 'DOMAIN_MISMATCH',
        domain_requested: 'client-beta.com',
        ip: '198.51.100.22',
        user_agent: 'Laravel HTTP Client',
        purchase_code: 'P-AX12-9931-ENV',
        item_name: 'Apex CRM SaaS Template',
        correlation_id: 'corr-ccb9a2a',
        signature_present: true,
    },
    {
        id: 'vlog_3',
        time: dateOffset(-2),
        result: 'fail',
        fail_reason: 'LICENSE_REVOKED',
        domain_requested: 'demo-hotel.app',
        ip: '198.51.100.39',
        user_agent: 'NuxtApp/1.2',
        purchase_code: 'P-AX12-9932-ENV',
        item_name: 'Pulse Booking System',
        correlation_id: 'corr-9b7ce6f',
        signature_present: true,
    },
];

export const demoAdminUsers: AdminUser[] = [
    {
        id: 1,
        name: 'Super Admin',
        email: 'super-admin@example.com',
        role: 'super-admin',
        two_factor_enabled: true,
        last_login_at: dateOffset(-1),
        disabled: false,
    },
    {
        id: 2,
        name: 'Admin User',
        email: 'admin@example.com',
        role: 'admin',
        two_factor_enabled: false,
        last_login_at: dateOffset(-2),
        disabled: false,
    },
    {
        id: 3,
        name: 'Support Analyst',
        email: 'support@example.com',
        role: 'support',
        two_factor_enabled: false,
        last_login_at: dateOffset(-1),
        disabled: false,
    },
];

export const demoAuditLogs: AuditLog[] = [
    {
        id: 901,
        event_type: 'license_revoked',
        created_at: dateOffset(-1),
        actor: {
            id: 2,
            name: 'Admin User',
            email: 'admin@example.com',
        },
        target: '#502',
        metadata: {
            reason: 'Chargeback detected',
            previous_status: 'active',
            new_status: 'revoked',
        },
    },
    {
        id: 902,
        event_type: 'domain_reset',
        created_at: dateOffset(-2),
        actor: {
            id: 2,
            name: 'Admin User',
            email: 'admin@example.com',
        },
        target: '#501',
        metadata: {
            reason: 'Customer moved hosting provider',
            previous_domain: 'old-client-alpha.com',
            new_domain: null,
        },
    },
    {
        id: 903,
        event_type: 'token_changed',
        created_at: dateOffset(-3),
        actor: {
            id: 1,
            name: 'Super Admin',
            email: 'super-admin@example.com',
        },
        target: 'settings',
        metadata: {
            changed_fields: ['envato_api_token', 'license_hmac_key'],
            before: { active_secret_version: 'v2' },
            after: { active_secret_version: 'v3' },
        },
    },
];

export const defaultSettings: AdminSettingsPayload = {
    has_envato_api_token: true,
    has_license_hmac_key: true,
    envato_api_base_url: 'https://api.envato.com/v3',
    envato_mock_mode: false,
    rate_limit_per_minute: 30,
    active_secret_version: 'v3',
    domain_policies: {
        treat_www_as_same: true,
        allow_localhost: false,
        allow_ip_domains: false,
    },
    reset_policies: {
        max_resets_allowed: 3,
    },
};

const checksSeries: DashboardSeriesPoint[] = Array.from({ length: 14 }, (_, index) => {
    const day = index - 13;
    const checks = 80 + (index % 5) * 10 + (index % 2 === 0 ? 6 : 0);
    const failures = Math.max(3, Math.round(checks * 0.07));

    return {
        date: dateOffset(day),
        checks,
        failures,
    };
});

const failureReasons: DashboardFailureReason[] = [
    { reason: 'DOMAIN_MISMATCH', count: 31 },
    { reason: 'LICENSE_REVOKED', count: 12 },
    { reason: 'PURCHASE_INVALID', count: 8 },
    { reason: 'RATE_LIMITED', count: 5 },
];

const topDomains: DashboardTopDomain[] = [
    { domain: 'client-alpha.com', checks: 93, failures: 5 },
    { domain: 'demo-hotel.app', checks: 51, failures: 12 },
    { domain: 'academy-atlas.io', checks: 47, failures: 2 },
];

const recentActivity: DashboardActivityItem[] = [
    {
        id: 'activity-1',
        type: 'domain_reset',
        title: 'Domain reset on license #501',
        actor: 'admin@example.com',
        timestamp: dateOffset(-1),
    },
    {
        id: 'activity-2',
        type: 'license_revoked',
        title: 'License #502 revoked',
        actor: 'admin@example.com',
        timestamp: dateOffset(-1),
    },
    {
        id: 'activity-3',
        type: 'token_changed',
        title: 'Integration secrets rotated',
        actor: 'super-admin@example.com',
        timestamp: dateOffset(-3),
    },
];

export const demoDashboardPayload: DashboardPayload = {
    metrics: {
        total_items: demoItems.length,
        total_licenses: demoLicenses.length,
        active_licenses: demoLicenses.filter((license) => license.status === 'active').length,
        revoked_licenses: demoLicenses.filter((license) => license.status === 'revoked').length,
        bound_licenses: demoLicenses.filter((license) => license.bound_domain !== null).length,
        unbound_licenses: demoLicenses.filter((license) => license.bound_domain === null).length,
        checks_today: 141,
        checks_last_7_days: 934,
        failure_rate_percent: 8.3,
    },
    checks_over_time: checksSeries,
    top_failure_reasons: failureReasons,
    top_domains: topDomains,
    recent_activity: recentActivity,
};

export const paginate = <T>(
    items: T[],
    page: number,
    perPage: number,
    path: string,
): PaginatedResponse<T> => {
    const total = items.length;
    const lastPage = Math.max(1, Math.ceil(total / perPage));
    const currentPage = Math.min(Math.max(page, 1), lastPage);
    const start = (currentPage - 1) * perPage;
    const data = items.slice(start, start + perPage);

    return {
        current_page: currentPage,
        data,
        first_page_url: `${path}?page=1`,
        from: data.length > 0 ? start + 1 : null,
        last_page: lastPage,
        last_page_url: `${path}?page=${lastPage}`,
        links: [],
        next_page_url: currentPage < lastPage ? `${path}?page=${currentPage + 1}` : null,
        path,
        per_page: perPage,
        prev_page_url: currentPage > 1 ? `${path}?page=${currentPage - 1}` : null,
        to: data.length > 0 ? start + data.length : null,
        total,
    };
};

export const searchDemoData = (term: string): GlobalSearchGroupedResult => {
    const query = term.toLowerCase();
    const includes = (value: string): boolean => value.toLowerCase().includes(query);

    const purchases = demoPurchases
        .filter((entry) => includes(entry.purchase_code) || includes(entry.buyer) || includes(entry.item_name))
        .slice(0, 5)
        .map((entry) => ({
            id: entry.id,
            title: entry.purchase_code,
            subtitle: `${entry.item_name} • ${entry.buyer}`,
            to: `/admin/purchases/${entry.id}`,
        }));

    const licenses = demoLicenses
        .filter((entry) => includes(entry.purchase_code) || includes(entry.item_name) || includes(String(entry.id)))
        .slice(0, 5)
        .map((entry) => ({
            id: entry.id,
            title: `License #${entry.id}`,
            subtitle: `${entry.purchase_code} • ${entry.status}`,
            to: `/admin/licenses/${entry.id}`,
        }));

    const domains = demoLicenses
        .filter((entry) => entry.bound_domain !== null && includes(entry.bound_domain))
        .slice(0, 5)
        .map((entry) => ({
            id: entry.id,
            title: entry.bound_domain ?? 'Unbound',
            subtitle: `License #${entry.id}`,
            to: `/admin/licenses/${entry.id}`,
        }));

    const items = demoItems
        .filter((entry) => includes(entry.name) || includes(String(entry.envato_item_id)))
        .slice(0, 5)
        .map((entry) => ({
            id: entry.id,
            title: entry.name,
            subtitle: `Item ID ${entry.envato_item_id}`,
            to: `/admin/items/${entry.id}`,
        }));

    return {
        purchases,
        licenses,
        domains,
        items,
    };
};
