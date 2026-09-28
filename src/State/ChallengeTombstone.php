<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\State;

use InvalidArgumentException;
use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\Purpose;

final readonly class ChallengeTombstone
{
    public function __construct(
        public ChallengeId $id,
        public Purpose $purpose,
        public TerminalReason $reason,
        public int $terminalAt,
        public int $purgeAt,
    ) {
        if ($terminalAt < 0 || $purgeAt <= $terminalAt) {
            throw new InvalidArgumentException('Invalid terminal timestamps.');
        }
    }
}
