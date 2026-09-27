<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\State;

enum LifecycleCode: string
{
    case Active = 'active';
    case Issued = 'issued';
    case Refreshed = 'refreshed';
    case Consumed = 'consumed';
    case WrongAttempt = 'wrong_attempt';
    case CapacityExceeded = 'capacity_exceeded';
    case NotFound = 'not_found';
    case PurposeMismatch = 'purpose_mismatch';
    case Expired = 'expired';
    case AttemptsExhausted = 'attempts_exhausted';
    case AlreadyConsumed = 'already_consumed';
    case Replaced = 'replaced';
}
