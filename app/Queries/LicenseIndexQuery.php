<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\License;
use Illuminate\Database\Eloquent\Builder;

readonly class LicenseIndexQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<License>
     */
    public function build(array $filters): Builder
    {
        return License::query()
            ->when(data_get($filters, 'search'), function (Builder $builder, string $search): void {
                $builder->where(function (Builder $subQuery) use ($search): void {
                    $subQuery
                        ->where('purchase_code', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%")
                        ->orWhere('bound_domain', 'like', "%{$search}%")
                        ->orWhereHas('instances', static function (Builder $instanceQuery) use ($search): void {
                            $instanceQuery->where('domain', 'like', "%{$search}%");
                        })
                        ->orWhere('metadata->buyer', 'like', "%{$search}%")
                        ->orWhere('metadata->buyer_username', 'like', "%{$search}%")
                        ->orWhere('metadata->version', 'like', "%{$search}%")
                        ->orWhere('metadata->license_type', 'like', "%{$search}%");
                });
            })
            ->when(data_get($filters, 'status'), function (Builder $builder, string $status): void {
                $builder->where('status', $status);
            })
            ->when(data_get($filters, 'item_id'), function (Builder $builder, int|string $itemId): void {
                $builder->where('envato_item_id', (int) $itemId);
            })
            ->latest('id');
    }
}
