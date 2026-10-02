<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Presentation;

use InvalidArgumentException;

/**
 * EN: The category is plain text; consumers must escape it for their output context. Human Gate does not emit HTML.
 * RU: Категория является обычным текстом; приложения должны экранировать её для контекста вывода. Human Gate не выдаёт HTML.
 */
final readonly class CategorySelectionPresentation implements Presentation
{
    /** @var list<CategorySelectionCard> */
    public array $cards;

    /**
     * EN: Validates the supplied array as a list of typed cards.
     * RU: Проверяет переданный массив как список типизированных карточек.
     *
     * @param array<array-key, mixed> $cards
     */
    public function __construct(public string $category, array $cards)
    {
        if (
            $category === ''
            || strlen($category) > 80
            || preg_match('//u', $category) !== 1
            || preg_match('/[\x00-\x1f\x7f]/', $category) !== 0
            || trim($category, " \t\r\n") !== $category
        ) {
            throw new InvalidArgumentException('Invalid category selection category label.');
        }
        if (!array_is_list($cards) || count($cards) < 3 || count($cards) > 12) {
            throw new InvalidArgumentException('Invalid category selection cards.');
        }
        $tokens = [];
        $labels = [];
        $validated = [];
        foreach ($cards as $card) {
            if (!$card instanceof CategorySelectionCard) {
                throw new InvalidArgumentException('Invalid category selection card.');
            }
            if (in_array($card->token, $tokens, true) || in_array($card->label, $labels, true)) {
                throw new InvalidArgumentException('Duplicate category selection card token or label.');
            }
            $tokens[] = $card->token;
            $labels[] = $card->label;
            $validated[] = $card;
        }
        $this->cards = $validated;
    }
}
