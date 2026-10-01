<?php

declare(strict_types=1);

use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\Policy;
use Yaleksandr\HumanGate\Challenge\Purpose;
use Yaleksandr\HumanGate\ChallengeService;
use Yaleksandr\HumanGate\Internal\AnswerDigest;
use Yaleksandr\HumanGate\Internal\ChallengeLifecycle;
use Yaleksandr\HumanGate\Internal\Session\SessionBucketCodec;
use Yaleksandr\HumanGate\Port\TextImageRenderer;
use Yaleksandr\HumanGate\Presentation\ImagePresentation;
use Yaleksandr\HumanGate\Session\ActiveNativeSessionScope;
use Yaleksandr\HumanGate\Session\NativeSessionChallengeStore;
use Yaleksandr\HumanGate\Session\NativeSessionStorageException;
use Yaleksandr\HumanGate\State\ActiveChallenge;
use Yaleksandr\HumanGate\State\AnswerProof;
use Yaleksandr\HumanGate\State\ChallengeBucket;
use Yaleksandr\HumanGate\Tests\Support\FrozenClock;
use Yaleksandr\HumanGate\TextImage\TextImageStrategy;

require __DIR__ . '/../../../vendor/autoload.php';

final class NativeSessionInfrastructureException extends RuntimeException {}

function writeNativeSessionFile(string $path, string $bytes): void
{
    if (file_put_contents($path, $bytes) !== strlen($bytes)) {
        throw new NativeSessionInfrastructureException('Could not write complete native session test file: ' . $path);
    }
}

function awaitNativeSessionMarker(string $path): void
{
    $deadline = microtime(true) + 10;
    while (!is_file($path)) {
        if (microtime(true) >= $deadline) {
            throw new NativeSessionInfrastructureException('Native session barrier timed out: ' . $path);
        }
        usleep(1000);
    }
}

final class NativeSessionWriteInterposer
{
    public static ?string $directory = null;
    public static ?string $id = null;
    public static ?string $before = null;
    public static bool $hybrid = false;
    public static ?int $child = null;

    public static function cleanup(): void
    {
        if (self::$child !== null) {
            posix_kill(self::$child, SIGKILL);
            pcntl_waitpid(self::$child, $status);
            self::$child = null;
        }
    }

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
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, static function (): never {
            exit(1);
        });
        $child = pcntl_fork();
        if ($child === -1) {
            throw new NativeSessionInfrastructureException('Could not fork native session interposer.');
        }
        if ($child === 0) {
            try {
                writeNativeSessionFile($directory . '/fork-ready', 'ready');
                awaitNativeSessionMarker($directory . '/fork-release');
                $deadline = microtime(true) + 10;
                $path = $directory . '/sess_' . $id;
                while (true) {
                    $bytes = file_get_contents($path);
                    $start = is_string($bytes) ? strpos($bytes, '{"schema":2') : false;
                    if ($start !== false && preg_match('/\{"schema":2,.*\]\}/s', substr($bytes, $start), $match) === 1 && $match[0] !== $before) {
                        try {
                            $decoded = SessionBucketCodec::decode($match[0]);
                            break;
                        } catch (NativeSessionStorageException) {
                        }
                    }
                    if (microtime(true) >= $deadline) {
                        throw new NativeSessionInfrastructureException('Native session interposer write timed out.');
                    }
                    usleep(1000);
                }
                if ($hybrid) {
                    $bucket = $decoded['bucket'];
                    $target = $bucket->active(ChallengeId::fromString(str_repeat('a', 64)));
                    if ($target === null) {
                        throw new NativeSessionInfrastructureException('Missing hybrid session target.');
                    }
                    $bucket->setActive(new ActiveChallenge($target->id, $target->purpose, $target->kind, $target->issuedAt, $target->expiresAt, $target->proof));
                    $receipts = $decoded['receipts'];
                    $last = array_pop($receipts);
                    if ($last === null) {
                        throw new NativeSessionInfrastructureException('Missing native session receipt.');
                    }
                    $receipts[] = ['token' => $last['token'], 'parent' => $last['parent'],
                        'payloadDigest' => hash('sha256', SessionBucketCodec::encodePayload($bucket))];
                    $replacement = SessionBucketCodec::encodeNamespace($bucket, $receipts);
                    if (strlen($replacement) !== strlen($match[0])) {
                        throw new NativeSessionInfrastructureException('Hybrid session replacement length differs.');
                    }
                    $rewritten = substr_replace($bytes, $replacement, $start, strlen($match[0]));
                    writeNativeSessionFile($path, $rewritten);
                }
                writeNativeSessionFile($directory . '/fork-done', 'done');
            } catch (Throwable $exception) {
                fwrite(STDERR, 'Native session interposer failed: ' . $exception->getMessage() . "\n");
            } finally {
                posix_kill(posix_getpid(), SIGKILL);
            }
        }
        self::$child = $child;
        awaitNativeSessionMarker($directory . '/fork-release');

        return [];
    }

    /** @param array<string, mixed> $data */
    public function __unserialize(array $data): void {}
}

register_shutdown_function(NativeSessionWriteInterposer::cleanup(...));

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
try {
    if (str_starts_with($operation, 'queue_')) {
        writeNativeSessionFile($directory . '/second-ready', 'ready');
        $operation = substr($operation, 6);
    }
    if (preg_match('/\Aqueue([0-9]+)_(.+)\z/', $operation, $queued) === 1) {
        writeNativeSessionFile($directory . '/queue' . $queued[1] . '-ready', 'ready');
        $operation = $queued[2];
    }

    if ($operation === 'identity_peer') {
        if (!unlink($directory . '/sess_' . $id)) {
            throw new NativeSessionInfrastructureException('Could not remove native session identity file.');
        }
        writeNativeSessionFile($directory . '/second-ready', 'ready');
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
        writeNativeSessionFile($directory . '/first-ready', 'ready');
        awaitNativeSessionMarker($directory . '/first-release');
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
        $_SESSION[SessionBucketCodec::SESSION_KEY] = '{"schema":2,"active":[],"terminal":[],"receipts":[]}';
        session_write_close();
        $result = ['status' => session_status()];
    } elseif ($operation === 'seed_eviction') {
        $bucket = new ChallengeBucket();
        $bucket->setActive(new ActiveChallenge(ChallengeId::fromString(str_repeat('a', 64)), new Purpose('login'), ChallengeKind::TextImage, 0, 2000, new AnswerProof(1, str_repeat('a', 64))));
        $saved = '';
        for ($number = 0; $number < 500; ++$number) {
            $bucket->setActive(new ActiveChallenge(ChallengeId::fromString(sprintf('%064x', $number)), new Purpose('login'), ChallengeKind::TextImage, 0, 2000, new AnswerProof(1, str_repeat('a', 64))));
            $payload = SessionBucketCodec::encodePayload($bucket);
            $one = [['token' => str_repeat('0', 64), 'parent' => null, 'payloadDigest' => hash('sha256', $payload)]];
            $candidate = SessionBucketCodec::encodeNamespace($bucket, $one, false);
            if (strlen($candidate) > 65536) {
                break;
            }
            $saved = $candidate;
            if (strlen($candidate) > 65200) {
                break;
            }
        }
        for ($number = 0; $number < 2; ++$number) {
            $bucket->setActive(new ActiveChallenge(ChallengeId::fromString(sprintf('%064x', $number)), new Purpose(str_repeat('a', 64)), ChallengeKind::TextImage, 0, 2000, new AnswerProof(1, str_repeat('a', 64))));
        }
        $payload = SessionBucketCodec::encodePayload($bucket);
        $saved = SessionBucketCodec::encodeNamespace($bucket, [['token' => str_repeat('0', 64), 'parent' => null, 'payloadDigest' => hash('sha256', $payload)]]);
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
        $proof = new AnswerProof(1, str_repeat('a', 64));
        $result = [];
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
        if ($operation === 'seed_service') {
            $knownProof = new AnswerProof(1, AnswerDigest::forAnswer($challengeId, $purpose, ChallengeKind::TextImage, '234567'));
            $store->atomic(static function (ChallengeBucket $bucket) use ($challengeId, $purpose, $knownProof): void {
                $bucket->setActive(new ActiveChallenge($challengeId, $purpose, ChallengeKind::TextImage, 1000, 2000, $knownProof));
            });
            $result = ['seeded' => true];
        } elseif ($operation === 'verify_correct') {
            $renderer = new class implements TextImageRenderer {
                public function render(string $canonicalAnswer): ImagePresentation
                {
                    throw new RuntimeException('Verification must not render.');
                }
            };
            $service = new ChallengeService($store, new Policy(), new FrozenClock(1000), new TextImageStrategy($renderer));
            $result = ['code' => $service->verify($challengeId, $purpose, '234567')->value];
        } elseif ($operation === 'nested') {
            $original = $_SESSION[SessionBucketCodec::SESSION_KEY] ?? null;
            try {
                $store->atomic(static function (ChallengeBucket $bucket) use ($store, $lifecycle, $challengeId, $purpose, $proof): void {
                    $lifecycle->issue($bucket, $challengeId, $purpose, ChallengeKind::TextImage, $proof);
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
                $store->atomic(static function (ChallengeBucket $bucket) use ($lifecycle, $challengeId, $purpose, $proof): never {
                    $lifecycle->issue($bucket, $challengeId, $purpose, ChallengeKind::TextImage, $proof);
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
            $issued = $store->atomic(static fn(ChallengeBucket $bucket) => $lifecycle->issue($bucket, $challengeId, $purpose, ChallengeKind::TextImage, $proof));
            $result = ['noop' => $noop, 'code' => $issued->code->value, 'closed' => session_status() === PHP_SESSION_NONE];
        } else {
            $transition = match ($operation) {
                'issue' => static fn(ChallengeBucket $bucket) => $lifecycle->issue($bucket, $challengeId, $purpose, ChallengeKind::TextImage, $proof),
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
} catch (NativeSessionInfrastructureException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
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

if (NativeSessionWriteInterposer::$child !== null) {
    try {
        awaitNativeSessionMarker($directory . '/fork-done');
    } catch (NativeSessionInfrastructureException $exception) {
        fwrite(STDERR, $exception->getMessage() . "\n");
        exit(1);
    } finally {
        NativeSessionWriteInterposer::cleanup();
    }
}

echo json_encode($result, JSON_THROW_ON_ERROR);
