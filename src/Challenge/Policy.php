<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Challenge;

use InvalidArgumentException;

final readonly class Policy
{
    public function __construct(
        public int $ttlSeconds = 180,
        public int $maxWrongAttempts = 3,
        public int $maxActive = 12,
        public int $maxActivePerPurpose = 4,
        public int $maxTombstones = 32,
        public int $terminalRetentionSeconds = 180,
    ) {
        foreach (get_object_vars($this) as $value) {
            if ($value < 1) {
                throw new InvalidArgumentException('All policy values must be positive.');
            }
        }

        if ($maxActivePerPurpose > $maxActive) {
            throw new InvalidArgumentException('The per-purpose limit must not exceed the global active limit.');
        }
    }
}
