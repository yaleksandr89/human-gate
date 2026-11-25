<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Port;

interface Clock
{
    /**
     * RU: Возвращает Unix-время в секундах.
     * EN: Returns Unix epoch seconds.
     */
    public function now(): int;
}
