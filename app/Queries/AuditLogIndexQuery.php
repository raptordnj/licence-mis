<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;

readonly class AuditLogIndexQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<AuditLog>
     */
    public function build(array $filters): Builder
    {
        return AuditLog::query()
            ->with([
                'actor:id,name,email',
                'license:id,purchase_code',
            ])
            ->when(data_get($filters, 'event_type'), function (Builder $builder, string $eventType): void {
                $builder->where('event_type', $eventType);
            })
            ->when(data_get($filters, 'search'), function (Builder $builder, string $search): void {
                $builder->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('event_type', 'like', "%{$search}%")
                        ->orWhereHas('actor', function (Builder $actorQuery) use ($search): void {
                            $actorQuery
                                ->where('email', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('license', function (Builder $licenseQuery) use ($search): void {
                            $licenseQuery->where('purchase_code', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('id');
    }
}
