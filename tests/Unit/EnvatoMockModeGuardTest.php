<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\EnvatoMockModeGuard;
use RuntimeException;
use Tests\TestCase;

class EnvatoMockModeGuardTest extends TestCase
{
    public function test_mock_mode_is_blocked_in_production_when_fail_closed_is_enabled(): void
    {
        config()->set('app.env', 'production');
        config()->set('license_manager.envato_mock.mode', true);
        config()->set('license_manager.envato_mock.fail_closed_in_prod', true);

        $guard = new EnvatoMockModeGuard();

        $this->expectException(RuntimeException::class);
        $guard->assertConfigurationIsSafe();
    }
}
