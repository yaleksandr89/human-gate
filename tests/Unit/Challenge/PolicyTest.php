<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Unit\Challenge;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\HumanGate\Challenge\Policy;

final class PolicyTest extends TestCase
{
    #[TestDox('Политика использует согласованные значения по умолчанию')]
    public function testDefaults(): void
    {
        $policy = new Policy();

        self::assertSame(180, $policy->ttlSeconds);
        self::assertSame(3, $policy->maxWrongAttempts);
        self::assertSame(12, $policy->maxActive);
        self::assertSame(4, $policy->maxActivePerPurpose);
        self::assertSame(32, $policy->maxTombstones);
        self::assertSame(180, $policy->terminalRetentionSeconds);
    }

    #[DataProvider('invalidPolicies')]
    #[TestDox('Неположительные значения и превышение общего лимита отклоняются')]
    public function testInvalidPolicyIsRejected(int $ttl, int $attempts, int $active, int $perPurpose, int $tombstones, int $retention): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Policy($ttl, $attempts, $active, $perPurpose, $tombstones, $retention);
    }

    /** @return iterable<string, array{int, int, int, int, int, int}> */
    public static function invalidPolicies(): iterable
    {
        foreach ([0, -1] as $invalid) {
            for ($position = 0; $position < 6; ++$position) {
                $values = [180, 3, 12, 4, 32, 180];
                $values[$position] = $invalid;
                yield $position . ':' . $invalid => $values;
            }
        }

        yield 'per-purpose exceeds global' => [180, 3, 2, 3, 32, 180];
    }

    #[TestDox('Минимальные значения и большие целые допустимы без произвольного потолка')]
    public function testPositiveBoundariesAreAccepted(): void
    {
        self::assertSame(1, new Policy(1, 1, 1, 1, 1, 1)->ttlSeconds);
        $policy = new Policy(PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX);
        self::assertSame(PHP_INT_MAX, $policy->terminalRetentionSeconds);
        self::assertSame(PHP_INT_MAX, $policy->maxActivePerPurpose);
    }
}
