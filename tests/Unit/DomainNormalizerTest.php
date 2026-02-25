<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\DomainNormalizer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DomainNormalizerTest extends TestCase
{
    public function test_it_normalizes_domain_input(): void
    {
        $normalizer = new DomainNormalizer();

        $result = $normalizer->normalize('https://WWW.Example.com/path?foo=bar');

        $this->assertSame('example.com', (string) $result);
    }

    public function test_it_throws_for_invalid_domain(): void
    {
        $normalizer = new DomainNormalizer();

        $this->expectException(InvalidArgumentException::class);

        $normalizer->normalize('///');
    }
}
