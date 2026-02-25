<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\LicenseCheckResult;
use App\Models\LicenseCheck;
use Illuminate\Database\Eloquent\Builder;

readonly class ValidationLogIndexQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<LicenseCheck>
     */
    public function build(array $filters): Builder
    {
        return LicenseCheck::query()
            ->with([
                'instance:id,license_id,instance_id,domain,ip,user_agent',
                'instance.license:id,purchase_code,bound_domain,envato_item_id,metadata',
            ])
            ->when(data_get($filters, 'result'), function (Builder $builder, string $result): void {
                if ($result === 'success') {
                    $builder->where('result', LicenseCheckResult::VALID->value);
                }

                if ($result === 'fail') {
                    $builder->where('result', LicenseCheckResult::INVALID->value);
                }
            })
            ->when(data_get($filters, 'fail_reason'), function (Builder $builder, string $reason): void {
                $normalizedReason = mb_strtolower(trim($reason));

                if ($normalizedReason === '') {
                    return;
                }

                $builder->where('reason', 'like', "%{$normalizedReason}%");
            })
            ->when(data_get($filters, 'item'), function (Builder $builder, string $item): void {
                $builder->where(function (Builder $query) use ($item): void {
                    $query
                        ->where('request_payload->item_name', 'like', "%{$item}%")
                        ->orWhere('request_payload->envato_item_id', 'like', "%{$item}%")
                        ->orWhereHas('instance.license', function (Builder $licenseQuery) use ($item): void {
                            $licenseQuery->where('envato_item_id', 'like', "%{$item}%");
                        });
                });
            })
            ->when(data_get($filters, 'purchase_code'), function (Builder $builder, string $purchaseCode): void {
                $builder->where(function (Builder $query) use ($purchaseCode): void {
                    $query
                        ->where('request_payload->purchase_code', 'like', "%{$purchaseCode}%")
                        ->orWhereHas('instance.license', function (Builder $licenseQuery) use ($purchaseCode): void {
                            $licenseQuery->where('purchase_code', 'like', "%{$purchaseCode}%");
                        });
                });
            })
            ->when(data_get($filters, 'domain'), function (Builder $builder, string $domain): void {
                $builder->where(function (Builder $query) use ($domain): void {
                    $query
                        ->where('request_payload->domain', 'like', "%{$domain}%")
                        ->orWhere('request_payload->domain_requested', 'like', "%{$domain}%")
                        ->orWhereHas('instance', function (Builder $instanceQuery) use ($domain): void {
                            $instanceQuery->where('domain', 'like', "%{$domain}%");
                        })
                        ->orWhereHas('instance.license', function (Builder $licenseQuery) use ($domain): void {
                            $licenseQuery->where('bound_domain', 'like', "%{$domain}%");
                        });
                });
            })
            ->when(data_get($filters, 'ip'), function (Builder $builder, string $ip): void {
                $builder->where('request_payload->ip', 'like', "%{$ip}%");
            })
            ->when(data_get($filters, 'from'), function (Builder $builder, string $from): void {
                $builder->whereDate('checked_at', '>=', $from);
            })
            ->when(data_get($filters, 'to'), function (Builder $builder, string $to): void {
                $builder->whereDate('checked_at', '<=', $to);
            })
            ->latest('id');
    }
}
