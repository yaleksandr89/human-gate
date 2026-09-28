<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\State;

use LogicException;
use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\Purpose;

/**
 * EN: Mutable only inside a ChallengeStore atomic scope; records themselves are immutable.
 * RU: Контейнер можно изменять только внутри атомарной области ChallengeStore; сами записи неизменяемы.
 */
final class ChallengeBucket
{
    /** @var array<string, ActiveChallenge> */
    private array $active = [];

    /** @var array<string, ChallengeTombstone> */
    private array $terminal = [];

    public function active(ChallengeId $id): ?ActiveChallenge
    {
        return $this->active[$id->value()] ?? null;
    }

    public function tombstone(ChallengeId $id): ?ChallengeTombstone
    {
        return $this->terminal[$id->value()] ?? null;
    }

    public function setActive(ActiveChallenge $challenge): void
    {
        if (isset($this->terminal[$challenge->id->value()])) {
            throw new LogicException('A terminal ID cannot become active.');
        }

        $this->active[$challenge->id->value()] = $challenge;
    }

    public function setTombstone(ChallengeTombstone $tombstone): void
    {
        unset($this->active[$tombstone->id->value()]);
        $this->terminal[$tombstone->id->value()] = $tombstone;
    }

    public function removeTombstone(ChallengeId $id): void
    {
        unset($this->terminal[$id->value()]);
    }

    /** @return iterable<ActiveChallenge> */
    public function activeChallenges(): iterable
    {
        yield from $this->active;
    }

    /** @return iterable<ChallengeTombstone> */
    public function tombstones(): iterable
    {
        yield from $this->terminal;
    }

    public function activeCount(?Purpose $purpose = null): int
    {
        if ($purpose === null) {
            return count($this->active);
        }

        $count = 0;
        foreach ($this->active as $challenge) {
            if ($challenge->purpose->value() === $purpose->value()) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * EN: Copies value state without sharing mutable storage with the source.
     * RU: Копирует состояние по значению, не разделяя изменяемое хранилище с источником.
     */
    public function copyFrom(self $source): void
    {
        $this->active = $source->active;
        $this->terminal = $source->terminal;
    }
}
