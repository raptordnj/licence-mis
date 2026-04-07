<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\LicenseManagement\AutoIssueLicenseAction;
use App\Actions\LicenseManagement\DeactivatePublicLicenseAction;
use App\Actions\LicenseManagement\VerifyPublicLicenseAction;
use App\Http\Controllers\Controller;
use App\Services\LicenseManagementService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class LicenseManagementController extends Controller
{
    public function verify(Request $request, VerifyPublicLicenseAction $action): JsonResponse
    {
        $response = $action->execute(
            payload: $request->all(),
            ipAddress: (string) $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json($response->toArray());
    }

    public function autoIssue(Request $request, AutoIssueLicenseAction $action): JsonResponse
    {
        $response = $action->execute(
            payload: $request->all(),
            ipAddress: (string) $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json($response->toArray());
    }

    public function deactivate(Request $request, DeactivatePublicLicenseAction $action): JsonResponse
    {
        $response = $action->execute(
            payload: $request->all(),
            ipAddress: (string) $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json($response->toArray());
    }

    public function domainValidity(Request $request, LicenseManagementService $licenseManagementService): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'domain' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::success([
                'valid' => false,
                'domain' => null,
                'reason' => 'bad_request',
            ]);
        }

        $domain = (string) data_get($validator->validated(), 'domain');

        try {
            $validation = $licenseManagementService->resolveApiDomainValidity($domain);
        } catch (InvalidArgumentException) {
            return ApiResponse::success([
                'valid' => false,
                'domain' => null,
                'reason' => 'invalid_domain',
            ]);
        }

        return ApiResponse::success([
            'valid' => $validation['valid'],
            'domain' => $validation['domain'],
            'reason' => $validation['valid'] ? null : 'not_licensed',
        ]);
    }
}
