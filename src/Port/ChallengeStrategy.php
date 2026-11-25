<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Port;

use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\PreparedChallenge;
use Yaleksandr\HumanGate\Challenge\Purpose;
use Yaleksandr\HumanGate\State\ActiveChallenge;

interface ChallengeStrategy
{
    /**
     * RU: Возвращает тип задачи, обрабатываемый этой стратегией.
     * EN: Returns the challenge kind handled by this strategy.
     */
    public function kind(): ChallengeKind;

    /**
     * RU: Готовит доказательство и представление до авторитетного сохранения, не изменяя авторитетное состояние задачи.
     * EN: Prepares proof and presentation before authoritative persistence without mutating authoritative challenge state.
     */
    public function prepare(ChallengeId $id, Purpose $purpose): PreparedChallenge;

    /**
     * RU: Выполняет ограниченную проверку без побочных эффектов; операция может выполняться внутри ChallengeStore::atomic().
     * EN: Performs bounded, side-effect-free verification; this operation may run inside ChallengeStore::atomic().
     */
    public function verify(ActiveChallenge $challenge, string $submittedAnswer): bool;
}
