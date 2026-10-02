<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Unit\CategorySelection;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\HumanGate\CategorySelection\CategorySelectionOptions;

#[TestDox('Настройки ограничивают число карточек и правильных выборов')]
final class CategorySelectionOptionsTest extends TestCase
{
    public function testDefaultsAndValidBoundaries(): void
    {
        self::assertSame(6, new CategorySelectionOptions()->cardCount);
        self::assertSame(2, new CategorySelectionOptions()->targetCount);
        foreach ([3, 12] as $cardCount) {
            foreach ([1, $cardCount - 1] as $targetCount) {
                $options = new CategorySelectionOptions($cardCount, $targetCount);
                self::assertSame($cardCount, $options->cardCount);
                self::assertSame($targetCount, $options->targetCount);
            }
        }
    }

    public function testInvalidOptions(): void
    {
        foreach ([[2, 1], [13, 2], [6, 0], [6, -1], [6, 6], [6, 7]] as [$cards, $targets]) {
            try {
                new CategorySelectionOptions($cards, $targets);
                self::fail('Invalid options were accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Invalid category selection options.', $exception->getMessage());
            }
        }
    }
}
