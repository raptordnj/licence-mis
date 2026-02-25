<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Auth\Access\AuthorizationException;

readonly class SetupTwoFactorAction
{
    public function __construct(private TwoFactorService $twoFactorService)
    {
    }

    /**
     * @return array{secret: string, recovery_codes: list<string>}
     */
    public function execute(User $user): array
    {
        if (! $user->isAdmin()) {
            throw new AuthorizationException('Only admins can setup 2FA.');
        }

        $secret = $this->twoFactorService->generateSecret();
        $recoveryCodes = $this->twoFactorService->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $this->twoFactorService->hashRecoveryCodes($recoveryCodes),
            'two_factor_confirmed_at' => null,
        ])->save();

        return [
            'secret' => $secret,
            'recovery_codes' => $recoveryCodes,
        ];
    }
}
