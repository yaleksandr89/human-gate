<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Port;

interface Clock
{
    /**
     * EN: Returns Unix epoch seconds.
     * RU: Возвращает Unix-время в секундах.
     */
    public function now(): int;
}
