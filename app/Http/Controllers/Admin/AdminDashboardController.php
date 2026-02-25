<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Api\ApiResponse;
use App\ViewModels\AdminDashboardViewModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function show(Request $request, AdminDashboardViewModel $viewModel): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isAdmin()) {
            return ApiResponse::error(ApiErrorCode::FORBIDDEN, 'Admin access is required.', 403);
        }

        return ApiResponse::success($viewModel->toArray());
    }
}
