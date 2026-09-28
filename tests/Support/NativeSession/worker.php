<?php

declare(strict_types=1);

use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\Policy;
use Yaleksandr\HumanGate\Challenge\Purpose;
use Yaleksandr\HumanGate\Internal\ChallengeLifecycle;
use Yaleksandr\HumanGate\Internal\Session\SessionBucketCodec;
use Yaleksandr\HumanGate\Session\ActiveNativeSessionScope;
use Yaleksandr\HumanGate\Session\NativeSessionChallengeStore;
use Yaleksandr\HumanGate\Session\NativeSessionStorageException;
use Yaleksandr\HumanGate\State\ActiveChallenge;
use Yaleksandr\HumanGate\State\ChallengeBucket;
use Yaleksandr\HumanGate\Tests\Support\FrozenClock;

require __DIR__ . '/../../../vendor/autoload.php';

final class NativeSessionWriteInterposer
{
    public static ?string $directory = null;
    public static ?string $id = null;
    public static ?string $before = null;
    public static bool $hybrid = false;

    /** @return array<string, mixed> */
    public function __serialize(): array
    {
        if (self::$directory === null || self::$id === null || self::$before === null) {
            return [];
        }
        $directory = self::$directory;
        $id = self::$id;
        $before = self::$before;
        $hybrid = self::$hybrid;
        self::$directory = null;
        $child = pcntl_fork();
        if ($child === -1) {
            throw new RuntimeException('Could not fork native session interposer.');
        }
        if ($child === 0) {
            file_put_contents($directory . '/fork-ready', 'ready');
            $deadline = microtime(true) + 10;
            while (!is_file($directory . '/fork-release')) {
                if (microtime(true) >= $deadline) {
                    posix_kill(posix_getpid(), SIGKILL);
                }
                usleep(1000);
            }
            $path = $directory . '/sess_' . $id;
            while (true) {
                $bytes = file_get_contents($path);
                $start = is_string($bytes) ? strpos($bytes, '{"schema":1') : false;
                if ($start !== false && preg_match('/\{"schema":1,.*\]\}/s', substr($bytes, $start), $match) === 1 && $match[0] !== $before) {
                    try {
                        $decoded = SessionBucketCodec::decode($match[0]);
                        break;
                    } catch (NativeSessionStorageException) {
                    }
                }
                if (microtime(true) >= $deadline) {
                    posix_kill(posix_getpid(), SIGKILL);
                }
                usleep(1000);
            }
            if ($hybrid) {
                $bucket = $decoded['bucket'];
                $target = $bucket->active(ChallengeId::fromString(str_repeat('a', 64)));
                if ($target !== null) {
                    $bucket->setActive(new ActiveChallenge($target->id, $target->purpose, $target->kind, $target->issuedAt, $target->expiresAt));
                    $receipts = $decoded['receipts'];
                    $last = array_pop($receipts);
                    if ($last === null) {
                        posix_kill(posix_getpid(), SIGKILL);
                        throw new RuntimeException('Missing native session receipt.');
                    }
                    $receipts[] = ['token' => $last['token'], 'parent' => $last['parent'],
                        'payloadDigest' => hash('sha256', SessionBucketCodec::encodePayload($bucket))];
                    $replacement = SessionBucketCodec::encodeNamespace($bucket, $receipts);
                    if (strlen($replacement) === strlen($match[0])) {
                        $rewritten = substr_replace($bytes, $replacement, $start, strlen($match[0]));
                        file_put_contents($path, $rewritten);
                    }
                }
            }
            file_put_contents($directory . '/fork-done', 'done');
            posix_kill(posix_getpid(), SIGKILL);
        }
        while (!is_file($directory . '/fork-release')) {
            usleep(1000);
        }

        return [];
    }

    /** @param array<string, mixed> $data */
    public function __unserialize(array $data): void {}
}

$arguments = $_SERVER['argv'] ?? null;
if (!is_array($arguments) || !isset($arguments[1], $arguments[2], $arguments[3], $arguments[4])
    || !is_string($arguments[1]) || !is_string($arguments[2]) || !is_string($arguments[3]) || !is_string($arguments[4])) {
    exit(1);
}
[, $directory, $id, $serializer, $operation] = $arguments;
ini_set('session.save_handler', 'files');
ini_set('session.save_path', $directory);
ini_set('session.serialize_handler', $operation === 'unsupported_serializer' ? 'php_binary' : $serializer);
ini_set('session.use_strict_mode', in_array($operation, ['init', 'unsupported_serializer'], true) ? '0' : '1');
session_name('HGTEST');
session_id($id);
if (str_starts_with($operation, 'queue_')) {
    file_put_contents($directory . '/second-ready', 'ready');
    $operation = substr($operation, 6);
}
if (preg_match('/\Aqueue([0-9]+)_(.+)\z/', $operation, $queued) === 1) {
    file_put_contents($directory . '/queue' . $queued[1] . '-ready', 'ready');
    $operation = $queued[2];
}

try {
    if ($operation === 'identity_peer') {
        unlink($directory . '/sess_' . $id);
        file_put_contents($directory . '/second-ready', 'ready');
        echo json_encode(['removed' => true], JSON_THROW_ON_ERROR);
        exit;
    }
    if (!session_start()) {
        throw new RuntimeException('Application session could not start.');
    }
    if ($operation === 'inactive_capture') {
        session_write_close();
        ActiveNativeSessionScope::capture();
    }
    if (str_starts_with($operation, 'wait_')) {
        file_put_contents($directory . '/first-ready', 'ready');
        $deadline = microtime(true) + 10;
        while (!is_file($directory . '/first-release')) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Native session barrier timed out.');
            }
            usleep(1000);
        }
        $operation = substr($operation, 5);
    }
    if ($operation === 'init') {
        $_SESSION['app'] = 'untouched';
        session_write_close();
        $result = ['status' => session_status()];
    } elseif ($operation === 'inspect') {
        $result = ['status' => session_status(), 'app' => $_SESSION['app'] ?? null,
            'raw' => $_SESSION[SessionBucketCodec::SESSION_KEY] ?? null];
        session_write_close();
    } elseif ($operation === 'inject') {
        $_SESSION[SessionBucketCodec::SESSION_KEY] = '{"schema":1,"active":[],"terminal":[],"receipts":[]}';
        session_write_close();
        $result = ['status' => session_status()];
    } elseif ($operation === 'seed_eviction') {
        $bucket = new ChallengeBucket();
        $bucket->setActive(new ActiveChallenge(ChallengeId::fromString(str_repeat('a', 64)), new Purpose('login'), ChallengeKind::TextImage, 0, 2000));
        $saved = '';
        for ($number = 0; $number < 500; ++$number) {
            $bucket->setActive(new ActiveChallenge(ChallengeId::fromString(sprintf('%064x', $number)), new Purpose('login'), ChallengeKind::TextImage, 0, 2000));
            $payload = SessionBucketCodec::encodePayload($bucket);
            $one = [['token' => str_repeat('0', 64), 'parent' => null, 'payloadDigest' => hash('sha256', $payload)]];
            $candidate = SessionBucketCodec::encodeNamespace($bucket, $one, false);
            if (strlen($candidate) > 65536) {
                break;
            }
            $saved = $candidate;
            if (strlen($candidate) > 65310) {
                break;
            }
        }
        if (strlen($saved) <= 65310) {
            throw new RuntimeException('Could not seed receipt eviction boundary.');
        }
        $_SESSION[SessionBucketCodec::SESSION_KEY] = $saved;
        session_write_close();
        $result = ['bytes' => strlen($saved)];
    } elseif ($operation === 'arm_interposer') {
        $_SESSION['app_interposer'] = new NativeSessionWriteInterposer();
        session_write_close();
        $result = ['armed' => true];
    } else {
        if (str_starts_with($operation, 'interposed_')) {
            $before = $_SESSION[SessionBucketCodec::SESSION_KEY] ?? null;
            if (!is_string($before)) {
                throw new RuntimeException('Interposer requires package state.');
            }
            NativeSessionWriteInterposer::$directory = $directory;
            NativeSessionWriteInterposer::$id = $id;
            NativeSessionWriteInterposer::$before = $before;
            NativeSessionWriteInterposer::$hybrid = $operation === 'interposed_hybrid';
            $operation = 'wrong';
        }
        $scope = ActiveNativeSessionScope::capture();
        $store = new NativeSessionChallengeStore($scope);
        $lifecycle = new ChallengeLifecycle(new Policy(), new FrozenClock(1000));
        $challengeId = ChallengeId::fromString(str_repeat('a', 64));
        $purpose = new Purpose('login');
        if ($operation === 'name_mismatch') {
            session_write_close();
            session_name('OTHER');
            session_id($id);
            session_start();
            $operation = 'issue';
        }
        if ($operation === 'headers') {
            echo 'HEADER_SENT_MARKER';
            flush();
            $operation = 'issue';
        }
        if ($operation === 'nested') {
            $original = $_SESSION[SessionBucketCodec::SESSION_KEY] ?? null;
            try {
                $store->atomic(static function (ChallengeBucket $bucket) use ($store, $lifecycle, $challengeId, $purpose): void {
                    $lifecycle->issue($bucket, $challengeId, $purpose, ChallengeKind::TextImage);
                    $store->atomic(static fn(ChallengeBucket $inner): int => $inner->activeCount());
                });
                throw new RuntimeException('Nested operation was accepted.');
            } catch (LogicException) {
                $result = ['nestedRejected' => true, 'sameKey' => ($_SESSION[SessionBucketCodec::SESSION_KEY] ?? null) === $original,
                    'active' => session_status() === PHP_SESSION_ACTIVE];
            }
            session_write_close();
        } elseif ($operation === 'callback') {
            $original = $_SESSION[SessionBucketCodec::SESSION_KEY] ?? null;
            $_SESSION['app'] = 'changed';
            try {
                $store->atomic(static function (ChallengeBucket $bucket) use ($lifecycle, $challengeId, $purpose): never {
                    $lifecycle->issue($bucket, $challengeId, $purpose, ChallengeKind::TextImage);
                    throw new RuntimeException('callback marker');
                });
            } catch (RuntimeException $exception) {
                $result = ['callback' => $exception->getMessage(), 'sameKey' => ($_SESSION[SessionBucketCodec::SESSION_KEY] ?? null) === $original,
                    'active' => session_status() === PHP_SESSION_ACTIVE, 'app' => $_SESSION['app']];
            }
            session_write_close();
        } elseif ($operation === 'noop_then_issue') {
            $original = $_SESSION[SessionBucketCodec::SESSION_KEY] ?? null;
            $store->atomic(static fn(ChallengeBucket $bucket): int => $bucket->activeCount());
            $noop = ['sameKey' => ($_SESSION[SessionBucketCodec::SESSION_KEY] ?? null) === $original,
                'active' => session_status() === PHP_SESSION_ACTIVE];
            $issued = $store->atomic(static fn(ChallengeBucket $bucket) => $lifecycle->issue($bucket, $challengeId, $purpose, ChallengeKind::TextImage));
            $result = ['noop' => $noop, 'code' => $issued->code->value, 'closed' => session_status() === PHP_SESSION_NONE];
        } else {
            $transition = match ($operation) {
                'issue' => static fn(ChallengeBucket $bucket) => $lifecycle->issue($bucket, $challengeId, $purpose, ChallengeKind::TextImage),
                'consume' => static fn(ChallengeBucket $bucket) => $lifecycle->consume($bucket, $challengeId, $purpose),
                'wrong' => static fn(ChallengeBucket $bucket) => $lifecycle->registerWrongAttempt($bucket, $challengeId, $purpose),
                'lookup' => static fn(ChallengeBucket $bucket) => $lifecycle->lookup($bucket, $challengeId, $purpose),
                default => throw new RuntimeException('Unknown worker operation.'),
            };
            $outcome = $store->atomic($transition);
            $result = ['code' => $outcome->code->value, 'attempts' => $outcome->challenge?->wrongAttempts,
                'closed' => session_status() === PHP_SESSION_NONE, 'strict' => ini_get('session.use_strict_mode'),
                'cookies' => ini_get('session.use_cookies'), 'limiter' => ini_get('session.cache_limiter')];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
        }
    }
} catch (Throwable $exception) {
    $result = ['error' => $exception::class];
    if ($exception instanceof NativeSessionStorageException) {
        $result['reason'] = $exception->reason->value;
    }
    $result['strict'] = ini_get('session.use_strict_mode');
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

echo json_encode($result, JSON_THROW_ON_ERROR);
