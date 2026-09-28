<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Challenge;

use InvalidArgumentException;
use Random\RandomException;

final readonly class ChallengeId
{
    private function __construct(
        private string $value,
    ) {}

    /**
     * EN: Generates an ID using cryptographically secure randomness, propagating source failures.
     * RU: Создаёт ID из криптографически стойких случайных данных; ошибки источника передаются вызывающему коду.
     *
     * @throws RandomException
     */
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
