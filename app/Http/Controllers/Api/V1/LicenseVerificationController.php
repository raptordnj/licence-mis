<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\License\VerifyLicenseAction;
use App\Data\Domain\LicenseVerificationInputData;
use App\Data\Requests\VerifyLicenseRequestData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\VerifyLicenseRequest;
use App\Models\Product;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class LicenseVerificationController extends Controller
{
    public function verify(VerifyLicenseRequest $request, VerifyLicenseAction $action): JsonResponse
    {
        $validated = $request->validated();
        $productId = data_get($validated, 'product_id') !== null ? (int) data_get($validated, 'product_id') : null;

        $resolvedItemId = data_get($validated, 'item_id') !== null
            ? (int) data_get($validated, 'item_id')
            : $this->resolveItemIdFromProductId($productId);

        $requestData = new VerifyLicenseRequestData(
            purchaseCode: (string) data_get($validated, 'purchase_code'),
            domain: (string) data_get($validated, 'domain'),
            itemId: $resolvedItemId,
            productId: $productId,
        );

        $result = $action->execute(new LicenseVerificationInputData(
            purchaseCode: $requestData->purchaseCode,
            domain: $requestData->domain,
            itemId: $requestData->itemId,
            productId: $requestData->productId,
        ));

        return ApiResponse::success($result->toArray());
    }

    private function resolveItemIdFromProductId(?int $productId): ?int
    {
        if (! is_int($productId) || $productId <= 0) {
            return null;
        }

        $envatoItemId = Product::query()
            ->whereKey($productId)
            ->value('envato_item_id');

        if (! is_numeric($envatoItemId)) {
            return null;
        }

        return (int) $envatoItemId;
    }
}
