<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Support;

use Closure;
use LogicException;
use Yaleksandr\HumanGate\Port\ChallengeStore;
use Yaleksandr\HumanGate\State\ChallengeBucket;

/**
 * EN: Reference for serialized atomic calls only, not a cross-process locking adapter.
 * RU: Эталон только для последовательных атомарных вызовов, а не адаптер межпроцессных блокировок.
 */
final class InMemoryChallengeStore implements ChallengeStore
{
    private ChallengeBucket $bucket;
    private bool $inProgress = false;

    public function __construct()
    {
        $this->bucket = new ChallengeBucket();
    }

    public function atomic(Closure $transition): mixed
    {
        if ($this->inProgress) {
            throw new LogicException('Nested atomic calls are unsupported.');
        }

        $this->inProgress = true;
        try {
            $working = clone $this->bucket;
            $result = $transition($working);
            $this->bucket = clone $working;

            return $result;
        } finally {
            $this->inProgress = false;
        }
    }
}
