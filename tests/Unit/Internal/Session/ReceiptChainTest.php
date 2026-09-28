<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Unit\Internal\Session;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\Purpose;
use Yaleksandr\HumanGate\Internal\Session\ReceiptChain;
use Yaleksandr\HumanGate\Internal\Session\SessionBucketCodec;
use Yaleksandr\HumanGate\Session\NativeSessionStorageException;
use Yaleksandr\HumanGate\State\ActiveChallenge;
use Yaleksandr\HumanGate\State\ChallengeBucket;

#[TestDox('Цепочка квитанций подтверждает собственную запись')]
final class ReceiptChainTest extends TestCase
{
    #[TestDox('Последняя квитанция требует полного совпадения состояния')]
    public function testLatestWriterRequiresExactBytes(): void
    {
        $bucket = new ChallengeBucket();
        $payload = SessionBucketCodec::encodePayload($bucket);
        $receipts = [self::receipt('a', null, $payload)];
        $candidate = SessionBucketCodec::encodeNamespace($bucket, $receipts);
        ReceiptChain::prove($receipts, str_repeat('a', 64), hash('sha256', $payload), $candidate, $candidate);

        $this->expectException(NativeSessionStorageException::class);
        ReceiptChain::prove($receipts, str_repeat('a', 64), hash('sha256', $payload), $candidate, $candidate . ' ');
    }

    #[TestDox('Отложенная проверка проходит только при сохранённой квитанции и целой цепочке')]
    public function testDelayedWriter(): void
    {
        $bucket = new ChallengeBucket();
        $payload = SessionBucketCodec::encodePayload($bucket);
        $a = self::receipt('a', null, $payload);
        $b = self::receipt('b', $a['token'], $payload);
        $c = self::receipt('c', $b['token'], $payload);
        $candidate = SessionBucketCodec::encodeNamespace($bucket, [$a]);
        $later = SessionBucketCodec::encodeNamespace($bucket, [$a, $b, $c]);
        ReceiptChain::prove(SessionBucketCodec::decode($later)['receipts'], $a['token'], $a['payloadDigest'], $candidate, $later);

        $this->expectException(NativeSessionStorageException::class);
        ReceiptChain::prove([$b, $c], $a['token'], $a['payloadDigest'], $candidate, $later);
    }

    #[TestDox('Сломанная цепочка, повтор токена и неверный итоговый хеш отклоняются')]
    public function testInvalidChain(): void
    {
        $bucket = new ChallengeBucket();
        $payload = SessionBucketCodec::encodePayload($bucket);
        $a = self::receipt('a', null, $payload);
        $b = self::receipt('b', $a['token'], $payload);
        foreach ([
            [$a, self::receipt('b', null, $payload)],
            [$a, $a],
            [$a, self::receipt('b', $a['token'], 'wrong')],
            [self::receipt('z', null, $payload)],
            [self::receipt('a', str_repeat('a', 64), $payload)],
        ] as $bad) {
            try {
                ReceiptChain::validate($bad, $payload);
                self::fail('Broken receipt chain was accepted.');
            } catch (NativeSessionStorageException $exception) {
                self::assertSame('malformed_state', $exception->reason->value);
            }
        }
        ReceiptChain::validate([$a, $b], $payload);
        ReceiptChain::validate([self::receipt('b', str_repeat('a', 64), $payload)], $payload);
    }

    #[TestDox('При переполнении первыми вытесняются старейшие квитанции')]
    public function testOldestFirstEviction(): void
    {
        $bucket = new ChallengeBucket();
        for ($i = 0; $i < 225; ++$i) {
            $bucket->setActive(new ActiveChallenge(ChallengeId::fromString(sprintf('%064x', $i)), new Purpose('login'), ChallengeKind::TextImage, 0, 1));
        }
        $payload = SessionBucketCodec::encodePayload($bucket);
        $receipts = [];
        for ($i = 1; $i <= 150; ++$i) {
            $receipts[] = self::receipt(sprintf('%064x', $i), $i === 1 ? null : sprintf('%064x', $i - 1), $payload, false);
        }
        $fitted = ReceiptChain::appendAndFit($bucket, $receipts, $payload);
        self::assertLessThan(count($receipts) + 1, count($fitted));
        self::assertSame($receipts[count($receipts) - 1]['token'], $fitted[count($fitted) - 1]['parent']);
        $evicted = count($receipts) + 1 - count($fitted);
        self::assertSame(array_slice($receipts, $evicted), array_slice($fitted, 0, -1));
        self::assertLessThanOrEqual(65536, strlen(SessionBucketCodec::encodeNamespace($bucket, $fitted)));
        self::assertGreaterThan(65536, strlen(SessionBucketCodec::encodeNamespace($bucket, array_merge([$receipts[$evicted - 1]], $fitted), false)));
    }

    #[TestDox('Состояние больше лимита даже с одной квитанцией отклоняется')]
    public function testOverflowBeforeCommit(): void
    {
        $bucket = new ChallengeBucket();
        for ($i = 0; $i < 500; ++$i) {
            $bucket->setActive(new ActiveChallenge(ChallengeId::fromString(sprintf('%064x', $i)), new Purpose('login'), ChallengeKind::TextImage, 0, 1));
        }
        $this->expectException(NativeSessionStorageException::class);
        ReceiptChain::appendAndFit($bucket, [], SessionBucketCodec::encodePayload($bucket));
    }

    /** @return array{token: string, parent: ?string, payloadDigest: string} */
    private static function receipt(string $token, ?string $parent, string $payload, bool $repeat = true): array
    {
        return ['token' => $repeat ? str_repeat($token, 64) : $token, 'parent' => $parent, 'payloadDigest' => hash('sha256', $payload)];
    }
}
