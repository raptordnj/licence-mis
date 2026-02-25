<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Contracts\SensitiveSettingsStoreInterface;
use App\Services\SignatureService;
use Tests\TestCase;

class SignatureServiceTest extends TestCase
{
    public function test_it_creates_stable_signatures_for_same_payload(): void
    {
        $settingsStore = new class () implements SensitiveSettingsStoreInterface {
            private ?string $hmacKey = null;

            public function saveEnvatoToken(string $token): void
            {
            }

            public function saveHmacKey(string $key): void
            {
                $this->hmacKey = $key;
            }

            public function saveEnvatoMockMode(bool $enabled): void
            {
            }

            public function getEnvatoToken(): ?string
            {
                return null;
            }

            public function getHmacKey(): ?string
            {
                return $this->hmacKey;
            }

            public function getEnvatoMockMode(): ?bool
            {
                return false;
            }

            public function hasEnvatoToken(): bool
            {
                return false;
            }

            public function hasHmacKey(): bool
            {
                return is_string($this->hmacKey) && $this->hmacKey !== '';
            }

            public function isEnvatoMockModeEnabled(): bool
            {
                return false;
            }
        };

        $settingsStore->saveHmacKey('test-hmac-key');

        $service = new SignatureService($settingsStore);

        $first = (string) $service->sign([
            'b' => 2,
            'a' => 1,
            'nested' => ['z' => 9, 'x' => 1],
        ]);

        $second = (string) $service->sign([
            'a' => 1,
            'nested' => ['x' => 1, 'z' => 9],
            'b' => 2,
        ]);

        $this->assertSame($first, $second);
        $this->assertTrue($service->verify(['a' => 1, 'b' => 2, 'nested' => ['x' => 1, 'z' => 9]], $first));
        $this->assertFalse($service->verify(['a' => 1, 'b' => 3], $first));
    }
}
