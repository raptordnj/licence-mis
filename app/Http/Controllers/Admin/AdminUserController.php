<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\CreateAdminUserAction;
use App\Actions\Admin\UpdateAdminUserRoleAction;
use App\Data\Domain\CreateAdminUserInputData;
use App\Data\Domain\UpdateAdminUserRoleInputData;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminUserIndexRequest;
use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Http\Requests\Admin\UpdateAdminUserRoleRequest;
use App\Models\User;
use App\Queries\AdminUserIndexQuery;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class AdminUserController extends Controller
{
    public function index(AdminUserIndexRequest $request, AdminUserIndexQuery $query): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $users = $query->build($request->validated())
            ->paginate((int) $request->integer('per_page', 15));

        $mapped = $users->getCollection()->map(fn (User $user): array => $this->mapUser($user))->values();

        $pagination = new LengthAwarePaginator(
            items: $mapped,
            total: $users->total(),
            perPage: $users->perPage(),
            currentPage: $users->currentPage(),
            options: [
                'path' => $users->path(),
                'query' => $request->query(),
            ],
        );

        return ApiResponse::success($pagination->toArray());
    }

    public function store(
        StoreAdminUserRequest $request,
        CreateAdminUserAction $action,
    ): JsonResponse {
        $this->authorize('create', User::class);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        $validated = $request->validated();

        $created = $action->execute(
            actor: $actor,
            input: new CreateAdminUserInputData(
                name: (string) data_get($validated, 'name'),
                email: (string) data_get($validated, 'email'),
                role: RoleName::from((string) data_get($validated, 'role')),
                password: data_get($validated, 'password') !== null
                    ? (string) data_get($validated, 'password')
                    : null,
            ),
        );

        return ApiResponse::success(['id' => $created->id], 201);
    }

    public function updateRole(
        UpdateAdminUserRoleRequest $request,
        User $user,
        UpdateAdminUserRoleAction $action,
    ): JsonResponse {
        $this->authorize('updateRole', $user);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        $validated = $request->validated();

        $updated = $action->execute(
            actor: $actor,
            target: $user,
            input: new UpdateAdminUserRoleInputData(
                role: RoleName::from((string) data_get($validated, 'role')),
            ),
        );

        return ApiResponse::success(['id' => $updated->id]);
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     email: string,
     *     role: string,
     *     two_factor_enabled: bool,
     *     last_login_at: string|null,
     *     disabled: bool
     * }
     */
    private function mapUser(User $user): array
    {
        $lastLoginRaw = $user->getAttribute('last_login_at');
        $lastLoginAt = null;

        if (is_string($lastLoginRaw) && $lastLoginRaw !== '') {
            $lastLoginAt = Carbon::parse($lastLoginRaw)->toIso8601String();
        } elseif ($lastLoginRaw instanceof Carbon) {
            $lastLoginAt = $lastLoginRaw->toIso8601String();
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'two_factor_enabled' => $user->hasTwoFactorEnabled(),
            'last_login_at' => $lastLoginAt,
            'disabled' => false,
        ];
    }
}
