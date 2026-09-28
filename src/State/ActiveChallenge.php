<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\State;

use InvalidArgumentException;
use OverflowException;
use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\Purpose;

final readonly class ActiveChallenge
{
    public function __construct(
        public ChallengeId $id,
        public Purpose $purpose,
        public ChallengeKind $kind,
        public int $issuedAt,
        public int $expiresAt,
        public int $wrongAttempts = 0,
    ) {
        if ($issuedAt < 0 || $expiresAt <= $issuedAt || $wrongAttempts < 0) {
            throw new InvalidArgumentException('Invalid active challenge timestamps or attempt count.');
        }
    }

    public function withWrongAttempt(): self
    {
        if ($this->wrongAttempts === PHP_INT_MAX) {
            throw new OverflowException('The wrong attempt count cannot be incremented.');
        }

        return new self($this->id, $this->purpose, $this->kind, $this->issuedAt, $this->expiresAt, $this->wrongAttempts + 1);
    }
}
