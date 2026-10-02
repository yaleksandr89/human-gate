<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\CategorySelection;

use InvalidArgumentException;

final readonly class CategorySelectionOptions
{
    public function __construct(public int $cardCount = 6, public int $targetCount = 2)
    {
        if ($cardCount < 3 || $cardCount > 12 || $targetCount < 1 || $targetCount >= $cardCount) {
            throw new InvalidArgumentException('Invalid category selection options.');
        }
    }
}
