<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Challenge;

use InvalidArgumentException;

/**
 * EN: A logical purpose chosen by the server; client input is never an authoritative source for this value.
 * RU: Логическое назначение, задаваемое сервером; клиентский ввод не является доверенным источником этого значения.
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
