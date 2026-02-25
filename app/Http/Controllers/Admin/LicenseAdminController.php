<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\ResetLicenseDomainAction;
use App\Actions\Admin\RevokeLicenseAction;
use App\Enums\LicenseInstanceStatus;
use App\Enums\LicenseType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetLicenseDomainRequest;
use App\Http\Requests\Admin\RevokeLicenseRequest;
use App\Models\AuditLog;
use App\Models\EnvatoItem;
use App\Models\License;
use App\Models\LicenseCheck;
use App\Models\LicenseInstance;
use App\Models\User;
use App\Queries\LicenseIndexQuery;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class LicenseAdminController extends Controller
{
    public function index(Request $request, LicenseIndexQuery $query): JsonResponse
    {
        $this->authorize('viewAny', License::class);

        $licenses = $query->build($request->query())
            ->paginate((int) $request->integer('per_page', 15));

        $itemIds = $licenses->getCollection()
            ->pluck('envato_item_id')
            ->filter(static fn (mixed $value): bool => is_int($value))
            ->values()
            ->all();

        /** @var Collection<int, string> $itemNamesById */
        $itemNamesById = EnvatoItem::query()
            ->whereIn('envato_item_id', $itemIds)
            ->pluck('name', 'envato_item_id');

        /** @var array<int, string> $activeDomainsByLicenseId */
        $activeDomainsByLicenseId = LicenseInstance::query()
            ->whereIn('license_id', $licenses->getCollection()->pluck('id')->all())
            ->where('status', LicenseInstanceStatus::ACTIVE->value)
            ->orderBy('license_id')
            ->orderBy('activated_at')
            ->get(['license_id', 'domain'])
            ->groupBy('license_id')
            ->map(static function (Collection $instances): string {
                /** @var LicenseInstance|null $first */
                $first = $instances->first();

                return $first instanceof LicenseInstance ? $first->domain : '';
            })
            ->filter(static fn (string $domain): bool => $domain !== '')
            ->toArray();

        $mapped = $licenses->getCollection()->map(function (License $license) use ($itemNamesById, $activeDomainsByLicenseId): array {
            $itemName = $license->envato_item_id !== null
                ? ($itemNamesById->get($license->envato_item_id) ?? 'Item '.$license->envato_item_id)
                : 'Unassigned Item';
            $resolvedBoundDomain = $license->bound_domain ?? ($activeDomainsByLicenseId[$license->id] ?? null);

            return $this->mapLicenseSummary(
                license: $license,
                itemName: $itemName,
                resolvedBoundDomain: $resolvedBoundDomain,
            );
        })->values();

        $pagination = new LengthAwarePaginator(
            items: $mapped,
            total: $licenses->total(),
            perPage: $licenses->perPage(),
            currentPage: $licenses->currentPage(),
            options: [
                'path' => $licenses->path(),
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

        $itemName = $license->envato_item_id !== null
            ? (EnvatoItem::query()->where('envato_item_id', $license->envato_item_id)->value('name') ?? 'Item '.$license->envato_item_id)
            : 'Unassigned Item';
        $resolvedBoundDomain = $license->bound_domain ?? $this->resolveFirstActiveInstanceDomain($license);

        $validationLogs = LicenseCheck::query()
            ->whereHas('instance', static function ($query) use ($license): void {
                $query->where('license_id', $license->id);
            })
            ->with('instance:id,license_id,instance_id,domain')
            ->latest('checked_at')
            ->limit(50)
            ->get();

        $auditTrail = AuditLog::query()
            ->where('license_id', $license->id)
            ->with('actor:id,name,email')
            ->latest('id')
            ->limit(50)
            ->get();

        $lastCheckAt = $validationLogs->first()?->checked_at?->toIso8601String()
            ?? $license->instances->first()?->last_seen_at?->toIso8601String()
            ?? $license->verified_at?->toIso8601String();

        return ApiResponse::success([
            ...$this->mapLicenseSummary(
                license: $license,
                itemName: $itemName,
                resolvedBoundDomain: $resolvedBoundDomain,
                lastCheckAt: $lastCheckAt,
            ),
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

    public function revoke(
        RevokeLicenseRequest $request,
        License $license,
        RevokeLicenseAction $action,
    ): JsonResponse {
        $this->authorize('revoke', $license);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $updated = $action->execute($license, $user, $request->string('reason')->toString());

        return ApiResponse::success($updated->toArray());
    }

    public function resetDomain(
        ResetLicenseDomainRequest $request,
        License $license,
        ResetLicenseDomainAction $action,
    ): JsonResponse {
        $this->authorize('resetDomain', $license);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $updated = $action->execute($license, $user, $request->string('reason')->toString());

        return ApiResponse::success($updated->toArray());
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

    private function mapLicenseSummary(
        License $license,
        string $itemName,
        ?string $resolvedBoundDomain,
        ?string $lastCheckAt = null,
    ): array {
        $metadata = is_array($license->metadata) ? $license->metadata : [];

        return [
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
            'last_check_at' => $lastCheckAt ?? $license->verified_at?->toIso8601String(),
            'reset_count' => self::resolveResetCount($metadata),
            'created_at' => $license->created_at?->toIso8601String(),
            'updated_at' => $license->updated_at?->toIso8601String(),
        ];
    }
}
