<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Unit\IconSequence;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\HumanGate\IconSequence\IconSequenceOptions;

#[TestDox('Параметры последовательности ограничивают длину и гарантируют два отвлекающих варианта')]
final class IconSequenceOptionsTest extends TestCase
{
    public function testDefaultsAndBoundaries(): void
    {
        $options = new IconSequenceOptions();
        self::assertSame(4, $options->sequenceLength);
        self::assertSame(8, $options->choiceCount);
        self::assertSame(['sequenceLength', 'choiceCount'], array_keys(get_object_vars($options)));
        foreach (range(3, 6) as $length) {
            foreach ([$length + 2, 12] as $count) {
                $options = new IconSequenceOptions($length, $count);
                self::assertSame($length, $options->sequenceLength);
                self::assertSame($count, $options->choiceCount);
            }
        }
    }

    public function testInvalidCombinations(): void
    {
        foreach ([[2, 8], [7, 12], [3, 4], [6, 7], [4, 13], [0, 0], [PHP_INT_MAX, PHP_INT_MAX], [4, -1]] as [$length, $count]) {
            try {
                new IconSequenceOptions($length, $count);
                self::fail('Invalid options accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Invalid icon sequence options.', $exception->getMessage());
            }
        }
    }
}
