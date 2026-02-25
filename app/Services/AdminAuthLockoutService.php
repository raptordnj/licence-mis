<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

readonly class AdminAuthLockoutService
{
    public function ensureIsNotLocked(string $email, string $ipAddress): void
    {
        $key = $this->key($email, $ipAddress);

        if (! RateLimiter::tooManyAttempts($key, $this->maxAttempts())) {
            return;
        }

        throw new TooManyRequestsHttpException(
            headers: [
                'Retry-After' => $this->availableInSeconds($email, $ipAddress),
            ],
            message: 'Too many login attempts. Please retry later.',
        );
    }

    public function recordFailedAttempt(string $email, string $ipAddress): void
    {
        RateLimiter::hit($this->key($email, $ipAddress), $this->lockoutSeconds());
    }

    public function clearAttempts(string $email, string $ipAddress): void
    {
        RateLimiter::clear($this->key($email, $ipAddress));
    }

    public function availableInSeconds(string $email, string $ipAddress): int
    {
        return RateLimiter::availableIn($this->key($email, $ipAddress));
    }

    private function key(string $email, string $ipAddress): string
    {
        return sprintf('admin-auth:%s|%s', mb_strtolower(trim($email)), trim($ipAddress));
    }

    private function maxAttempts(): int
    {
        return (int) config('services.admin_auth.max_attempts', 5);
    }

    private function lockoutSeconds(): int
    {
        return (int) config('services.admin_auth.lockout_seconds', 900);
    }
}
