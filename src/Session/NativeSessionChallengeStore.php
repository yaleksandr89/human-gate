<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Session;

use Closure;
use LogicException;
use Yaleksandr\HumanGate\Internal\Session\ReceiptChain;
use Yaleksandr\HumanGate\Internal\Session\SessionBucketCodec;
use Yaleksandr\HumanGate\Port\ChallengeStore;

/**
 * EN: Stores one challenge bucket in an already active native files session.
 * RU: Хранит контейнер задач в уже активной файловой сессии PHP.
 */
final class NativeSessionChallengeStore implements ChallengeStore
{
    private bool $inProgress = false;

    public function __construct(private readonly ActiveNativeSessionScope $scope) {}

    public function atomic(Closure $transition): mixed
    {
        if ($this->inProgress) {
            throw new LogicException('Nested atomic calls are unsupported.');
        }

        $this->inProgress = true;
        try {
            $this->scope->assertActive();
            $before = array_key_exists(SessionBucketCodec::SESSION_KEY, $_SESSION)
                ? SessionBucketCodec::decode($_SESSION[SessionBucketCodec::SESSION_KEY])
                : SessionBucketCodec::empty();
            $working = clone $before['bucket'];
            $result = $transition($working);
            $payload = SessionBucketCodec::encodePayload($working);
            if ($payload === $before['payload']) {
                return $result;
            }

            $receipts = ReceiptChain::appendAndFit($working, $before['receipts'], $payload);
            $candidate = SessionBucketCodec::encodeNamespace($working, $receipts);
            if (headers_sent()) {
                throw new NativeSessionStorageException(NativeSessionStorageFailureReason::HeadersSent);
            }
            $this->scope->assertActive();

            $_SESSION[SessionBucketCodec::SESSION_KEY] = $candidate;
            $reopened = SessionBucketCodec::decode($this->scope->commitAndRead());
            $own = $receipts[count($receipts) - 1];
            ReceiptChain::prove($reopened['receipts'], $own['token'], $own['payloadDigest'], $candidate, $reopened['namespace']);

            return $result;
        } finally {
            $this->inProgress = false;
        }
    }
}
