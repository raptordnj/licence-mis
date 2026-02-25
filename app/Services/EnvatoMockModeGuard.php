<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use Illuminate\Support\Facades\Log;

class EnvatoMockModeGuard
{
    public function assertConfigurationIsSafe(): void
    {
        $this->assertRuntimeStateIsSafe(
            mockModeEnabled: (bool) config('license_manager.envato_mock.mode', false),
            source: 'config',
        );
    }

    public function assertRuntimeStateIsSafe(bool $mockModeEnabled, string $source = 'runtime'): void
    {
        if (! $mockModeEnabled) {
            return;
        }

        $environment = mb_strtolower((string) config('app.env', 'production'));
        $failClosedInProduction = (bool) config('license_manager.envato_mock.fail_closed_in_prod', true);

        if ($environment === 'production' && $failClosedInProduction) {
            Log::critical('Envato purchase mock mode is enabled in production while fail-closed is active.');

            throw new RuntimeException(
                'Mock mode cannot run in production while LICENSE_ENVATO_MOCK_FAIL_CLOSED_IN_PROD is true.',
            );
        }

        Log::warning('Envato purchase mock mode is active in a non-production environment.', [
            'env' => $environment,
            'source' => $source,
            'fixture_path' => (string) config('license_manager.envato_mock.fixture_path'),
            'allowed_prefixes' => config('license_manager.envato_mock.allowed_prefixes', []),
        ]);
    }
}
