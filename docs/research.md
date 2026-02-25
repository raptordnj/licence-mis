# Envato API Research Notes

## Scope
Validation strategy for Envato purchase codes in a license verification system.

## Findings
- Envato offers authenticated APIs under `https://api.envato.com/v3/market`.
- Purchase verification is token-driven and must avoid exposing token material to clients.
- External verification latency and availability can fluctuate; caching is required.

## Implementation Decisions
- Introduced `EnvatoPurchaseValidatorInterface` with swappable API/mock validators.
- Added cache layer (`envato:verify:{purchase_code}`) with configurable TTL.
- Added `LICENSE_ENVATO_MOCK_*` controls for deterministic local/test fixture and seed validation.
- Added `EnvatoUnavailableException` to map provider failures to stable API errors.

## Risk Controls
- Do not leak provider token in errors or logs.
- Encrypted storage service for token material (`settings` table with encrypted cast).
- Endpoint rate limiting by IP + purchase code + item ID.

## Future Expansion
- Support multi-marketplace verification providers behind shared contract.
- Add async fallback queue/job for transient provider outages.
- Persist verification snapshots for audit/reporting.
