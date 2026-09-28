<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Integration\Session;

use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\HumanGate\Tests\Support\NativeSession\NativeSessionFixture;
use Yaleksandr\HumanGate\Tests\Support\NativeSession\NativeSessionProcess;

#[TestDox('Параллельные процессы сериализуются файловой блокировкой сессии')]
final class NativeSessionChallengeStoreConcurrencyTest extends TestCase
{
    #[DataProviderExternal(NativeSessionChallengeStoreTest::class, 'serializers')]
    #[TestDox('Два конкурирующих использования дают только один успех')]
    public function testCompetingConsume(string $serializer): void
    {
        $fixture = new NativeSessionFixture();
        $fixture->run($serializer, 'init');
        $fixture->run($serializer, 'issue');
        [$first, $second] = NativeSessionProcess::concurrent($fixture->directory, $fixture->id, $serializer, 'consume');
        self::assertSame('consumed', $first['code']);
        self::assertSame('already_consumed', $second['code']);
    }

    #[DataProviderExternal(NativeSessionChallengeStoreTest::class, 'serializers')]
    #[TestDox('Две конкурирующие неверные попытки сохраняют оба инкремента')]
    public function testCompetingWrongAttempts(string $serializer): void
    {
        $fixture = new NativeSessionFixture();
        $fixture->run($serializer, 'init');
        $fixture->run($serializer, 'issue');
        [$first, $second] = NativeSessionProcess::concurrent($fixture->directory, $fixture->id, $serializer, 'wrong');
        self::assertSame(1, $first['attempts']);
        self::assertSame(2, $second['attempts']);
    }

    #[DataProviderExternal(NativeSessionChallengeStoreTest::class, 'serializers')]
    #[TestDox('Отложенная проверка автора проходит после одного и двух следующих авторов')]
    public function testDelayedVerification(string $serializer): void
    {
        foreach ([1, 2] as $followers) {
            $fixture = new NativeSessionFixture();
            $fixture->run($serializer, 'init');
            $fixture->run($serializer, 'issue');
            $fixture->run($serializer, 'arm_interposer');
            $results = NativeSessionProcess::interposed($fixture->directory, $fixture->id, $serializer, 'normal', $followers);
            self::assertSame(1, $results[0]['attempts']);
            self::assertTrue($results[0]['closed']);
            for ($i = 1; $i <= $followers; ++$i) {
                self::assertArrayNotHasKey('error', $results[$i]);
            }
        }
    }

    #[DataProviderExternal(NativeSessionChallengeStoreTest::class, 'serializers')]
    #[TestDox('Вытеснение квитанции первого автора даёт только безопасный отказ')]
    public function testEvictedWriterFails(string $serializer): void
    {
        $fixture = new NativeSessionFixture();
        $fixture->run($serializer, 'init');
        $seed = $fixture->run($serializer, 'seed_eviction');
        self::assertGreaterThan(65310, $seed['bytes']);
        $fixture->run($serializer, 'arm_interposer');
        $results = NativeSessionProcess::interposed($fixture->directory, $fixture->id, $serializer, 'normal', 1);
        self::assertSame('verification', $results[0]['reason']);
        self::assertSame(2, $results[1]['attempts']);
    }
}
