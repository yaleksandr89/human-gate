<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Integration\Session;

use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\HumanGate\Internal\Session\SessionBucketCodec;
use Yaleksandr\HumanGate\Tests\Support\NativeSession\NativeSessionFixture;
use Yaleksandr\HumanGate\Tests\Support\NativeSession\NativeSessionProcess;

#[TestDox('Проверка записи отклоняет читаемое смешанное состояние файловой сессии')]
final class NativeSessionChallengeStorePartialWriteTest extends TestCase
{
    #[DataProviderExternal(NativeSessionChallengeStoreTest::class, 'serializers')]
    #[TestDox('Новый токен с прежним содержимым не подтверждает запись автора')]
    public function testCanonicalHybridFailsWriter(string $serializer): void
    {
        $fixture = new NativeSessionFixture();
        $fixture->run($serializer, 'init');
        $fixture->run($serializer, 'issue');
        $before = SessionBucketCodec::decode($fixture->run($serializer, 'inspect')['raw']);
        $fixture->run($serializer, 'arm_interposer');

        [$writer] = NativeSessionProcess::interposed($fixture->directory, $fixture->id, $serializer, 'hybrid', 0);
        self::assertFileExists($fixture->directory . '/fork-done');
        self::assertSame('verification', $writer['reason']);

        $after = SessionBucketCodec::decode($fixture->run($serializer, 'inspect')['raw']);
        self::assertSame($before['payload'], $after['payload']);
        self::assertCount(2, $after['receipts']);
        self::assertNotSame($before['receipts'][0]['token'], $after['receipts'][1]['token']);
    }

    #[DataProviderExternal(NativeSessionChallengeStoreTest::class, 'serializers')]
    #[TestDox('Позднейшие успешные авторы не подтверждают повреждённую запись первого')]
    public function testHybridThenLaterWriters(string $serializer): void
    {
        foreach ([1, 2] as $followers) {
            $fixture = new NativeSessionFixture();
            $fixture->run($serializer, 'init');
            $fixture->run($serializer, 'issue');
            $fixture->run($serializer, 'arm_interposer');
            $results = NativeSessionProcess::interposed($fixture->directory, $fixture->id, $serializer, 'hybrid', $followers);
            $writer = $results[0];
            $later = array_slice($results, 1);
            self::assertFileExists($fixture->directory . '/fork-done');
            self::assertSame('verification', $writer['reason']);
            self::assertCount($followers, $later);
            foreach ($later as $result) {
                self::assertArrayNotHasKey('error', $result);
            }
            SessionBucketCodec::decode($fixture->run($serializer, 'inspect')['raw']);
        }
    }

    #[DataProviderExternal(NativeSessionChallengeStoreTest::class, 'serializers')]
    #[TestDox('Удаление исходного ID до проверки отклоняется при включённом строгом режиме')]
    public function testStrictModeIdentityReplacement(string $serializer): void
    {
        $fixture = new NativeSessionFixture();
        $fixture->run($serializer, 'init');
        $fixture->run($serializer, 'issue');
        [$writer, $peer] = NativeSessionProcess::identityReplacement($fixture->directory, $fixture->id, $serializer);
        self::assertTrue($peer['removed']);
        self::assertSame('session_identity', $writer['reason']);
        self::assertSame('1', $writer['strict']);
    }
}
