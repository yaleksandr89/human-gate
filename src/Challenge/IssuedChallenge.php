<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Challenge;

use Yaleksandr\HumanGate\Presentation\Presentation;

final readonly class IssuedChallenge
{
    public function __construct(
        public ChallengeId $id,
        public ChallengeKind $kind,
        public int $expiresAt,
        public Presentation $presentation,
    ) {}
}
