<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Unit\Internal\Session;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\Purpose;
use Yaleksandr\HumanGate\Internal\Session\SessionBucketCodec;
use Yaleksandr\HumanGate\Session\NativeSessionStorageException;
use Yaleksandr\HumanGate\State\ActiveChallenge;
use Yaleksandr\HumanGate\State\ChallengeBucket;

#[TestDox('Канонический формат состояния сессии')]
final class SessionBucketCodecTest extends TestCase
{
    #[TestDox('Записи сортируются и проходят точный круговой переход JSON')]
    public function testCanonicalRoundTrip(): void
    {
        $bucket = new ChallengeBucket();
        $bucket->setActive(self::record('b'));
        $bucket->setActive(self::record('a'));
        $payload = SessionBucketCodec::encodePayload($bucket);
        $receipts = [self::receipt('1', null, $payload)];
        $json = SessionBucketCodec::encodeNamespace($bucket, $receipts);

        self::assertSame('{"schema":1,"active":[', substr($json, 0, 22));
        self::assertLessThan(strpos($json, str_repeat('b', 64)), strpos($json, str_repeat('a', 64)));
        self::assertSame($payload, SessionBucketCodec::decode($json)['payload']);
        self::assertSame($json, SessionBucketCodec::decode($json)['namespace']);
    }

    #[TestDox('Отсутствующее значение пусто, но присутствующее без квитанций ошибочно')]
    public function testEmptyBoundary(): void
    {
        self::assertSame([], SessionBucketCodec::empty()['receipts']);
        $this->expectException(NativeSessionStorageException::class);
        SessionBucketCodec::decode('{"schema":1,"active":[],"terminal":[],"receipts":[]}');
    }

    #[TestDox('Неканонический JSON, лишние поля и повторяющиеся ключи отклоняются')]
    public function testStrictStructure(): void
    {
        $valid = self::valid();
        $bad = [
            ' ' . $valid,
            str_replace('"schema":1', '"schema":1,"schema":1', $valid),
            str_replace('"schema":1', '"schema":1,"extra":0', $valid),
            str_replace('"schema":1', '"schema":2', $valid),
            str_replace('"active":[]', '"active":{}', $valid),
            'broken',
            null,
        ];
        foreach ($bad as $value) {
            try {
                SessionBucketCodec::decode($value);
                self::fail('Malformed package state was accepted.');
            } catch (NativeSessionStorageException $exception) {
                self::assertSame('malformed_state', $exception->reason->value);
            }
        }
    }

    #[TestDox('Повтор идентификатора и пересечение активных и завершённых записей отклоняются')]
    public function testDuplicateIdentifiers(): void
    {
        $record = ['id' => str_repeat('a', 64), 'purpose' => 'login', 'kind' => 'text_image', 'issuedAt' => 0, 'expiresAt' => 1, 'wrongAttempts' => 0];
        $terminal = ['id' => str_repeat('a', 64), 'purpose' => 'login', 'reason' => 'consumed', 'terminalAt' => 1, 'purgeAt' => 2];
        foreach ([[[$record, $record], []], [[$record], [$terminal]], [[], [$terminal, $terminal]]] as [$active, $finished]) {
            $payload = json_encode(['schema' => 1, 'active' => $active, 'terminal' => $finished], JSON_THROW_ON_ERROR);
            $json = json_encode(['schema' => 1, 'active' => $active, 'terminal' => $finished,
                'receipts' => [self::receipt('1', null, $payload)]], JSON_THROW_ON_ERROR);
            try {
                SessionBucketCodec::decode($json);
                self::fail('Duplicate challenge ID was accepted.');
            } catch (NativeSessionStorageException $exception) {
                self::assertSame('malformed_state', $exception->reason->value);
            }
        }
    }

    #[TestDox('Недопустимые типы, значения и размеры не принимаются')]
    public function testInvalidValuesAndSize(): void
    {
        $valid = self::valid();
        foreach ([
            str_replace('"schema":1', '"schema":"1"', $valid),
            str_replace('"receipts"', '"other"', $valid),
            str_replace('"token":"' . str_repeat('1', 64) . '"', '"token":"bad"', $valid),
            str_repeat(' ', 65537),
        ] as $bad) {
            try {
                SessionBucketCodec::decode($bad);
                self::fail('Invalid package state was accepted.');
            } catch (NativeSessionStorageException $exception) {
                self::assertContains($exception->reason->value, ['malformed_state', 'size_limit']);
            }
        }
    }

    #[TestDox('Некорректные перечисления, время и счётчик отклоняются как повреждённые записи')]
    public function testInvalidRecordFields(): void
    {
        $record = ['id' => str_repeat('a', 64), 'purpose' => 'login', 'kind' => 'text_image',
            'issuedAt' => 0, 'expiresAt' => 1, 'wrongAttempts' => 0];
        $variants = [];
        foreach ([['kind', 'unknown'], ['issuedAt', -1], ['expiresAt', 0], ['wrongAttempts', -1], ['purpose', 'Bad']] as [$field, $value]) {
            $bad = $record;
            $bad[$field] = $value;
            $variants[] = $bad;
        }
        foreach ($variants as $bad) {
            $payload = json_encode(['schema' => 1, 'active' => [$bad], 'terminal' => []], JSON_THROW_ON_ERROR);
            $json = json_encode(['schema' => 1, 'active' => [$bad], 'terminal' => [],
                'receipts' => [self::receipt('1', null, $payload)]], JSON_THROW_ON_ERROR);
            try {
                SessionBucketCodec::decode($json);
                self::fail('Invalid active challenge was accepted.');
            } catch (NativeSessionStorageException $exception) {
                self::assertSame('malformed_state', $exception->reason->value);
            }
        }
    }

    private static function valid(): string
    {
        $bucket = new ChallengeBucket();
        $payload = SessionBucketCodec::encodePayload($bucket);

        return SessionBucketCodec::encodeNamespace($bucket, [self::receipt('1', null, $payload)]);
    }

    /** @return array{token: string, parent: ?string, payloadDigest: string} */
    private static function receipt(string $digit, ?string $parent, string $payload): array
    {
        return ['token' => str_repeat($digit, 64), 'parent' => $parent, 'payloadDigest' => hash('sha256', $payload)];
    }

    private static function record(string $digit): ActiveChallenge
    {
        return new ActiveChallenge(ChallengeId::fromString(str_repeat($digit, 64)), new Purpose('login'), ChallengeKind::TextImage, 0, 1);
    }
}
