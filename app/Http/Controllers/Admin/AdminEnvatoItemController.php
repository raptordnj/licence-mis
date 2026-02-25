<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EnvatoItemIndexRequest;
use App\Http\Requests\Admin\StoreEnvatoItemRequest;
use App\Http\Requests\Admin\UpdateEnvatoItemRequest;
use App\Models\EnvatoItem;
use App\Queries\EnvatoItemIndexQuery;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminEnvatoItemController extends Controller
{
    public function index(EnvatoItemIndexRequest $request, EnvatoItemIndexQuery $query): JsonResponse
    {
        $this->authorize('viewAny', EnvatoItem::class);

        $items = $query->build($request->validated())
            ->paginate((int) $request->integer('per_page', 10));

        return ApiResponse::success($items->toArray());
    }

    public function store(StoreEnvatoItemRequest $request): JsonResponse
    {
        $this->authorize('create', EnvatoItem::class);

        $validated = $request->validated();
        $item = EnvatoItem::query()->create([
            'marketplace' => (string) data_get($validated, 'marketplace'),
            'envato_item_id' => (int) data_get($validated, 'envato_item_id'),
            'name' => (string) data_get($validated, 'name'),
            'status' => (string) data_get($validated, 'status'),
        ]);

        $item->loadCount('licenses');

        return ApiResponse::success($item->toArray(), 201);
    }

    public function update(UpdateEnvatoItemRequest $request, EnvatoItem $envatoItem): JsonResponse
    {
        $this->authorize('update', $envatoItem);

        $validated = $request->validated();
        $envatoItem->forceFill([
            'marketplace' => (string) data_get($validated, 'marketplace'),
            'envato_item_id' => (int) data_get($validated, 'envato_item_id'),
            'name' => (string) data_get($validated, 'name'),
            'status' => (string) data_get($validated, 'status'),
        ])->save();

        $envatoItem->loadCount('licenses');

        return ApiResponse::success($envatoItem->toArray());
    }
}
