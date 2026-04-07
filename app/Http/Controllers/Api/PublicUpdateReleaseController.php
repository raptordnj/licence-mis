<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Models\UpdateRelease;
use App\Services\UpdateReleaseService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicUpdateReleaseController extends Controller
{
    public function __construct(
        private readonly UpdateReleaseService $updateReleaseService,
    ) {
    }

    public function manifest(Request $request): JsonResponse
    {
        if (! (bool) config('update_releases.enabled', true)) {
            return ApiResponse::error(
                ApiErrorCode::FORBIDDEN,
                'Update release service is currently disabled.',
                403,
            );
        }

        $productId = $request->filled('product_id')
            ? (int) $request->integer('product_id')
            : null;
        $envatoItemId = $request->filled('envato_item_id')
            ? (int) $request->integer('envato_item_id')
            : null;

        $product = $this->updateReleaseService->resolveProduct($productId, $envatoItemId);
        $channel = $this->updateReleaseService->normalizeChannel($request->string('channel')->toString());
        $currentVersion = $request->string('current_version')->toString();

        $manifest = $this->updateReleaseService->buildManifest($product, $channel, $currentVersion);

        return ApiResponse::success($manifest);
    }

    public function download(UpdateRelease $updateRelease): StreamedResponse|JsonResponse
    {
        if (! $updateRelease->is_published) {
            return ApiResponse::error(
                ApiErrorCode::NOT_FOUND,
                'Release package not found.',
                404,
            );
        }

        $diskName = trim((string) config('update_releases.package_disk', 'local'));
        $disk = Storage::disk($diskName !== '' ? $diskName : 'local');
        if (! $disk->exists($updateRelease->package_path)) {
            return ApiResponse::error(
                ApiErrorCode::NOT_FOUND,
                'Release package file is missing.',
                404,
            );
        }

        $filename = sprintf(
            'update-%s-%s.zip',
            str_replace(['/', '\\', ' '], '-', $updateRelease->channel),
            str_replace(['/', '\\', ' '], '-', $updateRelease->version),
        );

        return $disk->download($updateRelease->package_path, $filename, [
            'X-Release-Checksum-Sha256' => $updateRelease->checksum,
        ]);
    }
}
