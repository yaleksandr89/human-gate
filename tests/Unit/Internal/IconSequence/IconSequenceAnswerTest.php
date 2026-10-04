<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Unit\Internal\IconSequence;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\HumanGate\Internal\IconSequence\IconSequenceAnswer;

#[TestDox('Ответ сохраняет порядок уникальных токенов без преобразования текста')]
final class IconSequenceAnswerTest extends TestCase
{
    public function testOrderedCanonicalAnswersAndMaximumLength(): void
    {
        $tokens = array_map(static fn(int $index): string => str_repeat(dechex($index), 32), range(1, 6));
        foreach (range(3, 6) as $length) {
            $sequence = array_slice($tokens, 0, $length);
            $answer = implode(',', $sequence);
            self::assertSame($answer, IconSequenceAnswer::canonicalize($sequence));
            self::assertSame($answer, IconSequenceAnswer::normalize($answer));
            self::assertNotSame($answer, IconSequenceAnswer::normalize(implode(',', array_reverse($sequence))));
        }
        self::assertSame(197, strlen(implode(',', $tokens)));
        self::assertNull(IconSequenceAnswer::normalize(implode(',', $tokens) . 'a'));
        self::assertNull(IconSequenceAnswer::normalize(str_repeat(',', 100000)));
    }

    public function testMalformedAnswersAndGeneratedData(): void
    {
        $tokens = [str_repeat('a', 32), str_repeat('b', 32), str_repeat('c', 32)];
        $answer = implode(',', $tokens);
        foreach ([
            '', implode(',', array_slice($tokens, 0, 2)), $answer . ',', ',' . $answer,
            $tokens[0] . ',,' . $tokens[1], $answer . ',' . $tokens[0],
            strtoupper($answer), str_replace('a', 'g', $answer), str_replace('a', 'а', $answer),
            str_replace(',', ';', $answer), substr($answer, 1), $answer . 'c', "\xff" . $answer,
            implode(',', array_map(static fn(int $n): string => str_pad(dechex($n), 32, '0', STR_PAD_LEFT), range(1, 7))),
        ] as $invalid) {
            self::assertNull(IconSequenceAnswer::normalize($invalid), bin2hex($invalid));
        }
        foreach ([" ", "\t", "\r", "\n", "\0", "\xc2\xa0"] as $space) {
            self::assertNull(IconSequenceAnswer::normalize($space . $answer));
            self::assertNull(IconSequenceAnswer::normalize($answer . $space));
            self::assertNull(IconSequenceAnswer::normalize($tokens[0] . ',' . $space . $tokens[1] . ',' . $tokens[2]));
        }
        foreach ([[], [1 => $tokens[0], 2 => $tokens[1], 3 => $tokens[2]], ['bad', 'bad', 'bad'], [$tokens[0], $tokens[0], $tokens[2]], array_slice($tokens, 0, 2)] as $invalid) {
            try {
                IconSequenceAnswer::canonicalize($invalid);
                self::fail('Invalid generated data accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Invalid icon sequence answer tokens.', $exception->getMessage());
            }
        }
    }
}
