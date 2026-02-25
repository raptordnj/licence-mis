export type ApiErrorCode =
    | 'LICENSE_REVOKED'
    | 'DOMAIN_MISMATCH'
    | 'PURCHASE_INVALID'
    | 'RATE_LIMITED'
    | 'ENVATO_UNAVAILABLE'
    | 'VALIDATION_ERROR'
    | 'UNAUTHORIZED'
    | 'INVALID_CREDENTIALS'
    | 'TWO_FACTOR_REQUIRED'
    | 'TWO_FACTOR_INVALID'
    | 'FORBIDDEN'
    | 'NOT_FOUND'
    | 'INTERNAL_ERROR'
    | 'UNKNOWN';

export interface ApiError {
    code: ApiErrorCode | string;
    message: string;
}

export interface ApiEnvelope<T> {
    success: boolean;
    data: T | null;
    error: ApiError | null;
}

export type RoleName = 'super-admin' | 'admin' | 'support';

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginatedResponse<T> {
    current_page: number;
    data: T[];
    first_page_url: string | null;
    from: number | null;
    last_page: number;
    last_page_url: string | null;
    links: PaginationLink[];
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
}

export interface AdminProfile {
    id: number;
    name: string;
    email: string;
    role: RoleName | string;
    two_factor_enabled: boolean;
    recovery_codes_remaining: number;
}

export interface AdminLoginRequest {
    email: string;
    password: string;
    remember_me?: boolean;
    two_factor_code?: string;
    recovery_code?: string;
}

export interface AdminLoginResponse {
    token: string;
    token_type: string;
    admin: {
        id: number;
        name: string;
        email: string;
        role: RoleName | string;
    };
    two_factor_enabled: boolean;
}

export interface TwoFactorChallengeState {
    email: string;
    password: string;
    remember_me: boolean;
}

export interface TwoFactorSetupPayload {
    secret: string;
    recovery_codes: string[];
}

export interface TwoFactorConfirmRequest {
    code: string;
}

export interface DashboardMetrics {
    total_items: number;
    total_licenses: number;
    active_licenses: number;
    revoked_licenses: number;
    bound_licenses: number;
    unbound_licenses: number;
    checks_today: number;
    checks_last_7_days: number;
    failure_rate_percent: number;
}

export interface DashboardSeriesPoint {
    date: string;
    checks: number;
    failures: number;
}

export interface DashboardFailureReason {
    reason: string;
    count: number;
}

export interface DashboardTopDomain {
    domain: string;
    checks: number;
    failures: number;
}

export interface DashboardActivityItem {
    id: string;
    type: string;
    title: string;
    actor: string | null;
    timestamp: string;
}

export interface DashboardPayload {
    metrics: DashboardMetrics;
    checks_over_time: DashboardSeriesPoint[];
    top_failure_reasons: DashboardFailureReason[];
    top_domains: DashboardTopDomain[];
    recent_activity: DashboardActivityItem[];
}

export interface EnvatoItem {
    id: number;
    marketplace: string;
    envato_item_id: number;
    name: string;
    status: 'active' | 'disabled';
    licenses_count: number;
    created_at: string;
}

export interface Purchase {
    id: number;
    purchase_code: string;
    item_name: string;
    envato_item_id: number;
    buyer: string;
    buyer_username: string | null;
    buyer_email: string | null;
    license_type: 'regular' | 'extended' | string;
    version: string | null;
    purchase_date: string;
    supported_until: string | null;
    activated_at: string | null;
    status: 'valid' | 'expired' | 'revoked' | 'unknown';
    created_at: string;
}

export interface PurchaseLinkedLicense extends License {
    product_id: number | null;
    notes: string | null;
    buyer: string | null;
    supported_until: string | null;
    verified_at: string | null;
}

export interface PurchaseDetail extends Purchase {
    updated_at: string;
    marketplace: string;
    metadata: Record<string, unknown>;
    license: PurchaseLinkedLicense | null;
    instances: LicenseInstanceDetail[];
    validation_logs: LicenseValidationEntry[];
    audit_trail: LicenseAuditEntry[];
}

export interface License {
    id: number;
    item_name: string;
    envato_item_id: number | null;
    purchase_code: string;
    status: 'active' | 'revoked' | 'expired' | string;
    license_type: 'regular' | 'extended' | string;
    buyer_username: string | null;
    version: string | null;
    bound_domain: string | null;
    bound_domain_original: string | null;
    bound_at: string | null;
    activated_at: string | null;
    last_check_at: string | null;
    reset_count: number;
    created_at: string;
    updated_at: string;
}

export interface LicenseInstanceDetail {
    id: number;
    instance_id: string;
    domain: string;
    app_url: string;
    status: string;
    ip: string | null;
    user_agent: string | null;
    last_seen_at: string | null;
    activated_at: string | null;
    deactivated_at: string | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface LicenseValidationEntry {
    id: number;
    time: string | null;
    result: string;
    reason: string | null;
    instance_id: string | null;
    domain: string | null;
    ip: string | null;
    user_agent: string | null;
}

export interface LicenseAuditEntry {
    id: number;
    time: string | null;
    event: string;
    actor: {
        id: number;
        name: string;
        email: string;
    } | null;
    reason: string | null;
}

export interface LicenseDetail extends License {
    instances: LicenseInstanceDetail[];
    validation_logs: LicenseValidationEntry[];
    audit_trail: LicenseAuditEntry[];
}

export interface ValidationLog {
    id: string;
    time: string;
    result: 'success' | 'fail';
    fail_reason: string | null;
    domain_requested: string;
    ip: string;
    user_agent: string;
    purchase_code: string;
    item_name: string;
    correlation_id: string;
    signature_present: boolean;
}

export interface AuditLog {
    id: number;
    event_type: string;
    created_at: string | null;
    actor: {
        id: number;
        name: string;
        email: string;
    } | null;
    target: string | null;
    metadata: Record<string, unknown> | null;
}

export interface AdminUser {
    id: number;
    name: string;
    email: string;
    role: RoleName | string;
    two_factor_enabled: boolean;
    last_login_at: string | null;
    disabled: boolean;
}

export interface GlobalSearchGroupedResult {
    purchases: Array<{ id: number; title: string; subtitle: string; to: string }>;
    licenses: Array<{ id: number; title: string; subtitle: string; to: string }>;
    domains: Array<{ id: number; title: string; subtitle: string; to: string }>;
    items: Array<{ id: number; title: string; subtitle: string; to: string }>;
}

export interface AdminSettingsPayload {
    has_envato_api_token: boolean;
    has_license_hmac_key: boolean;
    envato_api_base_url: string;
    envato_mock_mode: boolean;
    rate_limit_per_minute: number;
    active_secret_version: string;
    domain_policies: {
        treat_www_as_same: boolean;
        allow_localhost: boolean;
        allow_ip_domains: boolean;
    };
    reset_policies: {
        max_resets_allowed: number;
    };
}

export interface UpdateAdminSettingsRequest {
    envato_api_token?: string;
    license_hmac_key?: string;
    envato_mock_mode?: boolean;
    rate_limit_per_minute?: number;
    domain_policies?: {
        treat_www_as_same: boolean;
        allow_localhost: boolean;
        allow_ip_domains: boolean;
    };
    reset_policies?: {
        max_resets_allowed: number;
    };
}

export interface LogoutOtherDevicesResponse {
    revoked_tokens_count: number;
}

export interface DataListQuery {
    page: number;
    perPage: number;
    search: string;
    sortBy: string;
    sortDir: 'asc' | 'desc';
}
