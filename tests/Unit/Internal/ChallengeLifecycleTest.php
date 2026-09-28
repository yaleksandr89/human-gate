<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Unit\Internal;

use InvalidArgumentException;
use LogicException;
use OverflowException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\Policy;
use Yaleksandr\HumanGate\Challenge\Purpose;
use Yaleksandr\HumanGate\Internal\ChallengeLifecycle;
use Yaleksandr\HumanGate\Port\Clock;
use Yaleksandr\HumanGate\State\ActiveChallenge;
use Yaleksandr\HumanGate\State\ChallengeBucket;
use Yaleksandr\HumanGate\State\ChallengeTombstone;
use Yaleksandr\HumanGate\State\LifecycleCode;
use Yaleksandr\HumanGate\State\LifecycleResult;
use Yaleksandr\HumanGate\State\TerminalReason;
use Yaleksandr\HumanGate\Tests\Support\FrozenClock;

final class ChallengeLifecycleTest extends TestCase
{
    private FrozenClock $clock;
    private ChallengeBucket $bucket;
    private ChallengeLifecycle $lifecycle;
    private Purpose $purpose;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock(1000);
        $this->bucket = new ChallengeBucket();
        $this->lifecycle = new ChallengeLifecycle(new Policy(), $this->clock);
        $this->purpose = new Purpose('login');
    }

    #[TestDox('Выпуск задаёт точные временные метки, тип, назначение и нулевой счётчик')]
    public function testIssueCreatesExactActiveState(): void
    {
        $result = $this->lifecycle->issue($this->bucket, self::id(1), $this->purpose, ChallengeKind::IconSequence);
        self::assertSame(LifecycleCode::Issued, $result->code);
        $record = $result->challenge;
        self::assertNotNull($record);
        self::assertSame(self::id(1)->value(), $record->id->value());
        self::assertSame($this->purpose, $record->purpose);
        self::assertSame(ChallengeKind::IconSequence, $record->kind);
        self::assertSame(1000, $record->issuedAt);
        self::assertSame(1180, $record->expiresAt);
        self::assertSame(0, $record->wrongAttempts);
        self::assertSame($record, $this->lookup(1)->challenge);
    }

    #[TestDox('Срок действия задачи истекает ровно на expiresAt и не продлевается чтением')]
    public function testExactExpiryBoundary(): void
    {
        $this->issue(1);
        $this->clock->set(1179);
        self::assertSame(LifecycleCode::Active, $this->lookup(1)->code);
        self::assertSame(1180, $this->lookup(1)->challenge?->expiresAt);
        $this->clock->set(1180);
        self::assertSame(LifecycleCode::Expired, $this->lookup(1)->code);
        self::assertNull($this->bucket->active(self::id(1)));
        $marker = $this->bucket->tombstone(self::id(1));
        self::assertNotNull($marker);
        self::assertSame(1180, $marker->terminalAt);
        self::assertSame(1360, $marker->purgeAt);
    }

    #[TestDox('Третий неверный ответ исчерпывает попытки, четвёртый не меняет маркер')]
    public function testWrongAttemptsBecomeTerminalWithoutExtendingTtl(): void
    {
        $original = $this->issue(1);
        foreach ([1, 2] as $attempt) {
            $this->clock->set(1000 + $attempt);
            $result = $this->wrong(1);
            self::assertSame(LifecycleCode::WrongAttempt, $result->code);
            self::assertNotNull($result->challenge);
            self::assertSame($attempt, $result->challenge->wrongAttempts);
            self::assertSame(1180, $result->challenge->expiresAt);
        }
        self::assertSame(0, $original->wrongAttempts);
        $this->clock->set(1003);
        self::assertSame(LifecycleCode::AttemptsExhausted, $this->wrong(1)->code);
        $marker = $this->bucket->tombstone(self::id(1));
        self::assertNotNull($marker);
        self::assertSame(TerminalReason::AttemptsExhausted, $marker->reason);
        self::assertSame(1003, $marker->terminalAt);
        self::assertSame(1360, $marker->purgeAt);
        self::assertSame(LifecycleCode::AttemptsExhausted, $this->wrong(1)->code);
        self::assertSame($marker, $this->bucket->tombstone(self::id(1)));
        self::assertNull($this->bucket->active(self::id(1)));
    }

    #[TestDox('Настраиваемый лимит в одну попытку срабатывает на первом неверном ответе')]
    public function testSingleAttemptPolicy(): void
    {
        $this->lifecycle = new ChallengeLifecycle(new Policy(maxWrongAttempts: 1), $this->clock);
        $this->issue(1);
        self::assertSame(LifecycleCode::AttemptsExhausted, $this->wrong(1)->code);
    }

    #[TestDox('Успешное использование однократно, маркер сохраняется до конца исходного срока действия и периода хранения')]
    public function testConsumeAndRetentionBoundary(): void
    {
        $this->issue(1);
        $this->clock->set(1001);
        self::assertSame(LifecycleCode::Consumed, $this->consume(1)->code);
        self::assertSame(LifecycleCode::AlreadyConsumed, $this->consume(1)->code);
        $marker = $this->bucket->tombstone(self::id(1));
        self::assertNotNull($marker);
        self::assertSame(1001, $marker->terminalAt);
        self::assertSame(1360, $marker->purgeAt);
        $this->clock->set(1359);
        self::assertSame(LifecycleCode::AlreadyConsumed, $this->consume(1)->code);
        $this->clock->set(1360);
        self::assertSame(LifecycleCode::NotFound, $this->consume(1)->code);
        self::assertNull($this->bucket->tombstone(self::id(1)));
    }

    #[DataProvider('targetOperations')]
    #[TestDox('Чужое назначение не меняет активное состояние и не фиксирует побочную очистку')]
    public function testPurposeMismatchDoesNotMutate(string $operation): void
    {
        $this->issue(1);
        $this->clock->set(1001);
        $this->issue(2);
        $this->clock->set(1180);
        $before = clone $this->bucket;
        $result = $this->targetOperation($operation, new Purpose('password-reset'));

        self::assertSame(LifecycleCode::PurposeMismatch, $result->code);
        self::assertNull($result->challenge);
        self::assertEquals($before, $this->bucket);
    }

    #[DataProvider('targetOperations')]
    #[TestDox('Чужое назначение маркера завершённой задачи не позволяет изменить его')]
    public function testTerminalPurposeMismatchDoesNotMutate(string $operation): void
    {
        $this->issue(1);
        $this->consume(1);
        $before = clone $this->bucket;

        self::assertSame(LifecycleCode::PurposeMismatch, $this->targetOperation($operation, new Purpose('other'))->code);
        self::assertEquals($before, $this->bucket);
    }

    #[DataProvider('targetOperations')]
    #[TestDox('Неизвестный идентификатор не выдаёт разрешение и не создаёт замену')]
    public function testUnknownIdIsNotFound(string $operation): void
    {
        self::assertSame(LifecycleCode::NotFound, $this->targetOperation($operation, $this->purpose)->code);
        self::assertSame(0, $this->bucket->activeCount());
    }

    /** @return iterable<string, array{string}> */
    public static function targetOperations(): iterable
    {
        foreach (['lookup', 'wrong', 'consume', 'replace'] as $operation) {
            yield $operation => [$operation];
        }
    }

    #[TestDox('Общий лимит не вытесняет задачи других вкладок')]
    public function testGlobalCapacityDoesNotEvict(): void
    {
        for ($i = 1; $i <= 12; ++$i) {
            $this->issue($i, new Purpose('form-' . $i));
        }
        $before = clone $this->bucket;
        $result = $this->lifecycle->issue($this->bucket, self::id(13), $this->purpose, ChallengeKind::TextImage);

        self::assertSame(LifecycleCode::CapacityExceeded, $result->code);
        self::assertEquals($before, $this->bucket);
        self::assertSame(12, $this->bucket->activeCount());
    }

    #[TestDox('Лимит назначения сохраняет другие вкладки и оставляет место другому назначению')]
    public function testPerPurposeCapacityAndIndependentTabs(): void
    {
        for ($i = 1; $i <= 4; ++$i) {
            $this->issue($i);
        }
        self::assertSame(LifecycleCode::CapacityExceeded, $this->lifecycle->issue($this->bucket, self::id(5), new Purpose('login'), ChallengeKind::TextImage)->code);
        $this->issue(6, new Purpose('registration'));
        $this->wrong(1);
        $this->consume(2);

        self::assertSame(1, $this->lookup(1)->challenge?->wrongAttempts);
        self::assertSame(0, $this->lookup(3)->challenge?->wrongAttempts);
        self::assertSame(LifecycleCode::Active, $this->lookup(4)->code);
        self::assertSame(4, $this->bucket->activeCount());
    }

    #[TestDox('Точная замена нейтральна по обоим лимитам и сохраняет соседнюю вкладку')]
    public function testActiveRefreshAtBothCapsAndRepeatedRefresh(): void
    {
        $this->lifecycle = new ChallengeLifecycle(new Policy(maxActive: 2, maxActivePerPurpose: 2), $this->clock);
        $this->issue(1);
        $other = $this->issue(2);
        $this->wrong(1);
        $this->clock->set(1010);
        $result = $this->replace(1, 3);

        self::assertSame(LifecycleCode::Refreshed, $result->code);
        self::assertNotNull($result->challenge);
        self::assertSame(1010, $result->challenge->issuedAt);
        self::assertSame(1190, $result->challenge->expiresAt);
        self::assertSame(0, $result->challenge->wrongAttempts);
        self::assertSame(ChallengeKind::CategorySelection, $result->challenge->kind);
        self::assertSame(LifecycleCode::Replaced, $this->lookup(1)->code);
        self::assertSame(1360, $this->bucket->tombstone(self::id(1))?->purgeAt);
        self::assertSame($result->challenge, $this->lookup(3)->challenge);
        self::assertSame($other, $this->lookup(2)->challenge);
        self::assertSame(2, $this->bucket->activeCount());
        $beforeRetry = clone $this->bucket;
        self::assertSame(LifecycleCode::Replaced, $this->replace(1, 3)->code);
        self::assertEquals($beforeRetry, $this->bucket);
        self::assertSame($result->challenge, $this->lookup(3)->challenge);
        self::assertSame(2, $this->bucket->activeCount());
        self::assertSame(LifecycleCode::Replaced, $this->replace(1, 4)->code);
        self::assertSame(LifecycleCode::NotFound, $this->lookup(4)->code);
        self::assertSame(2, $this->bucket->activeCount());
    }

    #[DataProvider('nonReplaceableCollisionCases')]
    #[TestDox('Результат для исходной задачи имеет приоритет над коллизией нового идентификатора')]
    public function testOldResultPrecedesNewIdCollision(LifecycleCode $expected, bool $terminalCollision): void
    {
        if ($expected !== LifecycleCode::NotFound) {
            $this->issue(1);
        }
        if ($expected === LifecycleCode::AlreadyConsumed) {
            $this->consume(1);
        } elseif ($expected === LifecycleCode::AttemptsExhausted) {
            $this->wrong(1);
            $this->wrong(1);
            $this->wrong(1);
        }
        $this->issue(2);
        if ($terminalCollision) {
            $this->consume(2);
        }
        $before = clone $this->bucket;
        $purpose = $expected === LifecycleCode::PurposeMismatch ? new Purpose('other') : $this->purpose;

        $result = $this->lifecycle->replace($this->bucket, self::id(1), $purpose, self::id(2), ChallengeKind::CategorySelection);

        self::assertSame($expected, $result->code);
        self::assertNull($result->challenge);
        self::assertEquals($before, $this->bucket);
    }

    /** @return iterable<string, array{LifecycleCode, bool}> */
    public static function nonReplaceableCollisionCases(): iterable
    {
        foreach ([LifecycleCode::PurposeMismatch, LifecycleCode::NotFound, LifecycleCode::AlreadyConsumed, LifecycleCode::AttemptsExhausted] as $code) {
            yield $code->value . ' active collision' => [$code, false];
            yield $code->value . ' terminal collision' => [$code, true];
        }
    }

    #[DataProvider('expiredCollisionCases')]
    #[TestDox('Замена истёкшей задачи отклоняет занятый идентификатор без изменения состояния')]
    public function testExpiredReplacementCollisionPreservesState(int $newId): void
    {
        $this->issue(1);
        $this->clock->set(1180);
        $this->issue(2);
        $this->issue(3);
        $this->consume(3);
        $before = clone $this->bucket;

        try {
            $this->replace(1, $newId);
            self::fail('A replaceable expired ID must still reject a collision.');
        } catch (LogicException) {
            self::assertEquals($before, $this->bucket);
        }
    }

    /** @return iterable<string, array{int}> */
    public static function expiredCollisionCases(): iterable
    {
        yield 'same old and new ID' => [1];
        yield 'active collision' => [2];
        yield 'terminal collision' => [3];
    }

    #[TestDox('Известная истёкшая задача заменяется с новым сроком хранения маркера')]
    public function testRetainedExpiredChallengeCanBeRefreshed(): void
    {
        $this->issue(1);
        $this->clock->set(1200);
        self::assertSame(LifecycleCode::Expired, $this->lookup(1)->code);
        self::assertSame(LifecycleCode::Refreshed, $this->replace(1, 2)->code);
        self::assertSame(LifecycleCode::Replaced, $this->lookup(1)->code);
        $marker = $this->bucket->tombstone(self::id(1));
        self::assertNotNull($marker);
        self::assertSame(1200, $marker->terminalAt);
        self::assertSame(1380, $marker->purgeAt);
        self::assertSame(1380, $this->lookup(2)->challenge?->expiresAt);
        self::assertSame(LifecycleCode::Replaced, $this->replace(1, 3)->code);
    }

    #[DataProvider('capacityPolicies')]
    #[TestDox('Замена истёкшей задачи соблюдает общий лимит и лимит назначения, сохраняя маркер')]
    public function testExpiredRefreshRespectsCapacity(Policy $policy): void
    {
        $this->lifecycle = new ChallengeLifecycle($policy, $this->clock);
        $this->issue(1);
        $this->clock->set(1180);
        $this->issue(2);
        $before = clone $this->bucket;

        self::assertSame(LifecycleCode::CapacityExceeded, $this->replace(1, 3)->code);
        self::assertEquals($before, $this->bucket);
        self::assertSame(LifecycleCode::Expired, $this->lookup(1)->code);
        self::assertSame(LifecycleCode::Active, $this->lookup(2)->code);
    }

    /** @return iterable<string, array{Policy}> */
    public static function capacityPolicies(): iterable
    {
        yield 'global cap' => [new Policy(maxActive: 1, maxActivePerPurpose: 1)];
        yield 'purpose cap with global space' => [new Policy(maxActive: 2, maxActivePerPurpose: 1)];
    }

    #[TestDox('На границе срока удаления маркера истёкшую задачу уже нельзя обновить')]
    public function testExpiredTombstonePurgePreventsRefresh(): void
    {
        $this->issue(1);
        $this->clock->set(1359);
        self::assertSame(LifecycleCode::Expired, $this->lookup(1)->code);
        $this->clock->set(1360);
        self::assertSame(LifecycleCode::NotFound, $this->replace(1, 2)->code);
        self::assertSame(0, $this->bucket->activeCount());
        self::assertNull($this->bucket->tombstone(self::id(1)));
    }

    #[TestDox('Лимит маркеров удаляет запись с наименьшим terminalAt, затем с меньшим каноническим ID')]
    public function testTombstoneCapHasDeterministicOrdering(): void
    {
        $this->lifecycle = new ChallengeLifecycle(new Policy(maxTombstones: 2), $this->clock);
        $this->issue(9);
        $this->issue(3);
        $this->issue(2);
        $live = $this->issue(4);
        $this->consume(9);
        $this->clock->set(1001);
        $this->consume(3);
        $this->consume(2);
        self::assertSame(LifecycleCode::NotFound, $this->lookup(9)->code);
        self::assertSame(LifecycleCode::AlreadyConsumed, $this->lookup(2)->code);
        self::assertSame(LifecycleCode::AlreadyConsumed, $this->lookup(3)->code);

        $this->issue(5);
        $this->consume(5);
        self::assertSame(LifecycleCode::NotFound, $this->lookup(2)->code);
        self::assertSame(LifecycleCode::AlreadyConsumed, $this->lookup(3)->code);
        self::assertSame(LifecycleCode::AlreadyConsumed, $this->lookup(5)->code);
        self::assertSame($live, $this->lookup(4)->challenge);
        self::assertCount(2, iterator_to_array($this->bucket->tombstones()));
    }

    #[TestDox('Очистка ограничивает число маркеров и освобождает место, сохраняя действующую задачу другой вкладки')]
    public function testCleanupExpiresOnlyExpiredRecordsAndBoundsMarkers(): void
    {
        $this->lifecycle = new ChallengeLifecycle(new Policy(maxActive: 3, maxActivePerPurpose: 3, maxTombstones: 1), $this->clock);
        $this->issue(1);
        $this->issue(2);
        $this->clock->set(1010);
        $live = $this->issue(3);
        $this->clock->set(1180);
        $this->issue(4);

        self::assertSame(2, $this->bucket->activeCount());
        self::assertSame($live, $this->lookup(3)->challenge);
        self::assertSame(LifecycleCode::NotFound, $this->lookup(1)->code);
        self::assertSame(LifecycleCode::Expired, $this->lookup(2)->code);
        self::assertCount(1, iterator_to_array($this->bucket->tombstones()));
    }

    #[DataProvider('collisionCases')]
    #[TestDox('Коллизия идентификатора активной или завершённой задачи при выпуске и замене сохраняет состояние')]
    public function testIdCollisionIsInvariantFailure(bool $terminal, bool $replacement): void
    {
        $this->lifecycle = new ChallengeLifecycle(new Policy(maxActive: 2, maxActivePerPurpose: 2), $this->clock);
        $this->issue(1);
        $this->issue(2);
        if ($terminal) {
            $this->consume(2);
        }
        $before = clone $this->bucket;

        try {
            if ($replacement) {
                $this->replace(1, 2);
            } else {
                $this->issue(2);
            }
            self::fail('Collision must throw, even at capacity.');
        } catch (LogicException) {
            self::assertEquals($before, $this->bucket);
        }
    }

    /** @return iterable<string, array{bool, bool}> */
    public static function collisionCases(): iterable
    {
        yield 'issue active' => [false, false];
        yield 'issue terminal' => [true, false];
        yield 'refresh active' => [false, true];
        yield 'refresh terminal' => [true, true];
    }

    #[TestDox('Замена с совпадающими старым и новым ID отклоняется без потери задачи')]
    public function testSameIdReplacementIsRejected(): void
    {
        $record = $this->issue(1);
        try {
            $this->replace(1, 1);
            self::fail('Self-replacement must throw.');
        } catch (LogicException) {
            self::assertSame($record, $this->lookup(1)->challenge);
        }
    }

    #[DataProvider('terminalStates')]
    #[TestDox('Завершённая задача не допускает успешного использования или учёта неверного ответа; замена разрешена только для истёкшей задачи')]
    public function testTerminalStatesCannotGrantPermission(TerminalReason $reason, LifecycleCode $expected): void
    {
        $this->issue(1);
        match ($reason) {
            TerminalReason::Expired => $this->clock->set(1180),
            TerminalReason::Consumed => $this->consume(1),
            TerminalReason::Replaced => $this->replace(1, 2),
            TerminalReason::AttemptsExhausted => [$this->wrong(1), $this->wrong(1), $this->wrong(1)],
        };

        self::assertSame($expected, $this->lookup(1)->code);
        self::assertSame($expected, $this->consume(1)->code);
        self::assertSame($expected, $this->wrong(1)->code);
        if ($reason !== TerminalReason::Expired) {
            self::assertSame($expected, $this->replace(1, 3)->code);
            self::assertNull($this->bucket->active(self::id(3)));
        }
    }

    /** @return iterable<string, array{TerminalReason, LifecycleCode}> */
    public static function terminalStates(): iterable
    {
        yield 'expired' => [TerminalReason::Expired, LifecycleCode::Expired];
        yield 'exhausted' => [TerminalReason::AttemptsExhausted, LifecycleCode::AttemptsExhausted];
        yield 'consumed' => [TerminalReason::Consumed, LifecycleCode::AlreadyConsumed];
        yield 'replaced' => [TerminalReason::Replaced, LifecycleCode::Replaced];
    }

    #[DataProvider('overflowPolicies')]
    #[TestDox('Переполнение срока действия или хранения не создаёт задачу и не фиксирует очистку')]
    public function testOverflowCannotPublishPartialState(Policy $policy, int $now): void
    {
        $this->issue(1);
        $before = clone $this->bucket;
        $this->clock->set($now);
        $this->lifecycle = new ChallengeLifecycle($policy, $this->clock);
        try {
            $this->issue(2);
            self::fail('Overflow must throw.');
        } catch (OverflowException) {
            self::assertEquals($before, $this->bucket);
        }
    }

    /** @return iterable<string, array{Policy, int}> */
    public static function overflowPolicies(): iterable
    {
        yield 'expiry overflow' => [new Policy(), PHP_INT_MAX - 10];
        yield 'retention overflow' => [new Policy(), PHP_INT_MAX - 200];
        yield 'large ttl' => [new Policy(ttlSeconds: PHP_INT_MAX), 1000];
        yield 'cleanup retention overflow' => [new Policy(terminalRetentionSeconds: PHP_INT_MAX), 1180];
    }

    #[TestDox('Переполнение срока замены не уничтожает ещё действующую исходную задачу')]
    public function testReplacementOverflowPreservesOldChallenge(): void
    {
        $this->issue(1);
        $before = clone $this->bucket;
        $this->lifecycle = new ChallengeLifecycle(new Policy(ttlSeconds: PHP_INT_MAX), $this->clock);
        try {
            $this->replace(1, 2);
            self::fail('Replacement expiry must not overflow.');
        } catch (OverflowException) {
            self::assertEquals($before, $this->bucket);
        }
    }

    #[TestDox('Последняя представимая граница срока хранения допустима, маркер удаляется ровно на ней')]
    public function testMaximumRepresentableHorizon(): void
    {
        $this->lifecycle = new ChallengeLifecycle(new Policy(ttlSeconds: 1, terminalRetentionSeconds: 1), $this->clock);
        $this->clock->set(PHP_INT_MAX - 2);
        $record = $this->issue(1);
        self::assertSame(PHP_INT_MAX - 1, $record->expiresAt);
        $this->clock->set(PHP_INT_MAX - 1);
        self::assertSame(LifecycleCode::Expired, $this->lookup(1)->code);
        self::assertSame(PHP_INT_MAX, $this->bucket->tombstone(self::id(1))?->purgeAt);
        $this->clock->set(PHP_INT_MAX);
        self::assertSame(LifecycleCode::NotFound, $this->consume(1)->code);
    }

    #[TestDox('Отрицательное время не создаёт состояние')]
    public function testNegativeClockIsRejected(): void
    {
        $this->clock->set(-1);
        $this->expectException(InvalidArgumentException::class);
        $this->issue(1);
    }

    #[DataProvider('invalidActiveRecords')]
    #[TestDox('Активная запись отклоняет некорректные временные метки и отрицательное число попыток')]
    public function testInvalidActiveRecord(int $issuedAt, int $expiresAt, int $attempts): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ActiveChallenge(self::id(1), $this->purpose, ChallengeKind::TextImage, $issuedAt, $expiresAt, $attempts);
    }

    /** @return iterable<string, array{int, int, int}> */
    public static function invalidActiveRecords(): iterable
    {
        yield 'negative issue' => [-1, 100, 0];
        yield 'equal expiry' => [100, 100, 0];
        yield 'earlier expiry' => [100, 99, 0];
        yield 'negative attempts' => [100, 200, -1];
    }

    #[DataProvider('invalidTombstones')]
    #[TestDox('Запись о завершённой задаче отклоняет некорректный срок хранения')]
    public function testInvalidTombstone(int $terminalAt, int $purgeAt): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ChallengeTombstone(self::id(1), $this->purpose, TerminalReason::Consumed, $terminalAt, $purgeAt);
    }

    /** @return iterable<string, array{int, int}> */
    public static function invalidTombstones(): iterable
    {
        yield 'negative terminal time' => [-1, 100];
        yield 'equal purge' => [100, 100];
        yield 'earlier purge' => [100, 99];
    }

    #[DataProvider('invalidResults')]
    #[TestDox('Результат жизненного цикла не допускает несовместимые код и активную запись')]
    public function testInvalidResultCombination(LifecycleCode $code, bool $hasRecord): void
    {
        $record = $hasRecord ? $this->issue(1) : null;
        $this->expectException(InvalidArgumentException::class);
        new LifecycleResult($code, $record);
    }

    /** @return iterable<string, array{LifecycleCode, bool}> */
    public static function invalidResults(): iterable
    {
        yield 'active missing record' => [LifecycleCode::Active, false];
        yield 'issued missing record' => [LifecycleCode::Issued, false];
        yield 'refreshed missing record' => [LifecycleCode::Refreshed, false];
        yield 'attempt missing record' => [LifecycleCode::WrongAttempt, false];
        yield 'consumed with active record' => [LifecycleCode::Consumed, true];
        yield 'mismatch with active record' => [LifecycleCode::PurposeMismatch, true];
        yield 'exhausted with active record' => [LifecycleCode::AttemptsExhausted, true];
    }

    #[DataProvider('targetOperations')]
    #[TestDox('Операция жизненного цикла считывает Clock ровно один раз')]
    public function testSingleClockReadPerOperation(string $operation): void
    {
        $this->issue(1);
        $clock = new class implements Clock {
            public int $reads = 0;

            public function now(): int
            {
                ++$this->reads;

                return 1000;
            }
        };
        $this->lifecycle = new ChallengeLifecycle(new Policy(), $clock);
        $this->targetOperation($operation, $this->purpose);
        self::assertSame(1, $clock->reads);
        $this->issue(3);
        self::assertSame(2, $clock->reads);
    }

    private static function id(int $number): ChallengeId
    {
        return ChallengeId::fromString(sprintf('%064x', $number));
    }

    private function issue(int $number, ?Purpose $purpose = null): ActiveChallenge
    {
        $result = $this->lifecycle->issue($this->bucket, self::id($number), $purpose ?? $this->purpose, ChallengeKind::TextImage);
        self::assertSame(LifecycleCode::Issued, $result->code);
        self::assertNotNull($result->challenge);

        return $result->challenge;
    }

    private function lookup(int $number): LifecycleResult
    {
        return $this->lifecycle->lookup($this->bucket, self::id($number), $this->purpose);
    }

    private function wrong(int $number): LifecycleResult
    {
        return $this->lifecycle->registerWrongAttempt($this->bucket, self::id($number), $this->purpose);
    }

    private function consume(int $number): LifecycleResult
    {
        return $this->lifecycle->consume($this->bucket, self::id($number), $this->purpose);
    }

    private function replace(int $old, int $new): LifecycleResult
    {
        return $this->lifecycle->replace($this->bucket, self::id($old), $this->purpose, self::id($new), ChallengeKind::CategorySelection);
    }

    private function targetOperation(string $operation, Purpose $purpose): LifecycleResult
    {
        return match ($operation) {
            'lookup' => $this->lifecycle->lookup($this->bucket, self::id(1), $purpose),
            'wrong' => $this->lifecycle->registerWrongAttempt($this->bucket, self::id(1), $purpose),
            'consume' => $this->lifecycle->consume($this->bucket, self::id(1), $purpose),
            'replace' => $this->lifecycle->replace($this->bucket, self::id(1), $purpose, self::id(99), ChallengeKind::CategorySelection),
            default => throw new LogicException('Unknown test operation.'),
        };
    }
}
