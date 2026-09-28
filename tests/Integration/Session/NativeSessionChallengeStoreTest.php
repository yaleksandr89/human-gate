<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Integration\Session;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\HumanGate\Internal\Session\SessionBucketCodec;
use Yaleksandr\HumanGate\Tests\Support\NativeSession\NativeSessionFixture;

#[TestDox('Файловая сессия PHP сохраняет и проверяет состояние задач')]
final class NativeSessionChallengeStoreTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function serializers(): iterable
    {
        yield 'php' => ['php'];
        yield 'php_serialize' => ['php_serialize'];
    }

    #[DataProvider('serializers')]
    #[TestDox('Запись проверяется до возврата, а данные приложения сохраняются')]
    public function testVerifiedIssue(string $serializer): void
    {
        $fixture = new NativeSessionFixture();
        self::assertArrayNotHasKey('error', $fixture->run($serializer, 'init'));
        $issued = $fixture->run($serializer, 'issue');
        self::assertSame('issued', $issued['code']);
        self::assertTrue($issued['closed']);
        self::assertSame('1', $issued['strict']);
        self::assertSame('1', $issued['cookies']);
        self::assertSame('nocache', $issued['limiter']);

        $saved = $fixture->run($serializer, 'inspect');
        self::assertSame('untouched', $saved['app']);
        self::assertIsString($saved['raw']);
        self::assertCount(1, SessionBucketCodec::decode($saved['raw'])['receipts']);
    }

    #[DataProvider('serializers')]
    #[TestDox('Пустое действие не закрывает сессию и не расходует полномочие на запись')]
    public function testNoopThenMutation(string $serializer): void
    {
        $fixture = new NativeSessionFixture();
        $fixture->run($serializer, 'init');
        $result = $fixture->run($serializer, 'noop_then_issue');
        self::assertSame(['sameKey' => true, 'active' => true], $result['noop']);
        self::assertSame('issued', $result['code']);
        self::assertTrue($result['closed']);
    }

    #[DataProvider('serializers')]
    #[TestDox('Ошибка обработчика сохраняет ключ пакета, активную сессию и изменения приложения')]
    public function testCallbackException(string $serializer): void
    {
        $fixture = new NativeSessionFixture();
        $fixture->run($serializer, 'init');
        $result = $fixture->run($serializer, 'callback');
        self::assertSame('callback marker', $result['callback']);
        self::assertTrue($result['sameKey']);
        self::assertTrue($result['active']);
        self::assertSame('changed', $fixture->run($serializer, 'inspect')['app']);
    }

    #[DataProvider('serializers')]
    #[TestDox('Повреждённое существующее состояние не заменяется пустым')]
    public function testMalformedState(string $serializer): void
    {
        $fixture = new NativeSessionFixture();
        $fixture->run($serializer, 'init');
        $fixture->run($serializer, 'inject');
        $result = $fixture->run($serializer, 'issue');
        self::assertSame('malformed_state', $result['reason']);
        self::assertSame('{"schema":1,"active":[],"terminal":[],"receipts":[]}', $fixture->run($serializer, 'inspect')['raw']);
    }

    #[DataProvider('serializers')]
    #[TestDox('Последовательные операции допускают одно использование и сохраняют инкременты ошибок')]
    public function testSequentialOperations(string $serializer): void
    {
        $fixture = new NativeSessionFixture();
        $fixture->run($serializer, 'init');
        self::assertSame('issued', $fixture->run($serializer, 'issue')['code']);
        self::assertSame(1, $fixture->run($serializer, 'wrong')['attempts']);
        self::assertSame(2, $fixture->run($serializer, 'wrong')['attempts']);
        self::assertSame('consumed', $fixture->run($serializer, 'consume')['code']);
        self::assertSame('already_consumed', $fixture->run($serializer, 'consume')['code']);
    }

    #[DataProvider('serializers')]
    #[TestDox('Неактивная и неподдерживаемая сессия отклоняются явно')]
    public function testUnsupportedBoundaries(string $serializer): void
    {
        $fixture = new NativeSessionFixture();
        $fixture->run($serializer, 'init');
        self::assertSame('unsupported_session', $fixture->run($serializer, 'inactive_capture')['reason']);
        $unsupported = new NativeSessionFixture();
        self::assertSame('unsupported_session', $unsupported->run($serializer, 'unsupported_serializer')['reason']);
    }

    #[DataProvider('serializers')]
    #[TestDox('Уже отправленные заголовки блокируют запись до замены ключа')]
    public function testHeadersSent(string $serializer): void
    {
        $fixture = new NativeSessionFixture();
        $fixture->run($serializer, 'init');
        self::assertSame('headers_sent', $fixture->run($serializer, 'headers')['reason']);
        self::assertNull($fixture->run($serializer, 'inspect')['raw']);
    }

    #[DataProvider('serializers')]
    #[TestDox('Смена имени сессии отклоняется до изменения ключа пакета')]
    public function testSessionNameMismatch(string $serializer): void
    {
        $fixture = new NativeSessionFixture();
        $fixture->run($serializer, 'init');
        self::assertSame('session_identity', $fixture->run($serializer, 'name_mismatch')['reason']);
        self::assertNull($fixture->run($serializer, 'inspect')['raw']);
    }

    #[DataProvider('serializers')]
    #[TestDox('Вложенная операция отклоняется до чтения и не меняет ключ пакета')]
    public function testNestedAtomic(string $serializer): void
    {
        $fixture = new NativeSessionFixture();
        $fixture->run($serializer, 'init');
        $result = $fixture->run($serializer, 'nested');
        self::assertTrue($result['nestedRejected']);
        self::assertTrue($result['sameKey']);
        self::assertTrue($result['active']);
        self::assertNull($fixture->run($serializer, 'inspect')['raw']);
    }
}
