<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\EnvatoItem;
use Illuminate\Database\Eloquent\Builder;

readonly class EnvatoItemIndexQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<EnvatoItem>
     */
    public function build(array $filters): Builder
    {
        return EnvatoItem::query()
            ->withCount('licenses')
            ->when(data_get($filters, 'search'), function (Builder $builder, string $search): void {
                $builder->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('envato_item_id', 'like', "%{$search}%");
                });
            })
            ->when(data_get($filters, 'marketplace'), function (Builder $builder, string $marketplace): void {
                $builder->where('marketplace', $marketplace);
            })
            ->when(data_get($filters, 'status'), function (Builder $builder, string $status): void {
                $builder->where('status', $status);
            })
            ->latest('id');
    }
}
