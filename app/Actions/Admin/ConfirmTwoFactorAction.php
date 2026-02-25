<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

readonly class ConfirmTwoFactorAction
{
    public function __construct(private TwoFactorService $twoFactorService)
    {
    }

    public function execute(User $user, string $code): bool
    {
        if (! $user->isAdmin()) {
            throw new AuthorizationException('Only admins can confirm 2FA.');
        }

        if (! is_string($user->two_factor_secret) || $user->two_factor_secret === '') {
            throw ValidationException::withMessages([
                'code' => ['2FA setup has not been started.'],
            ]);
        }

        if (! $this->twoFactorService->verifyCode($user->two_factor_secret, $code)) {
            throw ValidationException::withMessages([
                'code' => ['Invalid 2FA code.'],
            ]);
        }

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();

        return true;
    }
}
