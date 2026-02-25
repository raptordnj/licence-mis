<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Data\Domain\AdminLoginInputData;
use App\Data\Responses\AdminLoginResponseData;
use App\Exceptions\InvalidCredentialsException;
use App\Exceptions\TwoFactorInvalidException;
use App\Exceptions\TwoFactorRequiredException;
use App\Models\User;
use App\Services\AdminAuthLockoutService;
use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Hash;

readonly class LoginAdminAction
{
    public function __construct(
        private AdminAuthLockoutService $lockoutService,
        private TwoFactorService $twoFactorService,
    ) {
    }

    public function execute(AdminLoginInputData $input): AdminLoginResponseData
    {
        $this->lockoutService->ensureIsNotLocked($input->email, $input->ipAddress);

        $admin = User::query()->where('email', $input->email)->first();

        if (! $admin instanceof User || ! $admin->isAdmin() || ! Hash::check($input->password, $admin->password)) {
            $this->lockoutService->recordFailedAttempt($input->email, $input->ipAddress);

            throw new InvalidCredentialsException();
        }

        if ($admin->hasTwoFactorEnabled()) {
            $this->assertTwoFactorValid($admin, $input);
        }

        $this->lockoutService->clearAttempts($input->email, $input->ipAddress);

        $token = $admin->createToken('admin-api')->plainTextToken;

        return new AdminLoginResponseData(
            token: $token,
            tokenType: 'Bearer',
            admin: [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->role->value,
            ],
            twoFactorEnabled: $admin->hasTwoFactorEnabled(),
        );
    }

    private function assertTwoFactorValid(User $admin, AdminLoginInputData $input): void
    {
        if ($input->recoveryCode !== null && $input->recoveryCode !== '') {
            $consumed = $this->twoFactorService->consumeRecoveryCode($admin, $input->recoveryCode);

            if ($consumed) {
                return;
            }

            $this->lockoutService->recordFailedAttempt($input->email, $input->ipAddress);

            throw new TwoFactorInvalidException('Invalid recovery code.');
        }

        if ($input->twoFactorCode === null || $input->twoFactorCode === '') {
            $this->lockoutService->recordFailedAttempt($input->email, $input->ipAddress);

            throw new TwoFactorRequiredException();
        }

        if (! is_string($admin->two_factor_secret) || $admin->two_factor_secret === '') {
            $this->lockoutService->recordFailedAttempt($input->email, $input->ipAddress);

            throw new TwoFactorInvalidException();
        }

        if ($this->twoFactorService->verifyCode($admin->two_factor_secret, $input->twoFactorCode)) {
            return;
        }

        $this->lockoutService->recordFailedAttempt($input->email, $input->ipAddress);

        throw new TwoFactorInvalidException('Invalid two-factor code.');
    }
}
