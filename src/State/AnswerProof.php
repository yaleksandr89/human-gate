<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\State;

use InvalidArgumentException;

final readonly class AnswerProof
{
    public function __construct(public int $format, public string $digest)
    {
        if ($format !== 1 || preg_match('/\A[0-9a-f]{64}\z/', $digest) !== 1) {
            throw new InvalidArgumentException('Invalid answer proof.');
        }
    }
}
