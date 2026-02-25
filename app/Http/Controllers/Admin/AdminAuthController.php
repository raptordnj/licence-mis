<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\LoginAdminAction;
use App\Data\Domain\AdminLoginInputData;
use App\Data\Requests\AdminLoginRequestData;
use App\Enums\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuthController extends Controller
{
    public function login(AdminLoginRequest $request, LoginAdminAction $action): JsonResponse
    {
        $validated = $request->validated();

        $requestData = new AdminLoginRequestData(
            email: (string) data_get($validated, 'email'),
            password: (string) data_get($validated, 'password'),
            twoFactorCode: data_get($validated, 'two_factor_code') !== null
                ? (string) data_get($validated, 'two_factor_code')
                : null,
            recoveryCode: data_get($validated, 'recovery_code') !== null
                ? (string) data_get($validated, 'recovery_code')
                : null,
        );

        $responseData = $action->execute(new AdminLoginInputData(
            email: $requestData->email,
            password: $requestData->password,
            ipAddress: (string) $request->ip(),
            twoFactorCode: $requestData->twoFactorCode,
            recoveryCode: $requestData->recoveryCode,
        ));

        return ApiResponse::success([
            'token' => $responseData->token,
            'token_type' => $responseData->tokenType,
            'admin' => $responseData->admin,
            'two_factor_enabled' => $responseData->twoFactorEnabled,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isAdmin()) {
            return ApiResponse::error(ApiErrorCode::FORBIDDEN, 'Admin access is required.', 403);
        }

        return ApiResponse::success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'two_factor_enabled' => $user->hasTwoFactorEnabled(),
            'recovery_codes_remaining' => is_array($user->two_factor_recovery_codes)
                ? count($user->two_factor_recovery_codes)
                : 0,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $currentToken = $user->currentAccessToken();

        if ($currentToken !== null) {
            $currentToken->delete();
        }

        return ApiResponse::success([
            'logged_out' => true,
        ]);
    }

    public function logoutOtherDevices(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isAdmin()) {
            return ApiResponse::error(ApiErrorCode::FORBIDDEN, 'Admin access is required.', 403);
        }

        $currentToken = $user->currentAccessToken();
        $tokens = $user->tokens();

        if ($currentToken !== null) {
            $tokens->whereKeyNot($currentToken->id);
        }

        $revokedTokensCount = $tokens->delete();

        return ApiResponse::success([
            'revoked_tokens_count' => $revokedTokensCount,
        ]);
    }
}
