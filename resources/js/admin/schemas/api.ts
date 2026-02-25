import { z } from 'zod';

export const apiErrorSchema = z.object({
    code: z.string(),
    message: z.string(),
});

export const apiEnvelopeSchema = <T extends z.ZodTypeAny>(dataSchema: T) =>
    z.object({
        success: z.boolean(),
        data: dataSchema.nullable(),
        error: apiErrorSchema.nullable(),
    });

export const paginationLinkSchema = z.object({
    url: z.string().nullable(),
    label: z.string(),
    active: z.boolean(),
});

export const paginatedSchema = <T extends z.ZodTypeAny>(itemSchema: T) =>
    z.object({
        current_page: z.number(),
        data: z.array(itemSchema),
        first_page_url: z.string().nullable(),
        from: z.number().nullable(),
        last_page: z.number(),
        last_page_url: z.string().nullable(),
        links: z.array(paginationLinkSchema),
        next_page_url: z.string().nullable(),
        path: z.string(),
        per_page: z.number(),
        prev_page_url: z.string().nullable(),
        to: z.number().nullable(),
        total: z.number(),
    });

export const adminProfileSchema = z.object({
    id: z.number(),
    name: z.string(),
    email: z.string().email(),
    role: z.string(),
    two_factor_enabled: z.boolean(),
    recovery_codes_remaining: z.number(),
});

export const adminLoginResponseSchema = z.object({
    token: z.string(),
    token_type: z.string(),
    admin: z.object({
        id: z.number(),
        name: z.string(),
        email: z.string().email(),
        role: z.string(),
    }),
    two_factor_enabled: z.boolean(),
});

export const dashboardPayloadSchema = z.object({
    metrics: z.object({
        total_items: z.number(),
        total_licenses: z.number(),
        active_licenses: z.number(),
        revoked_licenses: z.number(),
        bound_licenses: z.number(),
        unbound_licenses: z.number(),
        checks_today: z.number(),
        checks_last_7_days: z.number(),
        failure_rate_percent: z.number(),
    }),
    checks_over_time: z.array(
        z.object({
            date: z.string(),
            checks: z.number(),
            failures: z.number(),
        }),
    ),
    top_failure_reasons: z.array(
        z.object({
            reason: z.string(),
            count: z.number(),
        }),
    ),
    top_domains: z.array(
        z.object({
            domain: z.string(),
            checks: z.number(),
            failures: z.number(),
        }),
    ),
    recent_activity: z.array(
        z.object({
            id: z.string(),
            type: z.string(),
            title: z.string(),
            actor: z.string().nullable(),
            timestamp: z.string(),
        }),
    ),
});

export const envatoItemSchema = z.object({
    id: z.number(),
    marketplace: z.string(),
    envato_item_id: z.number(),
    name: z.string(),
    status: z.enum(['active', 'disabled']),
    licenses_count: z.number(),
    created_at: z.string(),
});

export const purchaseSchema = z.object({
    id: z.number(),
    purchase_code: z.string(),
    item_name: z.string(),
    envato_item_id: z.number(),
    buyer: z.string(),
    buyer_username: z.string().nullable().default(null),
    buyer_email: z.string().email().nullable(),
    license_type: z.enum(['regular', 'extended']).default('regular'),
    version: z.string().nullable().default(null),
    purchase_date: z.string(),
    supported_until: z.string().nullable(),
    activated_at: z.string().nullable().default(null),
    status: z.enum(['valid', 'expired', 'revoked', 'unknown']),
    created_at: z.string(),
});

export const licenseSchema = z.object({
    id: z.number(),
    item_name: z.string(),
    envato_item_id: z.number().nullable(),
    purchase_code: z.string(),
    status: z.string(),
    license_type: z.enum(['regular', 'extended']).default('regular'),
    buyer_username: z.string().nullable().default(null),
    version: z.string().nullable().default(null),
    bound_domain: z.string().nullable(),
    bound_domain_original: z.string().nullable(),
    bound_at: z.string().nullable(),
    activated_at: z.string().nullable().default(null),
    last_check_at: z.string().nullable(),
    reset_count: z.number(),
    created_at: z.string(),
    updated_at: z.string(),
});

export const licenseInstanceDetailSchema = z.object({
    id: z.number(),
    instance_id: z.string(),
    domain: z.string(),
    app_url: z.string(),
    status: z.string(),
    ip: z.string().nullable(),
    user_agent: z.string().nullable(),
    last_seen_at: z.string().nullable(),
    activated_at: z.string().nullable(),
    deactivated_at: z.string().nullable(),
    created_at: z.string().nullable(),
    updated_at: z.string().nullable(),
});

export const licenseValidationEntrySchema = z.object({
    id: z.number(),
    time: z.string().nullable(),
    result: z.string(),
    reason: z.string().nullable(),
    instance_id: z.string().nullable(),
    domain: z.string().nullable(),
    ip: z.string().nullable(),
    user_agent: z.string().nullable(),
});

export const licenseAuditEntrySchema = z.object({
    id: z.number(),
    time: z.string().nullable(),
    event: z.string(),
    actor: z
        .object({
            id: z.number(),
            name: z.string(),
            email: z.string(),
        })
        .nullable(),
    reason: z.string().nullable(),
});

export const licenseDetailSchema = licenseSchema.extend({
    instances: z.array(licenseInstanceDetailSchema),
    validation_logs: z.array(licenseValidationEntrySchema),
    audit_trail: z.array(licenseAuditEntrySchema),
});

export const purchaseLinkedLicenseSchema = licenseSchema.extend({
    product_id: z.number().nullable(),
    notes: z.string().nullable(),
    buyer: z.string().nullable(),
    supported_until: z.string().nullable(),
    verified_at: z.string().nullable(),
});

export const purchaseDetailSchema = purchaseSchema.extend({
    updated_at: z.string(),
    marketplace: z.string(),
    metadata: z.record(z.string(), z.unknown()),
    license: purchaseLinkedLicenseSchema.nullable(),
    instances: z.array(licenseInstanceDetailSchema),
    validation_logs: z.array(licenseValidationEntrySchema),
    audit_trail: z.array(licenseAuditEntrySchema),
});

export const validationLogSchema = z.object({
    id: z.string(),
    time: z.string(),
    result: z.enum(['success', 'fail']),
    fail_reason: z.string().nullable(),
    domain_requested: z.string(),
    ip: z.string(),
    user_agent: z.string(),
    purchase_code: z.string(),
    item_name: z.string(),
    correlation_id: z.string(),
    signature_present: z.boolean(),
});

export const auditLogSchema = z.object({
    id: z.number(),
    event_type: z.string(),
    created_at: z.string().nullable(),
    actor: z
        .object({
            id: z.number(),
            name: z.string(),
            email: z.string().email(),
        })
        .nullable(),
    target: z.string().nullable(),
    metadata: z.record(z.string(), z.unknown()).nullable(),
});

export const adminUserSchema = z.object({
    id: z.number(),
    name: z.string(),
    email: z.string().email(),
    role: z.string(),
    two_factor_enabled: z.boolean(),
    last_login_at: z.string().nullable(),
    disabled: z.boolean(),
});

export const globalSearchResultSchema = z.object({
    purchases: z.array(
        z.object({
            id: z.number(),
            title: z.string(),
            subtitle: z.string(),
            to: z.string(),
        }),
    ),
    licenses: z.array(
        z.object({
            id: z.number(),
            title: z.string(),
            subtitle: z.string(),
            to: z.string(),
        }),
    ),
    domains: z.array(
        z.object({
            id: z.number(),
            title: z.string(),
            subtitle: z.string(),
            to: z.string(),
        }),
    ),
    items: z.array(
        z.object({
            id: z.number(),
            title: z.string(),
            subtitle: z.string(),
            to: z.string(),
        }),
    ),
});

export const adminSettingsSchema = z.object({
    has_envato_api_token: z.boolean(),
    has_license_hmac_key: z.boolean(),
    envato_api_base_url: z.string(),
    envato_mock_mode: z.boolean(),
    rate_limit_per_minute: z.number().default(30),
    active_secret_version: z.string().default('v1'),
    domain_policies: z
        .object({
            treat_www_as_same: z.boolean(),
            allow_localhost: z.boolean(),
            allow_ip_domains: z.boolean(),
        })
        .default({
            treat_www_as_same: true,
            allow_localhost: false,
            allow_ip_domains: false,
        }),
    reset_policies: z
        .object({
            max_resets_allowed: z.number(),
        })
        .default({
            max_resets_allowed: 3,
        }),
});

export const twoFactorSetupSchema = z.object({
    secret: z.string(),
    recovery_codes: z.array(z.string()),
});

export const logoutOtherDevicesSchema = z.object({
    revoked_tokens_count: z.number(),
});
