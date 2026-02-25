<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\LicenseManagement\AutoIssueLicenseAction;
use App\Actions\LicenseManagement\DeactivatePublicLicenseAction;
use App\Actions\LicenseManagement\VerifyPublicLicenseAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
