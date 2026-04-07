<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\EnvatoItemStatus;
use App\Enums\ApiErrorCode;
use App\Enums\AuditEventType;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUpdateReleaseRequest;
use App\Http\Requests\Admin\UpdateUpdateReleaseRequest;
use App\Models\EnvatoItem;
use App\Models\Product;
use App\Models\UpdateRelease;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\UpdateReleaseService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUpdateReleaseController extends Controller
{
    public function __construct(
        private readonly UpdateReleaseService $updateReleaseService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        if (! $this->isAdminUser($request->user())) {
            return ApiResponse::error(ApiErrorCode::FORBIDDEN, 'Admin access is required.', 403);
        }

        $query = UpdateRelease::query()->with(['product', 'creator'])->latest('id');

        if ($request->filled('product_id')) {
            $query->where('product_id', (int) $request->integer('product_id'));
        } elseif ($request->filled('envato_item_id')) {
            $product = $this->updateReleaseService->resolveProduct(
                null,
                (int) $request->integer('envato_item_id'),
            );

            if ($product instanceof Product) {
                $query->where('product_id', $product->id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($request->filled('channel')) {
            $query->where('channel', $this->updateReleaseService->normalizeChannel($request->string('channel')->toString()));
        }

        if ($request->filled('is_published')) {
            $query->where('is_published', $request->boolean('is_published'));
        }

        $releases = $query->paginate((int) $request->integer('per_page', 20));

        $payload = $releases->toArray();
        $payload['data'] = $releases->getCollection()
            ->map(fn (UpdateRelease $release): array => $this->updateReleaseService->toAdminArray($release))
            ->values()
            ->all();

        return ApiResponse::success($payload);
    }

    public function store(StoreUpdateReleaseRequest $request): JsonResponse
    {
        $actor = $request->user();
        if (! $this->isAdminUser($actor)) {
            return ApiResponse::error(ApiErrorCode::FORBIDDEN, 'Admin access is required.', 403);
        }

        $validated = $request->validated();
        $channel = $this->updateReleaseService->normalizeChannel((string) data_get($validated, 'channel'));
        $version = $this->normalizeReleaseVersion((string) data_get($validated, 'version'));
        $resolvedProduct = $this->resolveProductFromPayload($validated);
        $productId = $resolvedProduct?->id;
        $requestedEnvatoItemId = data_get($validated, 'envato_item_id');
        $minVersion = $this->normalizeNullableVersion(data_get($validated, 'min_version'));
        $maxVersion = $this->normalizeNullableVersion(data_get($validated, 'max_version'));

        if ($requestedEnvatoItemId !== null && ! $resolvedProduct instanceof Product) {
            return ApiResponse::error(
                ApiErrorCode::VALIDATION_ERROR,
                'No product is mapped to the provided envato_item_id.',
                422,
            );
        }

        if ($this->hasInvalidVersionBounds($minVersion, $maxVersion)) {
            return ApiResponse::error(
                ApiErrorCode::VALIDATION_ERROR,
                'Minimum version cannot be greater than maximum version.',
                422,
            );
        }

        if ($this->versionAlreadyExists($productId, $channel, $version)) {
            return ApiResponse::error(
                ApiErrorCode::VALIDATION_ERROR,
                'A release with the same product, channel, and version already exists.',
                422,
            );
        }

        $packageMeta = $this->updateReleaseService->storePackage($request->file('package'));
        $isPublished = (bool) data_get($validated, 'is_published', false);

        $release = UpdateRelease::query()->create([
            'product_id' => $productId,
            'channel' => $channel,
            'version' => $version,
            'min_version' => $minVersion,
            'max_version' => $maxVersion,
            'release_notes' => $this->toNullableString(data_get($validated, 'release_notes')),
            'package_path' => $packageMeta['path'],
            'checksum' => $packageMeta['checksum'],
            'size_bytes' => $packageMeta['size_bytes'],
            'is_published' => $isPublished,
            'published_at' => $isPublished
                ? $this->resolvePublishedAt(data_get($validated, 'published_at'))
                : null,
            'created_by' => $actor?->id,
            'metadata' => is_array(data_get($validated, 'metadata')) ? data_get($validated, 'metadata') : null,
        ])->load(['product', 'creator']);

        $this->auditLogService->log(
            AuditEventType::UPDATE_RELEASE_CREATED,
            $actor instanceof User ? $actor : null,
            null,
            [
                'update_release_id' => $release->id,
                'channel' => $release->channel,
                'version' => $release->version,
                'product_id' => $release->product_id,
            ],
        );

        return ApiResponse::success($this->updateReleaseService->toAdminArray($release), 201);
    }

    public function update(UpdateUpdateReleaseRequest $request, UpdateRelease $updateRelease): JsonResponse
    {
        $actor = $request->user();
        if (! $this->isAdminUser($actor)) {
            return ApiResponse::error(ApiErrorCode::FORBIDDEN, 'Admin access is required.', 403);
        }

        $validated = $request->validated();
        $resolvedProduct = $this->resolveProductFromPayload($validated, $updateRelease->product_id);
        $nextProductId = $resolvedProduct?->id;
        $requestedEnvatoItemId = data_get($validated, 'envato_item_id');
        $nextChannel = array_key_exists('channel', $validated)
            ? $this->updateReleaseService->normalizeChannel((string) data_get($validated, 'channel'))
            : $updateRelease->channel;
        $nextVersion = array_key_exists('version', $validated)
            ? $this->normalizeReleaseVersion((string) data_get($validated, 'version'))
            : $updateRelease->version;
        $nextMinVersion = array_key_exists('min_version', $validated)
            ? $this->normalizeNullableVersion(data_get($validated, 'min_version'))
            : $updateRelease->min_version;
        $nextMaxVersion = array_key_exists('max_version', $validated)
            ? $this->normalizeNullableVersion(data_get($validated, 'max_version'))
            : $updateRelease->max_version;

        if ($requestedEnvatoItemId !== null && ! $resolvedProduct instanceof Product) {
            return ApiResponse::error(
                ApiErrorCode::VALIDATION_ERROR,
                'No product is mapped to the provided envato_item_id.',
                422,
            );
        }

        if ($this->hasInvalidVersionBounds($nextMinVersion, $nextMaxVersion)) {
            return ApiResponse::error(
                ApiErrorCode::VALIDATION_ERROR,
                'Minimum version cannot be greater than maximum version.',
                422,
            );
        }

        if ($this->versionAlreadyExists($nextProductId, $nextChannel, $nextVersion, $updateRelease->id)) {
            return ApiResponse::error(
                ApiErrorCode::VALIDATION_ERROR,
                'A release with the same product, channel, and version already exists.',
                422,
            );
        }

        $payload = [
            'product_id' => $nextProductId,
            'channel' => $nextChannel,
            'version' => $nextVersion,
            'min_version' => $nextMinVersion,
            'max_version' => $nextMaxVersion,
            'release_notes' => array_key_exists('release_notes', $validated)
                ? $this->toNullableString(data_get($validated, 'release_notes'))
                : $updateRelease->release_notes,
            'metadata' => array_key_exists('metadata', $validated)
                ? (is_array(data_get($validated, 'metadata')) ? data_get($validated, 'metadata') : null)
                : $updateRelease->metadata,
        ];

        if ($request->hasFile('package')) {
            $this->updateReleaseService->deletePackage($updateRelease->package_path);
            $packageMeta = $this->updateReleaseService->storePackage($request->file('package'));
            $payload['package_path'] = $packageMeta['path'];
            $payload['checksum'] = $packageMeta['checksum'];
            $payload['size_bytes'] = $packageMeta['size_bytes'];
        }

        if (array_key_exists('is_published', $validated)) {
            $isPublished = (bool) data_get($validated, 'is_published');
            $payload['is_published'] = $isPublished;
            $payload['published_at'] = $isPublished
                ? $this->resolvePublishedAt(data_get($validated, 'published_at') ?? $updateRelease->published_at)
                : null;
        } elseif (array_key_exists('published_at', $validated) && $updateRelease->is_published) {
            $payload['published_at'] = $this->resolvePublishedAt(data_get($validated, 'published_at'));
        }

        $updateRelease->forceFill($payload)->save();
        $updateRelease->load(['product', 'creator']);

        $this->auditLogService->log(
            AuditEventType::UPDATE_RELEASE_UPDATED,
            $actor instanceof User ? $actor : null,
            null,
            [
                'update_release_id' => $updateRelease->id,
                'channel' => $updateRelease->channel,
                'version' => $updateRelease->version,
                'product_id' => $updateRelease->product_id,
            ],
        );

        return ApiResponse::success($this->updateReleaseService->toAdminArray($updateRelease));
    }

    public function destroy(Request $request, UpdateRelease $updateRelease): JsonResponse
    {
        $actor = $request->user();
        if (! $this->isAdminUser($actor)) {
            return ApiResponse::error(ApiErrorCode::FORBIDDEN, 'Admin access is required.', 403);
        }

        $releaseId = $updateRelease->id;
        $version = $updateRelease->version;
        $channel = $updateRelease->channel;
        $productId = $updateRelease->product_id;

        $this->updateReleaseService->deletePackage($updateRelease->package_path);
        $updateRelease->delete();

        $this->auditLogService->log(
            AuditEventType::UPDATE_RELEASE_DELETED,
            $actor instanceof User ? $actor : null,
            null,
            [
                'update_release_id' => $releaseId,
                'channel' => $channel,
                'version' => $version,
                'product_id' => $productId,
            ],
        );

        return ApiResponse::success([
            'deleted' => true,
            'id' => $releaseId,
        ]);
    }

    private function isAdminUser(mixed $user): bool
    {
        return $user instanceof User && $user->isAdmin();
    }

    private function versionAlreadyExists(
        ?int $productId,
        string $channel,
        string $version,
        ?int $ignoreId = null,
    ): bool {
        $query = UpdateRelease::query()
            ->where('channel', $channel)
            ->where('version', $version);

        if ($productId !== null) {
            $query->where('product_id', $productId);
        } else {
            $query->whereNull('product_id');
        }

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveProductFromPayload(array $payload, ?int $fallbackProductId = null): ?Product
    {
        $productId = $fallbackProductId;
        if (array_key_exists('product_id', $payload)) {
            $productId = data_get($payload, 'product_id') !== null
                ? (int) data_get($payload, 'product_id')
                : null;
        }
        $envatoItemId = array_key_exists('envato_item_id', $payload) && data_get($payload, 'envato_item_id') !== null
            ? (int) data_get($payload, 'envato_item_id')
            : null;

        if ($productId !== null && $productId > 0) {
            return Product::query()->find($productId);
        }

        if ($envatoItemId !== null && $envatoItemId > 0) {
            $existing = Product::query()->where('envato_item_id', $envatoItemId)->first();
            if ($existing instanceof Product) {
                return $existing;
            }

            $envatoItem = EnvatoItem::query()->where('envato_item_id', $envatoItemId)->first();
            if (! $envatoItem instanceof EnvatoItem) {
                return null;
            }

            return Product::query()->firstOrCreate(
                ['envato_item_id' => $envatoItemId],
                [
                    'name' => trim($envatoItem->name) !== '' ? $envatoItem->name : 'Item #'.$envatoItemId,
                    'activation_limit' => 1,
                    'status' => $envatoItem->status === EnvatoItemStatus::ACTIVE
                        ? ProductStatus::ACTIVE->value
                        : ProductStatus::INACTIVE->value,
                    'strict_domain_binding' => true,
                ],
            );
        }

        return null;
    }

    private function toNullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeNullableVersion(mixed $value): ?string
    {
        $normalized = $this->toNullableString($value);
        if ($normalized === null) {
            return null;
        }

        return $this->normalizeReleaseVersion($normalized);
    }

    private function normalizeReleaseVersion(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '';
        }

        if (preg_match('/^(\d+)\.(\d+)(?:\.(\d+))?((?:[-+].*)?)$/', $normalized, $matches) !== 1) {
            return $normalized;
        }

        $major = $matches[1];
        $minor = $matches[2];
        $patch = isset($matches[3]) && $matches[3] !== '' ? $matches[3] : '0';
        $suffix = $matches[4] ?? '';

        return "{$major}.{$minor}.{$patch}{$suffix}";
    }

    private function hasInvalidVersionBounds(?string $minVersion, ?string $maxVersion): bool
    {
        if ($minVersion === null || $maxVersion === null) {
            return false;
        }

        return version_compare($minVersion, $maxVersion, '>');
    }

    private function resolvePublishedAt(mixed $value): \Illuminate\Support\Carbon
    {
        if ($value instanceof \Illuminate\Support\Carbon) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            return \Illuminate\Support\Carbon::parse($value);
        }

        return now();
    }
}
