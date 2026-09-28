<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Challenge;

enum VerificationCode: string
{
    case Accepted = 'accepted';
    case Incorrect = 'incorrect';
    case AttemptsExhausted = 'attempts_exhausted';
    case NotFound = 'not_found';
    case PurposeMismatch = 'purpose_mismatch';
    case Expired = 'expired';
    case AlreadyConsumed = 'already_consumed';
    case Replaced = 'replaced';
}
