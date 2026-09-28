<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Support\NativeSession;

use RuntimeException;

final class NativeSessionProcess
{
    /** @return array{array<string, mixed>, array<string, mixed>} */
    public static function concurrent(string $directory, string $id, string $serializer, string $operation): array
    {
        $first = self::start($directory, $id, $serializer, 'wait_' . $operation);
        self::awaitMarker($directory . '/first-ready');
        $second = self::start($directory, $id, $serializer, 'queue_' . $operation);
        self::awaitMarker($directory . '/second-ready');
        file_put_contents($directory . '/first-release', 'go');

        return [self::finish($first), self::finish($second)];
    }

    /** @return list<array<string, mixed>> */
    public static function interposed(string $directory, string $id, string $serializer, string $mode, int $followers): array
    {
        $writer = self::start($directory, $id, $serializer, 'interposed_' . $mode);
        self::awaitMarker($directory . '/fork-ready');
        $queued = self::queueFollowers($directory, $id, $serializer, $followers);
        file_put_contents($directory . '/fork-release', 'go');

        $results = [self::finish($writer)];
        foreach ($queued as $process) {
            $results[] = self::finish($process);
        }

        return $results;
    }

    /** @return array{array<string, mixed>, array<string, mixed>} */
    public static function identityReplacement(string $directory, string $id, string $serializer): array
    {
        $writer = self::start($directory, $id, $serializer, 'wait_wrong');
        self::awaitMarker($directory . '/first-ready');
        $peer = self::start($directory, $id, $serializer, 'identity_peer');
        self::awaitMarker($directory . '/second-ready');
        file_put_contents($directory . '/first-release', 'go');

        return [self::finish($writer), self::finish($peer)];
    }

    /** @return array<string, mixed> */
    public static function run(string $directory, string $id, string $serializer, string $operation): array
    {
        return self::finish(self::start($directory, $id, $serializer, $operation));
    }

    /** @return array{resource, array<int, resource>} */
    private static function start(string $directory, string $id, string $serializer, string $operation): array
    {
        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/worker.php', $directory, $id, $serializer, $operation],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Native session worker could not start.');
        }
        fclose($pipes[0]);

        return [$process, $pipes];
    }

    /**
     * @param array{resource, array<int, resource>} $worker
     * @return array<string, mixed>
     */
    private static function finish(array $worker): array
    {
        [$process, $pipes] = $worker;
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        if ($exit !== 0 || !is_string($output) || !is_string($error)) {
            throw new RuntimeException('Native session worker failed: ' . $error);
        }
        $result = json_decode(str_starts_with($output, 'HEADER_SENT_MARKER') ? substr($output, 18) : $output, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($result)) {
            throw new RuntimeException('Native session worker returned an invalid result.');
        }

        $typed = [];
        foreach ($result as $key => $value) {
            if (!is_string($key)) {
                throw new RuntimeException('Native session worker returned an invalid result.');
            }
            $typed[$key] = $value;
        }

        return $typed;
    }

    private static function awaitMarker(string $path): void
    {
        $deadline = microtime(true) + 10;
        while (!is_file($path)) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Native session worker barrier timed out.');
            }
            usleep(1000);
        }
    }

    /**
     * @return list<array{resource, array<int, resource>}>
     */
    private static function queueFollowers(string $directory, string $id, string $serializer, int $count): array
    {
        $queued = [];
        for ($i = 0; $i < $count; ++$i) {
            $number = 2 + $i;
            $process = self::start($directory, $id, $serializer, 'queue' . $number . '_wrong');
            self::awaitMarker($directory . '/queue' . $number . '-ready');
            self::awaitLockWaiter($process[0]);
            $queued[] = $process;
        }

        return $queued;
    }

    /** @param resource $process */
    private static function awaitLockWaiter($process): void
    {
        $pid = proc_get_status($process)['pid'];
        $deadline = microtime(true) + 10;
        while (true) {
            $locks = file('/proc/locks', FILE_IGNORE_NEW_LINES);
            foreach ($locks ?: [] as $line) {
                if (str_contains($line, '-> FLOCK') && preg_match('/\s' . $pid . '\s/', $line) === 1) {
                    return;
                }
            }
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Native session lock waiter could not be confirmed.');
            }
            usleep(1000);
        }
    }
}
