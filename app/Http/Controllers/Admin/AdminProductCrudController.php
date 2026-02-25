<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Product;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProductCrudController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $this->isAdminUser($request->user())) {
            return ApiResponse::error(ApiErrorCode::FORBIDDEN, 'Admin access is required.', 403);
        }

        $products = Product::query()
            ->latest('id')
            ->paginate((int) $request->integer('per_page', 20));

        return ApiResponse::success($products->toArray());
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        if (! $this->isAdminUser($request->user())) {
            return ApiResponse::error(ApiErrorCode::FORBIDDEN, 'Admin access is required.', 403);
        }

        $validated = $request->validated();

        $product = Product::query()->create([
            'envato_item_id' => (int) data_get($validated, 'envato_item_id'),
            'name' => (string) data_get($validated, 'name'),
            'activation_limit' => (int) data_get($validated, 'activation_limit'),
            'status' => (string) data_get($validated, 'status'),
            'strict_domain_binding' => (bool) data_get($validated, 'strict_domain_binding', true),
        ]);

        return ApiResponse::success($product->toArray(), 201);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        if (! $this->isAdminUser($request->user())) {
            return ApiResponse::error(ApiErrorCode::FORBIDDEN, 'Admin access is required.', 403);
        }

        $validated = $request->validated();

        $product->forceFill([
            'envato_item_id' => (int) data_get($validated, 'envato_item_id'),
            'name' => (string) data_get($validated, 'name'),
            'activation_limit' => (int) data_get($validated, 'activation_limit'),
            'status' => (string) data_get($validated, 'status'),
            'strict_domain_binding' => (bool) data_get($validated, 'strict_domain_binding'),
        ])->save();

        return ApiResponse::success($product->refresh()->toArray());
    }

    private function isAdminUser(mixed $user): bool
    {
        return $user instanceof User && $user->isAdmin();
    }
}
