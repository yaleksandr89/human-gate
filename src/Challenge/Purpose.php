<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Challenge;

use InvalidArgumentException;

/**
 * A logical binding selected by the server, never authoritatively by client input.
 */
final readonly class Purpose
{
    public function __construct(
        private string $value,
    ) {
        if (strlen($value) > 64 || preg_match('/\A[a-z0-9][a-z0-9._-]*\z/', $value) !== 1) {
            throw new InvalidArgumentException('Purpose must be 1 to 64 ASCII bytes, start with a lowercase letter or digit, and contain only lowercase letters, digits, dots, underscores or hyphens.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
