<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

readonly class AdminUserIndexQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<User>
     */
    public function build(array $filters): Builder
    {
        return User::query()
            ->select('users.*')
            ->addSelect([
                'last_login_at' => DB::table('personal_access_tokens')
                    ->selectRaw('MAX(last_used_at)')
                    ->whereColumn('tokenable_id', 'users.id')
                    ->where('tokenable_type', User::class),
            ])
            ->when(data_get($filters, 'search'), function (Builder $builder, string $search): void {
                $builder->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(data_get($filters, 'role'), function (Builder $builder, string $role): void {
                $builder->where('role', $role);
            })
            ->latest('id');
    }
}
