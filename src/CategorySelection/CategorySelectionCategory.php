<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\CategorySelection;

use InvalidArgumentException;

final readonly class CategorySelectionCategory
{
    /** @var list<string> */
    public array $items;

    /**
     * EN: Validates the supplied array as a list of plain-text item labels.
     * RU: Проверяет переданный массив как список текстовых подписей предметов.
     *
     * @param array<array-key, mixed> $items
     */
    public function __construct(public string $label, array $items)
    {
        self::validateLabel($label);
        if (!array_is_list($items) || count($items) < 1 || count($items) > 32) {
            throw new InvalidArgumentException('Invalid category items.');
        }
        $seen = [];
        foreach ($items as $item) {
            if (!is_string($item)) {
                throw new InvalidArgumentException('Invalid category item label.');
            }
            self::validateLabel($item);
            if (in_array($item, $seen, true)) {
                throw new InvalidArgumentException('Duplicate category item label.');
            }
            $seen[] = $item;
        }
        $this->items = $seen;
    }

    private static function validateLabel(string $label): void
    {
        if (
            $label === ''
            || strlen($label) > 80
            || preg_match('//u', $label) !== 1
            || preg_match('/[\x00-\x1f\x7f]/', $label) !== 0
            || trim($label, " \t\r\n") !== $label
        ) {
            throw new InvalidArgumentException('Invalid category label.');
        }
    }
}
