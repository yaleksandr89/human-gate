<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Unit\Challenge;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\HumanGate\Challenge\ChallengeId;

final class ChallengeIdTest extends TestCase
{
    #[TestDox('Созданный идентификатор имеет канонический формат')]
    public function testGeneratedIdHasCanonicalRepresentation(): void
    {
        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', ChallengeId::generate()->value());
    }

    #[TestDox('Канонический идентификатор восстанавливается без изменений')]
    public function testCanonicalStringPreservesValue(): void
    {
        $value = str_repeat('0123456789abcdef', 4);

        self::assertSame($value, ChallengeId::fromString($value)->value());
    }

    #[DataProvider('invalidValues')]
    #[TestDox('Неканонический идентификатор отклоняется')]
    public function testInvalidValueIsRejected(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        ChallengeId::fromString($value);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidValues(): iterable
    {
        yield 'empty' => [''];
        yield 'too short' => [str_repeat('a', 63)];
        yield 'too long' => [str_repeat('a', 65)];
        yield 'uppercase hex' => [str_repeat('A', 64)];
        yield 'non-hex character' => [str_repeat('a', 63) . 'g'];
        yield 'embedded space' => [str_repeat('a', 31) . ' ' . str_repeat('a', 32)];
        yield 'trailing newline' => [str_repeat('a', 64) . "\n"];
        yield 'Unicode lookalike' => [str_repeat('a', 63) . 'а'];
    }
}
