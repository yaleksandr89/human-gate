<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\State;

use InvalidArgumentException;

final readonly class LifecycleResult
{
    public function __construct(
        public LifecycleCode $code,
        public ?ActiveChallenge $challenge = null,
    ) {
        $needsActive = match ($code) {
            LifecycleCode::Active, LifecycleCode::Issued, LifecycleCode::Refreshed, LifecycleCode::WrongAttempt => true,
            default => false,
        };

        if ($needsActive !== ($challenge !== null)) {
            throw new InvalidArgumentException('The lifecycle code and active record do not match.');
        }
    }
}
