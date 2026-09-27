<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\State;

enum TerminalReason: string
{
    case Expired = 'expired';
    case AttemptsExhausted = 'attempts_exhausted';
    case Consumed = 'consumed';
    case Replaced = 'replaced';
}
