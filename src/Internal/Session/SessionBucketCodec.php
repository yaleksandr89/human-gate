<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Internal\Session;

use InvalidArgumentException;
use JsonException;
use ValueError;
use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\Purpose;
use Yaleksandr\HumanGate\Session\NativeSessionStorageException;
use Yaleksandr\HumanGate\Session\NativeSessionStorageFailureReason;
use Yaleksandr\HumanGate\State\ActiveChallenge;
use Yaleksandr\HumanGate\State\ChallengeBucket;
use Yaleksandr\HumanGate\State\ChallengeTombstone;
use Yaleksandr\HumanGate\State\TerminalReason;

/**
 * EN: Strict canonical JSON codec for the package-owned session namespace.
 * RU: Строгий кодек канонического JSON для области сессии, принадлежащей пакету.
 */
final class SessionBucketCodec
{
    public const SESSION_KEY = '__yaleksandr_human_gate';
    public const NAMESPACE_LIMIT = 65536;
    private const ACTIVE_LIMIT = 4096;

    /**
     * @return array{bucket: ChallengeBucket, receipts: list<array{token: string, parent: ?string, payloadDigest: string}>, payload: string, namespace: string}
     */
    public static function empty(): array
    {
        $bucket = new ChallengeBucket();

        return ['bucket' => $bucket, 'receipts' => [], 'payload' => self::encodePayload($bucket), 'namespace' => ''];
    }

    /**
     * @return array{bucket: ChallengeBucket, receipts: list<array{token: string, parent: ?string, payloadDigest: string}>, payload: string, namespace: string}
     */
    public static function decode(mixed $raw): array
    {
        if (!is_string($raw)) {
            self::malformed();
        }
        if (strlen($raw) > self::NAMESPACE_LIMIT) {
            throw new NativeSessionStorageException(NativeSessionStorageFailureReason::SizeLimit);
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new NativeSessionStorageException(NativeSessionStorageFailureReason::MalformedState, $exception);
        }
        if (!is_array($data) || array_keys($data) !== ['schema', 'active', 'terminal', 'receipts'] || $data['schema'] !== 1
            || !is_array($data['active']) || !array_is_list($data['active'])
            || !is_array($data['terminal']) || !array_is_list($data['terminal'])) {
            self::malformed();
        }

        $bucket = new ChallengeBucket();
        $seen = [];
        try {
            foreach ($data['active'] as $record) {
                if (!is_array($record) || array_keys($record) !== ['id', 'purpose', 'kind', 'issuedAt', 'expiresAt', 'wrongAttempts']
                    || !is_string($record['id']) || !is_string($record['purpose']) || !is_string($record['kind'])
                    || !is_int($record['issuedAt']) || !is_int($record['expiresAt']) || !is_int($record['wrongAttempts'])) {
                    self::malformed();
                }
                $active = new ActiveChallenge(
                    ChallengeId::fromString($record['id']),
                    new Purpose($record['purpose']),
                    ChallengeKind::from($record['kind']),
                    $record['issuedAt'],
                    $record['expiresAt'],
                    $record['wrongAttempts'],
                );
                if (isset($seen[$record['id']])) {
                    self::malformed();
                }
                $seen[$record['id']] = true;
                $bucket->setActive($active);
            }
            foreach ($data['terminal'] as $record) {
                if (!is_array($record) || array_keys($record) !== ['id', 'purpose', 'reason', 'terminalAt', 'purgeAt']
                    || !is_string($record['id']) || !is_string($record['purpose']) || !is_string($record['reason'])
                    || !is_int($record['terminalAt']) || !is_int($record['purgeAt'])) {
                    self::malformed();
                }
                $terminal = new ChallengeTombstone(
                    ChallengeId::fromString($record['id']),
                    new Purpose($record['purpose']),
                    TerminalReason::from($record['reason']),
                    $record['terminalAt'],
                    $record['purgeAt'],
                );
                if (isset($seen[$record['id']])) {
                    self::malformed();
                }
                $seen[$record['id']] = true;
                $bucket->setTombstone($terminal);
            }
        } catch (InvalidArgumentException|ValueError $exception) {
            throw new NativeSessionStorageException(NativeSessionStorageFailureReason::MalformedState, $exception);
        }

        $payload = self::encodePayload($bucket);
        $receipts = ReceiptChain::validate($data['receipts'], $payload);
        $canonical = self::encodeNamespace($bucket, $receipts);
        if ($raw !== $canonical) {
            self::malformed();
        }

        return ['bucket' => $bucket, 'receipts' => $receipts, 'payload' => $payload, 'namespace' => $canonical];
    }

    public static function encodePayload(ChallengeBucket $bucket): string
    {
        [$active, $terminal] = self::records($bucket);

        return self::json(['schema' => 1, 'active' => $active, 'terminal' => $terminal]);
    }

    /** @param list<array{token: string, parent: ?string, payloadDigest: string}> $receipts */
    public static function encodeNamespace(ChallengeBucket $bucket, array $receipts, bool $enforceLimit = true): string
    {
        [$active, $terminal] = self::records($bucket);
        $payload = self::json(['schema' => 1, 'active' => $active, 'terminal' => $terminal]);
        ReceiptChain::validate($receipts, $payload);
        $json = self::json(['schema' => 1, 'active' => $active, 'terminal' => $terminal, 'receipts' => $receipts]);
        if ($enforceLimit && strlen($json) > self::NAMESPACE_LIMIT) {
            throw new NativeSessionStorageException(NativeSessionStorageFailureReason::SizeLimit);
        }

        return $json;
    }

    /** @return array{list<array{id: string, purpose: string, kind: string, issuedAt: int, expiresAt: int, wrongAttempts: int}>, list<array{id: string, purpose: string, reason: string, terminalAt: int, purgeAt: int}>} */
    private static function records(ChallengeBucket $bucket): array
    {
        $active = [];
        $terminal = [];
        foreach ($bucket->activeChallenges() as $record) {
            $item = ['id' => $record->id->value(), 'purpose' => $record->purpose->value(), 'kind' => $record->kind->value,
                'issuedAt' => $record->issuedAt, 'expiresAt' => $record->expiresAt, 'wrongAttempts' => $record->wrongAttempts];
            if (strlen(self::json($item)) > self::ACTIVE_LIMIT) {
                throw new NativeSessionStorageException(NativeSessionStorageFailureReason::SizeLimit);
            }
            $active[] = $item;
        }
        foreach ($bucket->tombstones() as $record) {
            $terminal[] = ['id' => $record->id->value(), 'purpose' => $record->purpose->value(), 'reason' => $record->reason->value,
                'terminalAt' => $record->terminalAt, 'purgeAt' => $record->purgeAt];
        }
        usort($active, static fn(array $a, array $b): int => strcmp($a['id'], $b['id']));
        usort($terminal, static fn(array $a, array $b): int => strcmp($a['id'], $b['id']));

        return [$active, $terminal];
    }

    /** @param mixed $value */
    private static function json(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new NativeSessionStorageException(NativeSessionStorageFailureReason::MalformedState, $exception);
        }
    }

    private static function malformed(): never
    {
        throw new NativeSessionStorageException(NativeSessionStorageFailureReason::MalformedState);
    }
}
