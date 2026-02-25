<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\LicenseStatus;
use App\Models\License;
use Illuminate\Database\Eloquent\Builder;

readonly class PurchaseIndexQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<License>
     */
    public function build(array $filters): Builder
    {
        return License::query()
            ->when(data_get($filters, 'search'), function (Builder $builder, string $search): void {
                $builder->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('purchase_code', 'like', "%{$search}%")
                        ->orWhere('bound_domain', 'like', "%{$search}%")
                        ->orWhere('envato_item_id', 'like', "%{$search}%");
                });
            })
            ->when(data_get($filters, 'item_id'), function (Builder $builder, int|string $itemId): void {
                $builder->where('envato_item_id', (int) $itemId);
            })
            ->when(data_get($filters, 'status'), function (Builder $builder, string $status): void {
                $normalizedStatus = mb_strtolower(trim($status));

                if ($normalizedStatus === 'valid') {
                    $builder->where('status', LicenseStatus::ACTIVE->value);

                    return;
                }

                if ($normalizedStatus === 'revoked') {
                    $builder->where('status', LicenseStatus::REVOKED->value);

                    return;
                }

                if ($normalizedStatus === 'expired') {
                    $builder->where('status', LicenseStatus::EXPIRED->value);
                }
            })
            ->when(data_get($filters, 'buyer'), function (Builder $builder, string $buyer): void {
                $builder->where('metadata->buyer', 'like', "%{$buyer}%");
            })
            ->latest('id');
    }
}
