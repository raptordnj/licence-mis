<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\License\VerifyLicenseAction;
use App\Data\Domain\LicenseVerificationInputData;
use App\Data\Requests\VerifyLicenseRequestData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\VerifyLicenseRequest;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class LicenseVerificationController extends Controller
{
    public function verify(VerifyLicenseRequest $request, VerifyLicenseAction $action): JsonResponse
    {
        $validated = $request->validated();
        $requestData = new VerifyLicenseRequestData(
            purchaseCode: (string) data_get($validated, 'purchase_code'),
            domain: (string) data_get($validated, 'domain'),
            itemId: data_get($validated, 'item_id') !== null ? (int) data_get($validated, 'item_id') : null,
        );

        $result = $action->execute(new LicenseVerificationInputData(
            purchaseCode: $requestData->purchaseCode,
            domain: $requestData->domain,
            itemId: $requestData->itemId,
        ));

        return ApiResponse::success($result->toArray());
    }
}
