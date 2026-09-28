<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Unit\Port;

use LogicException;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\Policy;
use Yaleksandr\HumanGate\Challenge\Purpose;
use Yaleksandr\HumanGate\Internal\ChallengeLifecycle;
use Yaleksandr\HumanGate\Port\ChallengeStore;
use Yaleksandr\HumanGate\State\ChallengeBucket;
use Yaleksandr\HumanGate\State\LifecycleCode;
use Yaleksandr\HumanGate\State\LifecycleResult;
use Yaleksandr\HumanGate\Tests\Support\FrozenClock;
use Yaleksandr\HumanGate\Tests\Support\InMemoryChallengeStore;

#[TestDox('Эталонный атомарный контракт: последовательные вызовы, без проверки межпроцессных блокировок')]
final class ChallengeStoreTest extends TestCase
{
    private ChallengeStore $store;
    private ChallengeLifecycle $lifecycle;
    private ChallengeId $id;
    private Purpose $purpose;

    protected function setUp(): void
    {
        $this->store = new InMemoryChallengeStore();
        $this->lifecycle = new ChallengeLifecycle(new Policy(), new FrozenClock(1000));
        $this->id = ChallengeId::fromString(str_repeat('a', 64));
        $this->purpose = new Purpose('login');
    }

    #[TestDox('Успешный обработчик сохраняет состояние и возвращает исходное значение')]
    public function testSuccessfulCallbackCommitsAndPreservesReturnValue(): void
    {
        $marker = new Purpose('return-value');
        $result = $this->store->atomic(function (ChallengeBucket $bucket) use ($marker): Purpose {
            $this->lifecycle->issue($bucket, $this->id, $this->purpose, ChallengeKind::TextImage);

            return $marker;
        });

        self::assertSame($marker, $result);
        self::assertSame(LifecycleCode::Active, $this->lookup()->code);
    }

    #[TestDox('Исключение обработчика отменяет изменения и позволяет выполнить следующую операцию')]
    public function testCallbackExceptionRollsBackExistingState(): void
    {
        $this->issue();
        $failure = new RuntimeException('Abort callback');
        $caughtFailure = null;
        try {
            $this->store->atomic(function (ChallengeBucket $bucket) use ($failure): never {
                $this->lifecycle->consume($bucket, $this->id, $this->purpose);
                throw $failure;
            });
        } catch (RuntimeException $caught) {
            $caughtFailure = $caught;
        }

        self::assertSame($failure, $caughtFailure);
        self::assertSame(LifecycleCode::Active, $this->lookup()->code);
    }

    #[TestDox('Изменение выданной обработчику копии после фиксации не меняет сохранённое состояние')]
    public function testEscapedBucketCannotMutateCommittedState(): void
    {
        $this->issue();
        $escaped = $this->store->atomic(static fn(ChallengeBucket $bucket): ChallengeBucket => $bucket);
        $this->lifecycle->consume($escaped, $this->id, $this->purpose);

        self::assertSame(LifecycleCode::Active, $this->lookup()->code);
    }

    #[TestDox('Две последовательные попытки использования дают ровно один успешный результат')]
    public function testSerializedConsumesAcceptAtMostOnce(): void
    {
        $this->issue();
        $first = $this->store->atomic(fn(ChallengeBucket $bucket): LifecycleResult => $this->lifecycle->consume($bucket, $this->id, $this->purpose));
        $second = $this->store->atomic(fn(ChallengeBucket $bucket): LifecycleResult => $this->lifecycle->consume($bucket, $this->id, $this->purpose));

        self::assertSame(LifecycleCode::Consumed, $first->code);
        self::assertSame(LifecycleCode::AlreadyConsumed, $second->code);
    }

    #[TestDox('Последовательные транзакции неверных ответов не теряют инкременты')]
    public function testSerializedWrongAttemptsPreserveBothIncrements(): void
    {
        $this->issue();
        $first = $this->store->atomic(fn(ChallengeBucket $bucket): LifecycleResult => $this->lifecycle->registerWrongAttempt($bucket, $this->id, $this->purpose));
        $second = $this->store->atomic(fn(ChallengeBucket $bucket): LifecycleResult => $this->lifecycle->registerWrongAttempt($bucket, $this->id, $this->purpose));

        self::assertSame(1, $first->challenge?->wrongAttempts);
        self::assertSame(2, $second->challenge?->wrongAttempts);
        self::assertSame(2, $this->lookup()->challenge?->wrongAttempts);
    }

    #[TestDox('Вложенная транзакция отклоняется до чтения и не перезаписывает состояние')]
    public function testNestedCallsAreRejectedAndRolledBack(): void
    {
        $this->issue();
        try {
            $this->store->atomic(function (ChallengeBucket $bucket): void {
                $this->lifecycle->consume($bucket, $this->id, $this->purpose);
                $this->store->atomic(static fn(ChallengeBucket $nested): int => $nested->activeCount());
            });
            self::fail('Nested calls must fail.');
        } catch (LogicException) {
            self::assertSame(LifecycleCode::Active, $this->lookup()->code);
        }
    }

    private function issue(): void
    {
        $this->store->atomic(fn(ChallengeBucket $bucket): LifecycleResult => $this->lifecycle->issue($bucket, $this->id, $this->purpose, ChallengeKind::TextImage));
    }

    private function lookup(): LifecycleResult
    {
        return $this->store->atomic(fn(ChallengeBucket $bucket): LifecycleResult => $this->lifecycle->lookup($bucket, $this->id, $this->purpose));
    }
}
