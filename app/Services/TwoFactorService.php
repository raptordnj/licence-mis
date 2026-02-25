<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

readonly class TwoFactorService
{
    public function __construct(private Google2FA $google2FA)
    {
    }

    public function generateSecret(): string
    {
        return $this->google2FA->generateSecretKey();
    }

    public function verifyCode(string $secret, string $oneTimeCode): bool
    {
        return (bool) $this->google2FA->verifyKey($secret, $oneTimeCode);
    }

    /**
     * @return list<string>
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];

        for ($index = 0; $index < $count; $index++) {
            $codes[] = strtoupper(bin2hex(random_bytes(5)));
        }

        return $codes;
    }

    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    public function hashRecoveryCodes(array $codes): array
    {
        return array_map(
            fn (string $code): string => hash('sha256', $this->normalizeRecoveryCode($code)),
            $codes,
        );
    }

    public function consumeRecoveryCode(User $user, string $recoveryCode): bool
    {
        $storedCodes = $user->two_factor_recovery_codes;

        if (! is_array($storedCodes) || $storedCodes === []) {
            return false;
        }

        $normalized = $this->normalizeRecoveryCode($recoveryCode);

        if ($normalized === '') {
            return false;
        }

        $candidateHash = hash('sha256', $normalized);
        $remainingCodes = [];
        $matched = false;

        foreach ($storedCodes as $storedCodeHash) {
            if (! is_string($storedCodeHash)) {
                continue;
            }

            if (! $matched && hash_equals($storedCodeHash, $candidateHash)) {
                $matched = true;
                continue;
            }

            $remainingCodes[] = $storedCodeHash;
        }

        if (! $matched) {
            return false;
        }

        $user->forceFill([
            'two_factor_recovery_codes' => $remainingCodes,
        ])->save();

        return true;
    }

    public function normalizeRecoveryCode(string $recoveryCode): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $recoveryCode) ?? '');
    }
}
