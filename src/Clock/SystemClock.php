<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Clock;

use Yaleksandr\HumanGate\Port\Clock;

final class SystemClock implements Clock
{
    public function now(): int
    {
        return time();
    }
}
