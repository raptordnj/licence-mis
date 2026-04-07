# API

Base paths:
- `/api` (public license management API)
- `/api/v1` (existing legacy + admin API)

## Response Envelope
Success:
```json
{
  "success": true,
  "data": {},
  "error": null
}
```

Error:
```json
{
  "success": false,
  "data": null,
  "error": {
    "code": "DOMAIN_MISMATCH",
    "message": "License is bound to another domain."
  }
}
```

## Public License Management API

### `POST /api/licenses/verify`
Request:
```json
{
  "purchase_code": "PCODE-VALID-001",
  "product_id": 1,
  "instance_id": "57f6c8fd-03a3-4a35-a3c7-5bd3f8fd086f",
  "domain": "www.example.com",
  "app_url": "https://www.example.com/install",
  "app_version": "4.7.11",
  "signature_proof": "optional"
}
```

Alternative product identifier:
```json
{
  "envato_item_id": 1001
}
```

Valid response (always signed):
```json
{
  "status": "valid",
  "reason": null,
  "valid_until": 1771942200,
  "issued_at": 1771938600,
  "instance_id": "57f6c8fd-03a3-4a35-a3c7-5bd3f8fd086f",
  "domain": "example.com",
  "product_id": 1,
  "token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

Invalid response example:
```json
{
  "status": "invalid",
  "reason": "limit_reached",
  "valid_until": 1771938900,
  "issued_at": 1771938600,
  "instance_id": "57f6c8fd-03a3-4a35-a3c7-5bd3f8fd086f",
  "domain": "example.com",
  "product_id": 1,
  "token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

Possible invalid reasons:
- `revoked`
- `refund`
- `limit_reached`
- `domain_mismatch`
- `not_found`
- `bad_request`

### `POST /api/licenses/deactivate`
Request:
```json
{
  "purchase_code": "PCODE-VALID-001",
  "product_id": 1,
  "instance_id": "57f6c8fd-03a3-4a35-a3c7-5bd3f8fd086f"
}
```

Response:
```json
{
  "success": true,
  "reason": "deactivated",
  "issued_at": 1771938600,
  "valid_until": 1771942200,
  "instance_id": "57f6c8fd-03a3-4a35-a3c7-5bd3f8fd086f",
  "product_id": 1,
  "token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

## Public Update Release API

### `GET /api/updates/manifest`
Returns the latest published release manifest for a channel.

Query params:
- `channel` (optional, default: `stable`)
- `current_version` (optional, semantic version string)
- `product_id` (optional)
- `envato_item_id` (optional)

Response envelope:
```json
{
  "success": true,
  "data": {
    "channel": "stable",
    "version": "1.2.0",
    "download_url": "https://licence-mis.local/api/updates/releases/7/download",
    "checksum": "a2d2d6...",
    "min_version": "1.0.0",
    "max_version": "2.0.0",
    "release_notes": "Bug fixes and security improvements.",
    "published_at": "2026-03-07T10:00:00+00:00",
    "is_available": true,
    "compatible": true
  },
  "error": null
}
```

### `GET /api/updates/releases/{updateRelease}/download`
Downloads the ZIP package for a published release.

Notes:
- Returns a binary file response.
- Returns `404` envelope when release is unpublished or file is missing.

## Admin Update Release Endpoints
Auth: `auth:sanctum` + admin role

### `GET /api/v1/admin/update-releases`
Query params: `product_id`, `channel`, `is_published`, `per_page`, `page`

### `POST /api/v1/admin/update-releases`
Multipart payload:
- `package` (required `.zip`)
- `product_id` (optional, null for global release)
- `channel` (required)
- `version` (required)
- `min_version` (optional)
- `max_version` (optional)
- `release_notes` (optional)
- `is_published` (optional)
- `published_at` (optional)
- `metadata` (optional object)

### `PUT /api/v1/admin/update-releases/{updateRelease}`
Multipart/JSON payload, same keys as create but all optional.

### `DELETE /api/v1/admin/update-releases/{updateRelease}`
Deletes release record and stored package file.

### Key Management
Generate RSA keys:
```bash
mkdir -p storage/app/keys
openssl genrsa -out storage/app/keys/license-private.pem 2048
openssl rsa -in storage/app/keys/license-private.pem -pubout -out storage/app/keys/license-public.pem
chmod 600 storage/app/keys/license-private.pem
```

Environment:
```env
LICENSE_JWT_PRIVATE_KEY=storage/app/keys/license-private.pem
LICENSE_JWT_PUBLIC_KEY=storage/app/keys/license-public.pem
LICENSE_JWT_KEY_ID=license-v1
LICENSE_JWT_ISSUER=${APP_URL}
```

## Admin Authentication Endpoints
### `POST /admin/auth/login`
Request:
```json
{
  "email": "admin@example.com",
  "password": "password",
  "two_factor_code": "123456",
  "recovery_code": "RECOVERYCODE"
}
```

Notes:
- `two_factor_code` and `recovery_code` are optional unless 2FA is enabled.
- Lockout applies by email+IP after configured failed attempts.

### `GET /admin/auth/me`
Auth: `auth:sanctum`

### `POST /admin/auth/logout`
Auth: `auth:sanctum`

### `POST /admin/auth/logout-other-devices`
Auth: `auth:sanctum`
Returns:
```json
{
  "revoked_tokens_count": 1
}
```

## Admin License Endpoints
Auth: `auth:sanctum`

### `GET /admin/dashboard`
Returns:
- Aggregated metrics (`total_licenses`, `active_licenses`, `revoked_licenses`, `expired_licenses`)
- `recent_licenses` (latest 10)
- `recent_audit_logs` (latest 10 with actor/license)

### `GET /admin/licenses`
Query params: `search`, `status`, `item_id`, `per_page`

### `POST /admin/licenses/{license}/revoke`
Request:
```json
{
  "reason": "string|null"
}
```

### `POST /admin/licenses/{license}/reset-domain`
Request:
```json
{
  "reason": "string|null"
}
```

### `POST /admin/licenses/{license}/reset-activations`
Use this when a license gets stuck on `limit_reached` or old instance bindings.
Request:
```json
{
  "reason": "string|null"
}
```

## Admin Product & Managed License CRUD (Optional)
Auth: `auth:sanctum`

### `GET /admin/products`
Query params: `search`, `status`, `per_page`, `page`

### `POST /admin/products`
Request:
```json
{
  "envato_item_id": 1001,
  "name": "FleetCart - Modern eCommerce CMS",
  "activation_limit": 3,
  "status": "active",
  "strict_domain_binding": true
}
```

### `PUT /admin/products/{product}`
Request:
```json
{
  "name": "FleetCart Updated Name",
  "activation_limit": 2,
  "status": "disabled",
  "strict_domain_binding": false
}
```

### `GET /admin/managed-licenses`
Query params: `search`, `status`, `product_id`, `per_page`, `page`

### `POST /admin/managed-licenses`
Request:
```json
{
  "product_id": 1,
  "purchase_code": "PCODE-VALID-001",
  "buyer": "buyer_username",
  "status": "valid",
  "notes": "Imported from Envato",
  "bound_domain": "example.com"
}
```

### `PATCH /admin/managed-licenses/{license}/status`
Request:
```json
{
  "status": "revoked",
  "notes": "Chargeback detected"
}
```
Allowed status values:
- `valid`
- `invalid`
- `revoked`
- `refunded`
- `chargeback`

## Admin Audit Endpoint
Auth: `auth:sanctum`

### `GET /admin/audit-logs`
Query params: `search`, `event_type`, `per_page`, `page`

Returns paginated entries including:
- `event_type`
- `actor` (`id`, `name`, `email`) when available
- `license` (`id`, `purchase_code`) when available
- `metadata` map

## Admin Settings Endpoints
Auth: `auth:sanctum`

### `GET /admin/settings`
Returns:
```json
{
  "has_envato_api_token": true,
  "has_license_hmac_key": true,
  "envato_api_base_url": "https://api.envato.com/v3",
  "envato_mock_mode": false
}
```

### `PUT /admin/settings`
Request:
```json
{
  "envato_api_token": "optional-string",
  "license_hmac_key": "optional-string",
  "envato_mock_mode": false
}
```
Notes:
- At least one field is required.
- `envato_mock_mode` toggles local mock validation mode at runtime.
- Updated secrets are encrypted at rest.

## Admin 2FA Endpoints
Auth: `auth:sanctum`

### `POST /admin/2fa/setup`
Returns generated TOTP secret + plain recovery codes.

### `POST /admin/2fa/confirm`
Request:
```json
{
  "code": "123456"
}
```

## Error Codes
- `LICENSE_REVOKED`
- `DOMAIN_MISMATCH`
- `PURCHASE_INVALID`
- `RATE_LIMITED`
- `ENVATO_UNAVAILABLE`
- `INVALID_CREDENTIALS`
- `TWO_FACTOR_REQUIRED`
- `TWO_FACTOR_INVALID`
- `VALIDATION_ERROR`
- `UNAUTHORIZED`
- `FORBIDDEN`
- `NOT_FOUND`
- `INTERNAL_ERROR`
