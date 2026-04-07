# Licence MIS

License verification service focused on Envato purchase validation, domain binding, and admin governance.

## Stack
- PHP 8.3+
- Laravel 12
- Laravel Sanctum
- Spatie Laravel Data
- PHPUnit
- Laravel Pint
- PHPStan + Larastan
- Vite + Vue 3 + TypeScript strict + ESLint + Prettier + `vue-tsc`
- Pinia + Vue Router + Zod + Lucide Icons

## Setup
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
```

## Admin SPA
- URL: `/admin` (login route is `/admin/login`)
- Post-login routes:
  - `/admin/dashboard`
  - `/admin/items`
  - `/admin/purchases`
  - `/admin/licenses`
  - `/admin/validation-logs`
  - `/admin/admin-users`
  - `/admin/audit-logs`
  - `/admin/settings`
  - `/admin/design-system`
- Global command palette: `Ctrl+K` / `Cmd+K`
- Seeded admin login:
  - Email: `admin@example.com`
  - Password: `password`

## Quality Commands
```bash
composer lint
composer analyse
composer test
npm run lint
npm run type-check
npm run format
```

## Required Environment Variables
- `ENVATO_API_BASE_URL`
- `ENVATO_API_TOKEN`
- `ENVATO_CACHE_TTL_SECONDS`
- `LICENSE_ENVATO_MOCK_MODE`
- `LICENSE_ENVATO_MOCK_ALLOWED_PREFIXES`
- `LICENSE_ENVATO_MOCK_SEED` (optional)
- `LICENSE_ENVATO_MOCK_FIXTURE_PATH`
- `LICENSE_ENVATO_MOCK_ALLOWED_ITEM_IDS` (optional)
- `LICENSE_ENVATO_MOCK_ALLOWED_PRODUCT_IDS` (optional)
- `LICENSE_ENVATO_MOCK_FAIL_CLOSED_IN_PROD`
- `LICENSE_HMAC_KEY`
- `LICENSE_VERIFY_RATE_LIMIT`
- `LICENSE_JWT_PRIVATE_KEY`
- `LICENSE_JWT_PUBLIC_KEY`
- `LICENSE_JWT_KEY_ID`
- `LICENSE_JWT_ISSUER`
- `LICENSE_TOKEN_TTL_SECONDS`
- `LICENSE_INVALID_TOKEN_TTL_SECONDS`
- `LICENSE_PUBLIC_VERIFY_RATE_LIMIT`
- `LICENSE_PUBLIC_DEACTIVATE_RATE_LIMIT`
- `LICENSE_NORMALIZE_WWW`
- `UPDATE_RELEASES_ENABLED`
- `UPDATE_RELEASES_DEFAULT_CHANNEL`
- `UPDATE_RELEASES_DEFAULT_PRODUCT_ID`
- `UPDATE_RELEASES_PACKAGE_DISK`
- `UPDATE_RELEASES_PACKAGE_DIRECTORY`
- `UPDATE_RELEASES_MAX_PACKAGE_SIZE_MB`
- `UPDATE_RELEASES_MANIFEST_RATE_LIMIT`
- `UPDATE_RELEASES_DOWNLOAD_RATE_LIMIT`
- `ADMIN_AUTH_MAX_ATTEMPTS`
- `ADMIN_AUTH_LOCKOUT_SECONDS`
- `SANCTUM_STATEFUL_DOMAINS`
- `SESSION_SECURE_COOKIE`

## Envato Mock Mode (Local/QA/Demo)
Enable deterministic local purchase validation without calling Envato:

```env
LICENSE_ENVATO_MOCK_MODE=true
LICENSE_ENVATO_MOCK_ALLOWED_PREFIXES=MOCK-,TEST-
LICENSE_ENVATO_MOCK_SEED=local-seed-optional
LICENSE_ENVATO_MOCK_FIXTURE_PATH=storage/app/envato-mock/fixtures.json
LICENSE_ENVATO_MOCK_ALLOWED_ITEM_IDS=
LICENSE_ENVATO_MOCK_ALLOWED_PRODUCT_IDS=
LICENSE_ENVATO_MOCK_FAIL_CLOSED_IN_PROD=true
```

Fixture file example (`storage/app/envato-mock/fixtures.json`):

```json
{
  "fixtures": [
    {
      "purchase_code": "MOCK-FIXTURE-VALID-001",
      "valid": true,
      "buyer": "qa-buyer",
      "supported_envato_item_id_list": [1001],
      "product_ids": [1],
      "supported_until": "2030-12-31T00:00:00+00:00"
    },
    {
      "purchase_code": "MOCK-FIXTURE-REFUND-001",
      "valid": false,
      "reason": "refund"
    }
  ]
}
```

Troubleshooting:
- If a code is unexpectedly invalid, check prefix list, fixture path, and fixture JSON syntax.
- If `LICENSE_ENVATO_MOCK_SEED` is empty, unknown non-prefix/non-fixture codes resolve to `not_found`.
- In production with `LICENSE_ENVATO_MOCK_FAIL_CLOSED_IN_PROD=true`, app boot fails when mock mode is enabled.
- Admins can toggle runtime mode via `PUT /api/v1/admin/settings` with `{ \"envato_mock_mode\": true|false }`.

## RSA Key Generation (JWT RS256)
```bash
mkdir -p storage/app/keys
openssl genrsa -out storage/app/keys/license-private.pem 2048
openssl rsa -in storage/app/keys/license-private.pem -pubout -out storage/app/keys/license-public.pem
chmod 600 storage/app/keys/license-private.pem
```

## API Quick Start
```bash
curl -X POST http://localhost:8000/api/licenses/verify \
  -H "Content-Type: application/json" \
  -d '{
    "purchase_code": "PCODE-VALID-001",
    "product_id": 1,
    "instance_id": "57f6c8fd-03a3-4a35-a3c7-5bd3f8fd086f",
    "domain": "example.com",
    "app_url": "https://example.com",
    "app_version": "4.7.11"
  }'
```

```bash
curl -X POST http://localhost:8000/api/licenses/deactivate \
  -H "Content-Type: application/json" \
  -d '{
    "purchase_code": "PCODE-VALID-001",
    "product_id": 1,
    "instance_id": "57f6c8fd-03a3-4a35-a3c7-5bd3f8fd086f"
  }'
```

```bash
curl -X POST http://localhost:8000/api/v1/licenses/verify \
  -H "Content-Type: application/json" \
  -d '{
    "purchase_code": "valid-example-code",
    "domain": "example.com",
    "item_id": 1000
  }'
```

```bash
curl -X POST http://localhost:8000/api/v1/admin/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "password"
  }'
```

## Customer Integration Snippet
```php
<?php

declare(strict_types=1);

$payload = [
    'purchase_code' => 'YOUR_PURCHASE_CODE',
    'domain' => $_SERVER['HTTP_HOST'] ?? 'localhost',
    'item_id' => 1000,
];

$ch = curl_init('https://your-licence-host.com/api/v1/licenses/verify');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
    CURLOPT_TIMEOUT => 10,
]);

$response = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($statusCode !== 200) {
    throw new RuntimeException('License verification failed: '.$response);
}
```

## Deployment Notes
- Run DB migrations before traffic switch.
- Configure production `SESSION_SECURE_COOKIE=true` and HTTPS termination.
- Keep `LICENSE_ENVATO_MOCK_MODE=false` in production.
- Rotate `LICENSE_HMAC_KEY` and Envato token via secure secret storage.
- Queue workers should run for background verification or sync jobs when enabled.

## Documentation
- `docs/research.md`
- `docs/architecture.md`
- `docs/api.md`
- `docs/compliance-audit.md`
