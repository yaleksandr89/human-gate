<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Internal\Session;

use Random\RandomException;
use Yaleksandr\HumanGate\Session\NativeSessionStorageException;
use Yaleksandr\HumanGate\Session\NativeSessionStorageFailureReason;
use Yaleksandr\HumanGate\State\ChallengeBucket;

/**
 * EN: Validates retained writer ancestry and proves participation in a verified native write.
 * RU: Проверяет цепочку сохранённых квитанций и подтверждает участие автора в проверенной записи сессии.
 */
final class ReceiptChain
{
    /**
     * @param list<array{token: string, parent: ?string, payloadDigest: string}> $receipts
     * @return list<array{token: string, parent: ?string, payloadDigest: string}>
     */
    public static function appendAndFit(ChallengeBucket $bucket, array $receipts, string $payload): array
    {
        try {
            $token = bin2hex(random_bytes(32));
        } catch (RandomException $exception) {
            throw new NativeSessionStorageException(NativeSessionStorageFailureReason::Randomness, $exception);
        }

        $last = $receipts === [] ? null : $receipts[array_key_last($receipts)]['token'];
        $receipts[] = ['token' => $token, 'parent' => $last, 'payloadDigest' => hash('sha256', $payload)];

        while (strlen(SessionBucketCodec::encodeNamespace($bucket, $receipts, false)) > SessionBucketCodec::NAMESPACE_LIMIT) {
            if (count($receipts) === 1) {
                throw new NativeSessionStorageException(NativeSessionStorageFailureReason::SizeLimit);
            }
            array_shift($receipts);
        }

        return $receipts;
    }

    /** @return list<array{token: string, parent: ?string, payloadDigest: string}> */
    public static function validate(mixed $receipts, string $payload): array
    {
        if (!is_array($receipts) || !array_is_list($receipts) || $receipts === []) {
            self::malformed();
        }

        $seen = [];
        $validated = [];
        $previous = null;
        foreach ($receipts as $index => $receipt) {
            if (!is_array($receipt) || array_keys($receipt) !== ['token', 'parent', 'payloadDigest']
                || !is_string($receipt['token']) || !self::hex($receipt['token'])
                || !is_string($receipt['payloadDigest']) || !self::hex($receipt['payloadDigest'])
                || ($receipt['parent'] !== null && (!is_string($receipt['parent']) || !self::hex($receipt['parent'])))
                || isset($seen[$receipt['token']])
                || ($index > 0 && $receipt['parent'] !== $previous)) {
                self::malformed();
            }
            $seen[$receipt['token']] = true;
            $previous = $receipt['token'];
            $validated[] = ['token' => $receipt['token'], 'parent' => $receipt['parent'], 'payloadDigest' => $receipt['payloadDigest']];
        }

        $firstParent = $validated[0]['parent'];
        if ($firstParent !== null && isset($seen[$firstParent])) {
            self::malformed();
        }
        if ($validated[count($validated) - 1]['payloadDigest'] !== hash('sha256', $payload)) {
            self::malformed();
        }

        return $validated;
    }

    /**
     * @param list<array{token: string, parent: ?string, payloadDigest: string}> $receipts
     */
    public static function prove(array $receipts, string $token, string $digest, string $candidate, string $reopened): void
    {
        foreach ($receipts as $index => $receipt) {
            if ($receipt['token'] !== $token) {
                continue;
            }
            if ($receipt['payloadDigest'] !== $digest) {
                break;
            }
            if ($index === array_key_last($receipts) && $candidate !== $reopened) {
                break;
            }

            return;
        }

        throw new NativeSessionStorageException(NativeSessionStorageFailureReason::Verification);
    }

    private static function hex(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A[0-9a-f]{64}\z/', $value) === 1;
    }

    private static function malformed(): never
    {
        throw new NativeSessionStorageException(NativeSessionStorageFailureReason::MalformedState);
    }
}
