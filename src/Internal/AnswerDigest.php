<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Internal;

use JsonException;
use LogicException;
use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\Purpose;

final class AnswerDigest
{
    /**
     * EN: The challenge ID separates proofs, but disclosure of a proof still permits offline guessing of a short answer.
     * RU: ID задачи разделяет доказательства, но раскрытие доказательства всё равно позволяет перебирать короткий ответ без обращения к серверу.
     */
    public static function forAnswer(ChallengeId $id, Purpose $purpose, ChallengeKind $kind, string $canonicalAnswer): string
    {
        $tuple = ['human-gate/answer-proof', 1, $id->value(), $purpose->value(), $kind->value, $canonicalAnswer];

        try {
            $encoded = json_encode($tuple, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new LogicException('Canonical answer proof encoding failed.', 0, $exception);
        }

        return hash('sha256', $encoded);
    }
}
