<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\UpdateSensitiveSettingsAction;
use App\Data\Domain\UpdateSensitiveSettingsInputData;
use App\Data\Requests\UpdateAdminSettingsRequestData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdminSettingsRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\Contracts\EnvatoVerifierInterface;
use App\Support\Api\ApiResponse;
use App\ViewModels\AdminSettingsViewModel;
use Illuminate\Http\JsonResponse;

class AdminSettingsController extends Controller
{
    private const ENVATO_HEALTHCHECK_PURCHASE_CODE = '00000000-0000-0000-0000-000000000000';

    public function show(AdminSettingsViewModel $viewModel): JsonResponse
    {
        $this->authorize('viewAny', Setting::class);

        return ApiResponse::success($viewModel->toData()->toArray());
    }

    public function update(
        UpdateAdminSettingsRequest $request,
        UpdateSensitiveSettingsAction $action,
        AdminSettingsViewModel $viewModel,
    ): JsonResponse {
        $this->authorize('update', Setting::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $validated = $request->validated();
        $requestData = new UpdateAdminSettingsRequestData(
            envatoApiToken: data_get($validated, 'envato_api_token') !== null
                ? (string) data_get($validated, 'envato_api_token')
                : null,
            licenseHmacKey: data_get($validated, 'license_hmac_key') !== null
                ? (string) data_get($validated, 'license_hmac_key')
                : null,
            envatoMockMode: $request->exists('envato_mock_mode')
                ? (bool) data_get($validated, 'envato_mock_mode')
                : null,
        );

        $action->execute(
            actor: $user,
            input: new UpdateSensitiveSettingsInputData(
                envatoApiToken: $requestData->envatoApiToken,
                licenseHmacKey: $requestData->licenseHmacKey,
                envatoMockMode: $requestData->envatoMockMode,
            ),
        );

        return ApiResponse::success($viewModel->toData()->toArray());
    }

    public function testEnvatoToken(EnvatoVerifierInterface $verifier): JsonResponse
    {
        $this->authorize('viewAny', Setting::class);

        $verifier->verifyPurchaseCode(self::ENVATO_HEALTHCHECK_PURCHASE_CODE);

        return ApiResponse::success([
            'ok' => true,
        ]);
    }
}
