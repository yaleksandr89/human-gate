<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Challenge;

use InvalidArgumentException;

final readonly class ChallengeId
{
    private function __construct(
        private string $value,
    ) {}

    public static function generate(): self
    {
        return new self(bin2hex(random_bytes(32)));
    }

    public static function fromString(string $value): self
    {
        if (preg_match('/\A[0-9a-f]{64}\z/', $value) !== 1) {
            throw new InvalidArgumentException('Challenge ID must contain exactly 64 lowercase hexadecimal characters.');
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
