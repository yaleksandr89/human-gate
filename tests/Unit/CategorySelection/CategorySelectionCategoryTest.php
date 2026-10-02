<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Unit\CategorySelection;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\HumanGate\CategorySelection\CategorySelectionCategory;

#[TestDox('Категория принимает только ограниченные однозначные текстовые подписи')]
final class CategorySelectionCategoryTest extends TestCase
{
    public function testValidLabelsAndItemCountBoundaries(): void
    {
        $items = array_map(static fn(int $index): string => 'Предмет ' . $index, range(1, 32));
        $category = new CategorySelectionCategory(str_repeat('я', 40), $items);
        self::assertSame(str_repeat('я', 40), $category->label);
        self::assertSame($items, $category->items);
        self::assertSame(['<b>Яблоко</b>'], new CategorySelectionCategory('Фрукты', ['<b>Яблоко</b>'])->items);
        self::assertSame(['1', '01', 'A', 'a'], new CategorySelectionCategory('Разные', ['1', '01', 'A', 'a'])->items);
    }

    #[DataProvider('invalidLabels')]
    public function testInvalidCategoryAndItemLabels(string $label): void
    {
        foreach ([true, false] as $invalidCategory) {
            try {
                new CategorySelectionCategory($invalidCategory ? $label : 'Категория', [$invalidCategory ? 'Предмет' : $label]);
                self::fail('Invalid label was accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Invalid category label.', $exception->getMessage());
            }
        }
    }

    /** @return iterable<string, array{string}> */
    public static function invalidLabels(): iterable
    {
        yield 'empty' => [''];
        yield 'overlong ASCII' => [str_repeat('x', 81)];
        yield 'overlong UTF-8 bytes' => [str_repeat('я', 41)];
        yield 'invalid UTF-8' => ["\xff"];
        foreach ([" ", "\t", "\r", "\n"] as $index => $space) {
            yield 'leading ' . $index => [$space . 'Label'];
            yield 'trailing ' . $index => ['Label' . $space];
        }
        foreach ([...range(0, 31), 127] as $byte) {
            yield 'control ' . $byte => ['a' . chr($byte) . 'b'];
        }
    }

    public function testInvalidItemCollections(): void
    {
        foreach ([[], [1 => 'Item'], ['Item', 'Item'], array_fill(0, 33, 'Item'), [1]] as $items) {
            try {
                new CategorySelectionCategory('Category', $items);
                self::fail('Invalid items were accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('category', $exception->getMessage());
            }
        }
    }
}
