<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Unit\Presentation;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\HumanGate\Presentation\CategorySelectionCard;
use Yaleksandr\HumanGate\Presentation\CategorySelectionPresentation;
use Yaleksandr\HumanGate\Presentation\Presentation;

#[TestDox('Представление содержит только текст категории и уникальные карточки')]
final class CategorySelectionPresentationTest extends TestCase
{
    public function testValidPresentationAndCardBoundaries(): void
    {
        foreach ([3, 12] as $count) {
            $cards = self::cards($count);
            $presentation = new CategorySelectionPresentation(str_repeat('я', 40), $cards);
            self::assertInstanceOf(Presentation::class, $presentation);
            self::assertSame($cards, $presentation->cards);
            self::assertSame(str_repeat('я', 40), $presentation->category);
            self::assertEqualsCanonicalizing(['category', 'cards'], array_keys(get_object_vars($presentation)));
            self::assertSame(['token', 'label'], array_keys(get_object_vars($cards[0])));
        }
        $card = new CategorySelectionCard(str_repeat('a', 32), str_repeat('я', 40));
        self::assertSame(str_repeat('я', 40), $card->label);
        self::assertSame('<b>Fruit</b>', new CategorySelectionPresentation('<b>Fruit</b>', self::cards(3))->category);
        self::assertSame('<i>Apple</i>', new CategorySelectionCard(str_repeat('f', 32), '<i>Apple</i>')->label);
    }

    #[DataProvider('invalidLabels')]
    public function testInvalidLabels(string $label): void
    {
        foreach ([true, false] as $invalidCategory) {
            try {
                if ($invalidCategory) {
                    new CategorySelectionPresentation($label, self::cards(3));
                } else {
                    new CategorySelectionCard(str_repeat('a', 32), $label);
                }
                self::fail('Invalid presentation label was accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('label', $exception->getMessage());
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

    public function testInvalidTokens(): void
    {
        foreach (['', str_repeat('a', 31), str_repeat('a', 33), str_repeat('A', 32), str_repeat('g', 32), str_repeat('а', 32), str_repeat('a', 32) . "\n"] as $token) {
            try {
                new CategorySelectionCard($token, 'Label');
                self::fail('Invalid token was accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Invalid category selection card token.', $exception->getMessage());
            }
        }
    }

    public function testInvalidCardCollections(): void
    {
        $cards = self::cards(3);
        foreach ([
            [],
            self::cards(2),
            self::cards(13),
            [1 => $cards[0], 2 => $cards[1], 3 => $cards[2]],
            [$cards[0], $cards[1], new CategorySelectionCard($cards[0]->token, 'Other')],
            [$cards[0], $cards[1], new CategorySelectionCard(str_repeat('f', 32), $cards[0]->label)],
            [$cards[0], $cards[1], 'invalid'],
        ] as $invalid) {
            try {
                new CategorySelectionPresentation('Category', $invalid);
                self::fail('Invalid cards were accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('card', $exception->getMessage());
            }
        }
    }

    /** @return list<CategorySelectionCard> */
    private static function cards(int $count): array
    {
        return array_map(
            static fn(int $index): CategorySelectionCard => new CategorySelectionCard(
                str_pad(dechex($index), 32, '0', STR_PAD_LEFT),
                'Card ' . $index,
            ),
            range(1, $count),
        );
    }
}
