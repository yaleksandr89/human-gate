<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Unit\Internal\CategorySelection;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\HumanGate\Internal\CategorySelection\CategorySelectionAnswer;

#[TestDox('Ответ является ограниченным набором уникальных lowercase hex токенов')]
final class CategorySelectionAnswerTest extends TestCase
{
    public function testCanonicalizationIgnoresOrderAndUsesStringOrdering(): void
    {
        $tokens = [str_repeat('a', 32), str_repeat('2', 32), str_repeat('1', 32)];
        $expected = implode(',', array_reverse($tokens));
        self::assertSame($expected, CategorySelectionAnswer::canonicalize($tokens));
        self::assertSame($expected, CategorySelectionAnswer::normalize(implode(',', $tokens)));
        self::assertSame($expected, CategorySelectionAnswer::normalize($expected));
        self::assertSame(str_repeat('f', 32), CategorySelectionAnswer::normalize(str_repeat('f', 32)));
    }

    public function testTokenCountAndInputLengthBounds(): void
    {
        $tokens = array_map(static fn(int $index): string => str_pad(dechex($index), 32, '0', STR_PAD_LEFT), range(0, 11));
        $raw = implode(',', $tokens);
        self::assertSame(395, strlen($raw));
        self::assertSame($raw, CategorySelectionAnswer::normalize($raw));
        self::assertNull(CategorySelectionAnswer::normalize($raw . ',' . str_repeat('f', 32)));
        self::assertNull(CategorySelectionAnswer::normalize(str_repeat('0', 512)));
        self::assertNull(CategorySelectionAnswer::normalize(str_repeat('0', 513)));
    }

    public function testMalformedTokensAreRejectedWithoutTrimming(): void
    {
        $token = str_repeat('a', 32);
        foreach ([
            '',
            $token . ',' . $token,
            ',' . $token,
            $token . ',',
            $token . ',,' . str_repeat('b', 32),
            str_repeat('a', 31),
            str_repeat('a', 33),
            strtoupper($token),
            str_repeat('g', 32),
            str_repeat('а', 32),
            "\xff" . $token,
            $token . ';' . str_repeat('b', 32),
        ] as $raw) {
            self::assertNull(CategorySelectionAnswer::normalize($raw), bin2hex($raw));
        }
        foreach ([" ", "\t", "\r", "\n", "\0", "\xc2\xa0"] as $space) {
            self::assertNull(CategorySelectionAnswer::normalize($space . $token));
            self::assertNull(CategorySelectionAnswer::normalize($token . $space));
            self::assertNull(CategorySelectionAnswer::normalize($token . ',' . $space . str_repeat('b', 32)));
        }
    }

    public function testInvalidGeneratedTokensFailExplicitly(): void
    {
        foreach ([[], [1 => str_repeat('a', 32)], ['bad'], [str_repeat('a', 32), str_repeat('a', 32)]] as $tokens) {
            try {
                CategorySelectionAnswer::canonicalize($tokens);
                self::fail('Invalid generated answer was accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Invalid category selection answer tokens.', $exception->getMessage());
            }
        }
    }
}
