<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\ConfirmTwoFactorAction;
use App\Actions\Admin\SetupTwoFactorAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfirmTwoFactorRequest;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TwoFactorController extends Controller
{
    public function setup(Request $request, SetupTwoFactorAction $action): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        return ApiResponse::success($action->execute($user));
    }

    public function confirm(ConfirmTwoFactorRequest $request, ConfirmTwoFactorAction $action): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $action->execute($user, $request->string('code')->toString());

        return ApiResponse::success([
            'two_factor_enabled' => true,
        ]);
    }
}
