<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\IconSequence;

use InvalidArgumentException;

final readonly class IconSequenceOptions
{
    public function __construct(
        public int $sequenceLength = 4,
        public int $choiceCount = 8,
    ) {
        if ($sequenceLength < 3 || $sequenceLength > 6 || $choiceCount < $sequenceLength + 2 || $choiceCount > 12) {
            throw new InvalidArgumentException('Invalid icon sequence options.');
        }
    }
}
