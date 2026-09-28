<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Unit\Internal;

use JsonException;
use LogicException;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\Purpose;
use Yaleksandr\HumanGate\Internal\AnswerDigest;

#[TestDox('Хеш ответа связан с идентификатором, назначением и типом')]
final class AnswerDigestTest extends TestCase
{
    public function testKnownVectorAndBindings(): void
    {
        $id = ChallengeId::fromString(str_repeat('a', 64));
        $purpose = new Purpose('login');
        $digest = AnswerDigest::forAnswer($id, $purpose, ChallengeKind::TextImage, '234567');
        self::assertSame('8becf5a4f99cc96eef9c60f72e57da32afe383c1182fafec3690ea4ebf8955af', $digest);
        self::assertNotSame($digest, AnswerDigest::forAnswer(ChallengeId::fromString(str_repeat('b', 64)), $purpose, ChallengeKind::TextImage, '234567'));
        self::assertNotSame($digest, AnswerDigest::forAnswer($id, new Purpose('signup'), ChallengeKind::TextImage, '234567'));
        self::assertNotSame($digest, AnswerDigest::forAnswer($id, $purpose, ChallengeKind::IconSequence, '234567'));
    }

    #[TestDox('Некорректный UTF-8 скрывает исключение кодирования за внутренней границей')]
    public function testInvalidUtf8IsWrappedInLogicException(): void
    {
        try {
            AnswerDigest::forAnswer(
                ChallengeId::fromString(str_repeat('a', 64)),
                new Purpose('login'),
                ChallengeKind::TextImage,
                "\xFF",
            );
            self::fail('Expected invalid UTF-8 to fail encoding.');
        } catch (LogicException $exception) {
            self::assertInstanceOf(JsonException::class, $exception->getPrevious());
        }
    }
}
