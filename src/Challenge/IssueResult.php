<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Challenge;

use InvalidArgumentException;
use Yaleksandr\HumanGate\State\LifecycleCode;

final readonly class IssueResult
{
    public function __construct(public LifecycleCode $code, public ?IssuedChallenge $challenge)
    {
        $valid = match ($code) {
            LifecycleCode::Issued, LifecycleCode::Refreshed => $challenge !== null,
            LifecycleCode::CapacityExceeded, LifecycleCode::NotFound, LifecycleCode::PurposeMismatch,
            LifecycleCode::Expired, LifecycleCode::AttemptsExhausted, LifecycleCode::AlreadyConsumed,
            LifecycleCode::Replaced => $challenge === null,
            default => false,
        };
        if (!$valid) {
            throw new InvalidArgumentException('Invalid issue result.');
        }
    }
}
