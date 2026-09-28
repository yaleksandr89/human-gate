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
     * EN: Returns the challenge kind handled by this strategy.
     * RU: Возвращает тип задачи, обрабатываемый этой стратегией.
     */
    public function kind(): ChallengeKind;

    /**
     * EN: Prepares proof and presentation before authoritative persistence without mutating authoritative challenge state.
     * RU: Готовит доказательство и представление до авторитетного сохранения, не изменяя авторитетное состояние задачи.
     */
    public function prepare(ChallengeId $id, Purpose $purpose): PreparedChallenge;

    /**
     * EN: Performs bounded, side-effect-free verification; this operation may run inside ChallengeStore::atomic().
     * RU: Выполняет ограниченную проверку без побочных эффектов; операция может выполняться внутри ChallengeStore::atomic().
     */
    public function verify(ActiveChallenge $challenge, string $submittedAnswer): bool;
}
