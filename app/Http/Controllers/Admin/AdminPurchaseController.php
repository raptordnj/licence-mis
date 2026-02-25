<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\LicenseInstanceStatus;
use App\Enums\LicenseType;
use App\Enums\LicenseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PurchaseIndexRequest;
use App\Models\AuditLog;
use App\Models\EnvatoItem;
use App\Models\License;
use App\Models\LicenseCheck;
use App\Models\LicenseInstance;
use App\Queries\PurchaseIndexQuery;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AdminPurchaseController extends Controller
{
    public function index(PurchaseIndexRequest $request, PurchaseIndexQuery $query): JsonResponse
    {
        $this->authorize('viewAny', License::class);

        $purchases = $query->build($request->validated())
            ->paginate((int) $request->integer('per_page', 15));

        $itemIds = $purchases->getCollection()
            ->pluck('envato_item_id')
            ->filter(static fn (mixed $value): bool => is_int($value))
            ->values()
            ->all();

        /** @var Collection<int, string> $itemNamesById */
        $itemNamesById = EnvatoItem::query()
            ->whereIn('envato_item_id', $itemIds)
            ->pluck('name', 'envato_item_id');

        $mapped = $purchases->getCollection()->map(function (License $license) use ($itemNamesById): array {
            $metadata = is_array($license->metadata) ? $license->metadata : [];

            $itemName = $license->envato_item_id !== null
                ? ($itemNamesById->get($license->envato_item_id) ?? 'Item '.$license->envato_item_id)
                : 'Unknown Item';

            return [
                'id' => $license->id,
                'purchase_code' => $license->purchase_code,
                'item_name' => $itemName,
                'envato_item_id' => $license->envato_item_id,
                'buyer' => is_string(data_get($metadata, 'buyer')) ? (string) data_get($metadata, 'buyer') : 'unknown',
                'buyer_username' => self::resolveBuyerUsername($metadata),
                'buyer_email' => is_string(data_get($metadata, 'buyer_email'))
                    ? (string) data_get($metadata, 'buyer_email')
                    : null,
                'license_type' => self::resolveLicenseType($metadata)->value,
                'version' => self::resolveVersion($metadata),
                'purchase_date' => $license->created_at?->toIso8601String(),
                'supported_until' => $license->supported_until?->toIso8601String(),
                'activated_at' => $license->verified_at?->toIso8601String(),
                'status' => $this->resolvePurchaseStatus($license->status),
                'created_at' => $license->created_at?->toIso8601String(),
            ];
        })->values();

        $pagination = new LengthAwarePaginator(
            items: $mapped,
            total: $purchases->total(),
            perPage: $purchases->perPage(),
            currentPage: $purchases->currentPage(),
            options: [
                'path' => $purchases->path(),
                'query' => $request->query(),
            ],
        );

        return ApiResponse::success($pagination->toArray());
    }

    public function show(License $license): JsonResponse
    {
        $this->authorize('viewAny', License::class);

        $license->loadMissing([
            'instances' => static function ($query): void {
                $query
                    ->orderByDesc('last_seen_at')
                    ->orderByDesc('id');
            },
        ]);

        $metadata = is_array($license->metadata) ? $license->metadata : [];
        $itemName = $license->envato_item_id !== null
            ? (EnvatoItem::query()->where('envato_item_id', $license->envato_item_id)->value('name') ?? 'Item '.$license->envato_item_id)
            : 'Unknown Item';
        $resolvedBoundDomain = $license->bound_domain ?? $this->resolveFirstActiveInstanceDomain($license);

        $validationLogs = LicenseCheck::query()
            ->whereHas('instance', static function ($query) use ($license): void {
                $query->where('license_id', $license->id);
            })
            ->with('instance:id,license_id,instance_id,domain')
            ->latest('checked_at')
            ->limit(100)
            ->get();

        $auditTrail = AuditLog::query()
            ->where('license_id', $license->id)
            ->with('actor:id,name,email')
            ->latest('id')
            ->limit(100)
            ->get();

        $lastCheckAt = $validationLogs->first()?->checked_at?->toIso8601String()
            ?? $license->instances->first()?->last_seen_at?->toIso8601String()
            ?? $license->verified_at?->toIso8601String();

        return ApiResponse::success([
            'id' => $license->id,
            'purchase_code' => $license->purchase_code,
            'item_name' => $itemName,
            'envato_item_id' => $license->envato_item_id,
            'buyer' => is_string(data_get($metadata, 'buyer'))
                ? (string) data_get($metadata, 'buyer')
                : ($license->buyer ?? 'unknown'),
            'buyer_username' => self::resolveBuyerUsername($metadata),
            'buyer_email' => is_string(data_get($metadata, 'buyer_email'))
                ? (string) data_get($metadata, 'buyer_email')
                : null,
            'license_type' => self::resolveLicenseType($metadata)->value,
            'version' => self::resolveVersion($metadata),
            'purchase_date' => $license->created_at?->toIso8601String(),
            'supported_until' => $license->supported_until?->toIso8601String(),
            'activated_at' => $license->verified_at?->toIso8601String(),
            'status' => $this->resolvePurchaseStatus($license->status),
            'created_at' => $license->created_at?->toIso8601String(),
            'updated_at' => $license->updated_at?->toIso8601String(),
            'marketplace' => $license->marketplace->value,
            'metadata' => $metadata,
            'license' => [
                'id' => $license->id,
                'item_name' => $itemName,
                'envato_item_id' => $license->envato_item_id,
                'purchase_code' => $license->purchase_code,
                'status' => $license->status->value,
                'license_type' => self::resolveLicenseType($metadata)->value,
                'buyer_username' => self::resolveBuyerUsername($metadata),
                'version' => self::resolveVersion($metadata),
                'bound_domain' => $resolvedBoundDomain,
                'bound_domain_original' => self::resolveBoundDomainOriginal($metadata, $resolvedBoundDomain),
                'bound_at' => $license->verified_at?->toIso8601String(),
                'activated_at' => $license->verified_at?->toIso8601String(),
                'last_check_at' => $lastCheckAt,
                'reset_count' => self::resolveResetCount($metadata),
                'created_at' => $license->created_at?->toIso8601String(),
                'updated_at' => $license->updated_at?->toIso8601String(),
                'product_id' => $license->product_id,
                'notes' => $license->notes,
                'buyer' => $license->buyer,
                'supported_until' => $license->supported_until?->toIso8601String(),
                'verified_at' => $license->verified_at?->toIso8601String(),
            ],
            'instances' => $license->instances->map(static function (LicenseInstance $instance): array {
                return [
                    'id' => $instance->id,
                    'instance_id' => $instance->instance_id,
                    'domain' => $instance->domain,
                    'app_url' => $instance->app_url,
                    'status' => $instance->status->value,
                    'ip' => $instance->ip,
                    'user_agent' => $instance->user_agent,
                    'last_seen_at' => $instance->last_seen_at?->toIso8601String(),
                    'activated_at' => $instance->activated_at?->toIso8601String(),
                    'deactivated_at' => $instance->deactivated_at?->toIso8601String(),
                    'created_at' => $instance->created_at?->toIso8601String(),
                    'updated_at' => $instance->updated_at?->toIso8601String(),
                ];
            })->values()->all(),
            'validation_logs' => $validationLogs->map(static function (LicenseCheck $log): array {
                return [
                    'id' => $log->id,
                    'time' => $log->checked_at?->toIso8601String(),
                    'result' => $log->result->value,
                    'reason' => $log->reason,
                    'instance_id' => $log->instance?->instance_id,
                    'domain' => $log->instance?->domain,
                    'ip' => is_string(data_get($log->request_payload, 'ip'))
                        ? (string) data_get($log->request_payload, 'ip')
                        : null,
                    'user_agent' => is_string(data_get($log->request_payload, 'user_agent'))
                        ? (string) data_get($log->request_payload, 'user_agent')
                        : null,
                ];
            })->values()->all(),
            'audit_trail' => $auditTrail->map(static function (AuditLog $entry): array {
                return [
                    'id' => $entry->id,
                    'time' => $entry->created_at?->toIso8601String(),
                    'event' => $entry->event_type->value,
                    'actor' => $entry->actor !== null ? [
                        'id' => $entry->actor->id,
                        'name' => $entry->actor->name,
                        'email' => $entry->actor->email,
                    ] : null,
                    'reason' => is_string(data_get($entry->metadata, 'reason'))
                        ? (string) data_get($entry->metadata, 'reason')
                        : null,
                ];
            })->values()->all(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private static function resolveLicenseType(array $metadata): LicenseType
    {
        $raw = mb_strtolower(trim((string) data_get($metadata, 'license_type', 'regular')));

        if (str_contains($raw, 'extended')) {
            return LicenseType::EXTENDED;
        }

        return LicenseType::REGULAR;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private static function resolveBuyerUsername(array $metadata): ?string
    {
        $buyerUsername = data_get($metadata, 'buyer_username');

        if (is_string($buyerUsername) && trim($buyerUsername) !== '') {
            return $buyerUsername;
        }

        $buyer = data_get($metadata, 'buyer');

        if (is_string($buyer) && trim($buyer) !== '') {
            return $buyer;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private static function resolveVersion(array $metadata): ?string
    {
        $version = data_get($metadata, 'version');

        if (is_string($version) && trim($version) !== '') {
            return $version;
        }

        return null;
    }

    private function resolvePurchaseStatus(LicenseStatus $licenseStatus): string
    {
        if ($licenseStatus === LicenseStatus::ACTIVE || $licenseStatus === LicenseStatus::VALID) {
            return 'valid';
        }

        if ($licenseStatus === LicenseStatus::REVOKED) {
            return 'revoked';
        }

        if ($licenseStatus === LicenseStatus::EXPIRED) {
            return 'expired';
        }

        return 'unknown';
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private static function resolveBoundDomainOriginal(array $metadata, ?string $fallback): ?string
    {
        $boundDomainOriginal = data_get($metadata, 'bound_domain_original');

        if (is_string($boundDomainOriginal) && trim($boundDomainOriginal) !== '') {
            return $boundDomainOriginal;
        }

        return $fallback;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private static function resolveResetCount(array $metadata): int
    {
        $resetCount = data_get($metadata, 'reset_count');

        if (is_int($resetCount)) {
            return $resetCount;
        }

        if (is_numeric($resetCount)) {
            return (int) $resetCount;
        }

        return 0;
    }

    private function resolveFirstActiveInstanceDomain(License $license): ?string
    {
        /** @var LicenseInstance|null $firstActiveInstance */
        $firstActiveInstance = $license->instances
            ->first(static fn (LicenseInstance $instance): bool => $instance->status === LicenseInstanceStatus::ACTIVE);

        return $firstActiveInstance?->domain;
    }
}
