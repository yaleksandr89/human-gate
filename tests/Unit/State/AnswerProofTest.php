<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Unit\State;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\Purpose;
use Yaleksandr\HumanGate\State\ActiveChallenge;
use Yaleksandr\HumanGate\State\AnswerProof;

#[TestDox('Доказательство ответа хранит только допустимый формат и хеш')]
final class AnswerProofTest extends TestCase
{
    public function testValidProofAndAttemptPreservation(): void
    {
        $proof = new AnswerProof(1, str_repeat('a', 64));
        $active = new ActiveChallenge(ChallengeId::fromString(str_repeat('b', 64)), new Purpose('login'), ChallengeKind::TextImage, 0, 1, $proof);
        self::assertSame($proof, $active->withWrongAttempt()->proof);
    }

    public function testInvalidProofs(): void
    {
        foreach ([[2, str_repeat('a', 64)], [1, str_repeat('A', 64)], [1, str_repeat('z', 64)], [1, 'a']] as [$format, $digest]) {
            try {
                new AnswerProof($format, $digest);
                self::fail('Invalid proof was accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Invalid answer proof.', $exception->getMessage());
            }
        }
    }
}
