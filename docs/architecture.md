# Architecture

## Layering
- Controllers: transport only (`app/Http/Controllers/...`)
- Form Requests: validation/authorization (`app/Http/Requests/...`)
- Actions: use-case orchestration (`app/Actions/...`)
- Services: reusable domain logic (`app/Services/...`)
- Repositories: persistence abstraction (`app/Repositories/...`)
- Queries: filtered listing (`app/Queries/...`)
- ViewModels: aggregated UI-ready data (`app/ViewModels/...`)
- Data/DTO: request/response/domain transfer objects (`app/Data/...`)
- Enums: status/roles/error code/event types (`app/Enums/...`)

## Main Verification Flow
1. `POST /api/v1/licenses/verify`
2. `VerifyLicenseRequest` validates payload.
3. Controller maps payload to `VerifyLicenseRequestData` and `LicenseVerificationInputData`.
4. `VerifyLicenseAction` orchestrates:
   - domain normalization (`DomainNormalizer`)
   - existing license checks (revoked/domain mismatch/item mismatch)
   - provider verification (`EnvatoPurchaseValidatorInterface` through `EnvatoVerifierInterface`) for first bind
   - persistence via `LicenseRepositoryInterface`
   - response signing via `SignatureService`
5. Controller returns standardized envelope via `ApiResponse`.

## Public License Management API (JWT Signed)
1. `POST /api/licenses/verify` hits `LicenseManagementController@verify`.
2. `VerifyPublicLicenseAction` validates payload and maps to `PublicLicenseVerifyRequestData`.
3. `LicenseManagementService` applies verification rules:
   - product lookup by `product_id` or `envato_item_id`
   - purchase-code lookup in `licenses` scoped by product
   - provider-backed purchase validation (`EnvatoPurchaseValidatorInterface`) when local license is missing
   - mock fixture/prefix/seed evaluation when `LICENSE_ENVATO_MOCK_MODE=true`
   - status gates (`revoked`/`refunded`)
   - activation limit checks via `license_instances`
   - strict domain binding checks
   - instance create/reuse + `last_seen_at` update
4. `LicenseJwtService` signs RS256 JWT containing verification claims.
5. `LicenseCheckLoggerService` persists request/response audits in `license_checks`.

`POST /api/licenses/deactivate` follows the same layered flow through `DeactivatePublicLicenseAction` and marks matching `license_instances` rows inactive.

## Public Update Release Flow
1. `GET /api/updates/manifest` hits `PublicUpdateReleaseController@manifest`.
2. `UpdateReleaseService` resolves target product by `product_id` / `envato_item_id` (with configurable default fallback).
3. Service selects latest published release for the requested channel:
   - product-specific release first
   - then global release (`product_id = null`) fallback
4. Manifest response returns compatibility metadata (`min_version`, `max_version`) plus `download_url` and `checksum`.
5. `GET /api/updates/releases/{id}/download` streams the ZIP package only if release is published and file exists.

## Admin Update Release Flow
1. `POST /api/v1/admin/update-releases` (multipart) uploads a ZIP package.
2. `UpdateReleaseService` stores the package on configured disk and computes SHA-256 checksum.
3. `update_releases` record stores channel, version, compatibility range, notes, publish state, package path, checksum, and size.
4. `PUT /api/v1/admin/update-releases/{id}` can replace package and metadata.
5. `DELETE /api/v1/admin/update-releases/{id}` removes both DB row and package file.
6. Create/update/delete actions are written to audit log events.

## License Management Data Model
- `products`
  - `id`, `envato_item_id`, `name`, `activation_limit`, `status`, `strict_domain_binding`
- `licenses` (extended existing table)
  - adds `product_id`, `buyer`, `notes`
- `license_instances`
  - instance/device registry with activation lifecycle fields
- `license_checks`
  - append-style request/response history for verification and deactivation attempts

## Rate Limits
- `public-license-verify`: keyed by `ip + purchase_code`
- `public-license-deactivate`: keyed by `ip + purchase_code`

Configured in `config/license_manager.php` and registered in `AppServiceProvider`.

## Admin Auth Flow
1. `POST /api/v1/admin/auth/login`
2. `AdminLoginRequest` validates credentials and optional `two_factor_code`/`recovery_code`.
3. Controller maps payload to `AdminLoginRequestData` and `AdminLoginInputData`.
4. `LoginAdminAction` orchestrates:
   - lockout guard (`AdminAuthLockoutService`) by email+IP
   - credential verification with admin-role gate
   - two-factor verification (TOTP or recovery code)
   - recovery code consumption via `TwoFactorService::consumeRecoveryCode`
   - lockout counter clear + Sanctum token issue
5. `GET /api/v1/admin/auth/me` and `POST /api/v1/admin/auth/logout` provide session lifecycle.

## Admin Dashboard Flow
1. `GET /api/v1/admin/dashboard`
2. `AdminDashboardController` enforces admin role and returns API envelope.
3. `AdminDashboardViewModel` aggregates:
   - license metrics by status
   - latest licenses
   - latest audit logs with eager-loaded actor/license
4. Vue SPA (`/admin`) consumes this endpoint through Pinia (`dashboard` store).

## Admin License Management Flow
1. `GET /api/v1/admin/licenses` reads paginated data from `LicenseIndexQuery`.
2. `POST /api/v1/admin/licenses/{license}/revoke` runs `RevokeLicenseAction`.
3. `POST /api/v1/admin/licenses/{license}/reset-domain` runs `ResetLicenseDomainAction`.
4. Both write audit events via `AuditLogService`.

## Admin Audit Log Flow
1. `GET /api/v1/admin/audit-logs`
2. `AdminAuditLogController` authorizes via `AuditLogPolicy`.
3. `AuditLogIndexQuery` handles filters (`search`, `event_type`) and eager loading.
4. Response is paginated and normalized for SPA tables.

## Admin Settings Flow
1. `GET /api/v1/admin/settings` returns non-sensitive config status.
2. `PUT /api/v1/admin/settings` validates payload with `UpdateAdminSettingsRequest`.
3. Controller maps to Spatie Data (`UpdateAdminSettingsRequestData`, `UpdateSensitiveSettingsInputData`).
4. `UpdateSensitiveSettingsAction` persists encrypted values through `SensitiveSettingsStoreInterface`.
5. Token/key updates emit `TOKEN_CHANGED` audit events.

## Admin SPA Shell
- Route group `/admin` uses a shared shell component with:
  - top header navbar
  - left-side navigation
  - nested views for dashboard, items, purchases, licenses, validation logs, admin users, audit logs, settings
- Auth gate is enforced in router guards using the auth Pinia store bootstrap.
- Permission gates are route-aware and hide sidebar/actions by role.
- Global command palette supports keyboard-first navigation (`Ctrl+K` / `Cmd+K`).

## Error Handling
- Centralized rendering in `bootstrap/app.php`.
- Enum-based stable API error codes:
  - `LICENSE_REVOKED`
  - `DOMAIN_MISMATCH`
  - `PURCHASE_INVALID`
  - `RATE_LIMITED`
  - `ENVATO_UNAVAILABLE`
  - `INVALID_CREDENTIALS`
  - `TWO_FACTOR_REQUIRED`
  - `TWO_FACTOR_INVALID`
  - plus auth/validation/internal codes

## Security Foundations
- Sanctum API auth for admin endpoints.
- Role-based policy checks in `LicensePolicy`.
- Admin login lockout controls by email+IP.
- 2FA setup/confirm and recovery-code verification during login.
- Recovery codes are hashed before encrypted persistence.
- Encrypted secret persistence (`Setting` model).
- Audit logging for revoke/reset operations.
- Session secure cookie defaults hardened.

## Performance Foundations
- Indexed `licenses` fields for common lookups.
- Indexed `audit_logs.created_at` for timeline/reporting.
- Caching for provider verification responses.
- Pagination in admin list query.
