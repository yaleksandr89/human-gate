<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Internal;

use Closure;
use InvalidArgumentException;
use LogicException;
use OverflowException;
use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\Policy;
use Yaleksandr\HumanGate\Challenge\Purpose;
use Yaleksandr\HumanGate\Port\Clock;
use Yaleksandr\HumanGate\State\ActiveChallenge;
use Yaleksandr\HumanGate\State\AnswerProof;
use Yaleksandr\HumanGate\State\ChallengeBucket;
use Yaleksandr\HumanGate\State\ChallengeTombstone;
use Yaleksandr\HumanGate\State\LifecycleCode;
use Yaleksandr\HumanGate\State\LifecycleResult;
use Yaleksandr\HumanGate\State\TerminalReason;

/**
 * EN: Package orchestration: callers must supply a bucket from ChallengeStore::atomic().
 * RU: Оркестрация пакета: вызывающий код обязан передать контейнер состояния из ChallengeStore::atomic().
 */
final readonly class ChallengeLifecycle
{
    public function __construct(private Policy $policy, private Clock $clock) {}

    public function issue(ChallengeBucket $bucket, ChallengeId $id, Purpose $purpose, ChallengeKind $kind, AnswerProof $proof): LifecycleResult
    {
        return $this->transition($bucket, function (ChallengeBucket $working, int $now) use ($id, $purpose, $kind, $proof): LifecycleResult {
            $this->requireUnusedId($working, $id);
            if ($this->atCapacity($working, $purpose)) {
                return new LifecycleResult(LifecycleCode::CapacityExceeded);
            }

            $challenge = $this->newChallenge($id, $purpose, $kind, $proof, $now);
            $working->setActive($challenge);

            return new LifecycleResult(LifecycleCode::Issued, $challenge);
        });
    }

    public function lookup(ChallengeBucket $bucket, ChallengeId $id, Purpose $purpose): LifecycleResult
    {
        return $this->transition($bucket, fn(ChallengeBucket $working, int $now): LifecycleResult => $this->resolve($working, $id, $purpose));
    }

    public function registerWrongAttempt(ChallengeBucket $bucket, ChallengeId $id, Purpose $purpose): LifecycleResult
    {
        return $this->transition($bucket, function (ChallengeBucket $working, int $now) use ($id, $purpose): LifecycleResult {
            $result = $this->resolve($working, $id, $purpose);
            $challenge = $result->challenge;
            if ($challenge === null) {
                return $result;
            }

            if ($challenge->wrongAttempts >= $this->policy->maxWrongAttempts - 1) {
                $working->setTombstone($this->terminal($id, $purpose, TerminalReason::AttemptsExhausted, $challenge->expiresAt, $now));

                return new LifecycleResult(LifecycleCode::AttemptsExhausted);
            }

            $updated = $challenge->withWrongAttempt();
            $working->setActive($updated);

            return new LifecycleResult(LifecycleCode::WrongAttempt, $updated);
        });
    }

    /**
     * EN: Call this method only after the answer has been accepted within the same atomic operation.
     * RU: Вызывайте этот метод только после того, как ответ признан корректным в рамках той же атомарной операции.
     */
    public function consume(ChallengeBucket $bucket, ChallengeId $id, Purpose $purpose): LifecycleResult
    {
        return $this->transition($bucket, function (ChallengeBucket $working, int $now) use ($id, $purpose): LifecycleResult {
            $result = $this->resolve($working, $id, $purpose);
            if ($result->challenge === null) {
                return $result;
            }

            $working->setTombstone($this->terminal($id, $purpose, TerminalReason::Consumed, $result->challenge->expiresAt, $now));

            return new LifecycleResult(LifecycleCode::Consumed);
        });
    }

    /**
     * EN: Replacement generation/rendering must already have succeeded before this transition.
     * RU: Генерация и отрисовка замены должны успешно завершиться до этого перехода.
     */
    public function replace(ChallengeBucket $bucket, ChallengeId $oldId, Purpose $purpose, ChallengeId $newId, ChallengeKind $newKind, AnswerProof $proof): LifecycleResult
    {
        return $this->transition($bucket, function (ChallengeBucket $working, int $now) use ($oldId, $purpose, $newId, $newKind, $proof): LifecycleResult {
            $result = $this->resolve($working, $oldId, $purpose);
            if ($result->code !== LifecycleCode::Active && $result->code !== LifecycleCode::Expired) {
                return $result;
            }

            $this->requireUnusedId($working, $newId);
            $active = $result->challenge;
            if ($active !== null) {
                $expiresAt = $active->expiresAt;
            } else {
                $tombstone = $working->tombstone($oldId);
                if ($tombstone === null) {
                    throw new LogicException('A replaceable challenge must exist.');
                }

                if ($this->atCapacity($working, $purpose)) {
                    return new LifecycleResult(LifecycleCode::CapacityExceeded);
                }

                $expiresAt = $tombstone->terminalAt;
            }

            $new = $this->newChallenge($newId, $purpose, $newKind, $proof, $now);
            $working->setTombstone($this->terminal($oldId, $purpose, TerminalReason::Replaced, $expiresAt, $now));
            $working->setActive($new);

            return new LifecycleResult(LifecycleCode::Refreshed, $new);
        });
    }

    /** @param Closure(ChallengeBucket, int): LifecycleResult $operation */
    private function transition(ChallengeBucket $bucket, Closure $operation): LifecycleResult
    {
        $now = $this->clock->now();
        if ($now < 0) {
            throw new InvalidArgumentException('Clock time must be a non-negative Unix timestamp.');
        }

        $working = clone $bucket;
        $this->cleanup($working, $now);
        $result = $operation($working, $now);

        // EN: Even cleanup stays provisional on a purpose mismatch or an exception.
        // RU: При несовпадении назначения или исключении даже изменения очистки состояния не фиксируются.
        if ($result->code !== LifecycleCode::PurposeMismatch) {
            $this->pruneTombstones($working, $now);
            $bucket->copyFrom($working);
        }

        return $result;
    }

    private function resolve(ChallengeBucket $bucket, ChallengeId $id, Purpose $purpose): LifecycleResult
    {
        $active = $bucket->active($id);
        if ($active !== null) {
            if ($active->purpose->value() !== $purpose->value()) {
                return new LifecycleResult(LifecycleCode::PurposeMismatch);
            }

            return new LifecycleResult(LifecycleCode::Active, $active);
        }

        $tombstone = $bucket->tombstone($id);
        if ($tombstone === null) {
            return new LifecycleResult(LifecycleCode::NotFound);
        }

        if ($tombstone->purpose->value() !== $purpose->value()) {
            return new LifecycleResult(LifecycleCode::PurposeMismatch);
        }

        return new LifecycleResult(match ($tombstone->reason) {
            TerminalReason::Expired => LifecycleCode::Expired,
            TerminalReason::AttemptsExhausted => LifecycleCode::AttemptsExhausted,
            TerminalReason::Consumed => LifecycleCode::AlreadyConsumed,
            TerminalReason::Replaced => LifecycleCode::Replaced,
        });
    }

    private function requireUnusedId(ChallengeBucket $bucket, ChallengeId $id): void
    {
        if ($bucket->active($id) !== null || $bucket->tombstone($id) !== null) {
            throw new LogicException('The generated challenge ID already exists.');
        }
    }

    private function atCapacity(ChallengeBucket $bucket, Purpose $purpose): bool
    {
        return $bucket->activeCount() >= $this->policy->maxActive
            || $bucket->activeCount($purpose) >= $this->policy->maxActivePerPurpose;
    }

    private function newChallenge(ChallengeId $id, Purpose $purpose, ChallengeKind $kind, AnswerProof $proof, int $now): ActiveChallenge
    {
        $expiresAt = $this->addSeconds($now, $this->policy->ttlSeconds);
        // EN: Reserve a representable retention horizon before accepting an active record.
        // RU: До принятия активной записи проверяем, что конец срока хранения представим целым числом.
        $this->addSeconds($expiresAt, $this->policy->terminalRetentionSeconds);

        return new ActiveChallenge($id, $purpose, $kind, $now, $expiresAt, $proof);
    }

    private function terminal(ChallengeId $id, Purpose $purpose, TerminalReason $reason, int $expiresAt, int $now): ChallengeTombstone
    {
        // EN: Retain through the original validity horizon plus retention (subject to cap eviction).
        // RU: Храним до конца исходного срока действия плюс период хранения, с возможным вытеснением по лимиту.
        return new ChallengeTombstone($id, $purpose, $reason, $now, $this->addSeconds(max($expiresAt, $now), $this->policy->terminalRetentionSeconds));
    }

    private function cleanup(ChallengeBucket $bucket, int $now): void
    {
        foreach ($bucket->activeChallenges() as $challenge) {
            if ($now >= $challenge->expiresAt) {
                $bucket->setTombstone($this->terminal($challenge->id, $challenge->purpose, TerminalReason::Expired, $challenge->expiresAt, $challenge->expiresAt));
            }
        }

        $this->pruneTombstones($bucket, $now);
    }

    private function pruneTombstones(ChallengeBucket $bucket, int $now): void
    {
        $retained = [];
        foreach ($bucket->tombstones() as $tombstone) {
            if ($now >= $tombstone->purgeAt) {
                $bucket->removeTombstone($tombstone->id);
            } else {
                $retained[] = $tombstone;
            }
        }

        $excess = count($retained) - $this->policy->maxTombstones;
        if ($excess <= 0) {
            return;
        }

        usort($retained, static fn(ChallengeTombstone $a, ChallengeTombstone $b): int => ($a->terminalAt <=> $b->terminalAt) ?: strcmp($a->id->value(), $b->id->value()));
        foreach (array_slice($retained, 0, $excess) as $tombstone) {
            $bucket->removeTombstone($tombstone->id);
        }
    }

    private function addSeconds(int $timestamp, int $seconds): int
    {
        if ($timestamp > PHP_INT_MAX - $seconds) {
            throw new OverflowException('The challenge timestamp exceeds the integer range.');
        }

        return $timestamp + $seconds;
    }
}
