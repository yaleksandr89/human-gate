<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Session;

use RuntimeException;
use Throwable;

final class NativeSessionStorageException extends RuntimeException
{
    public function __construct(public readonly NativeSessionStorageFailureReason $reason, ?Throwable $previous = null)
    {
        parent::__construct('Native PHP session storage failure: ' . $reason->value . '.', 0, $previous);
    }
}
