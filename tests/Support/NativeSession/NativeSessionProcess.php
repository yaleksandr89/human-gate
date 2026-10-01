<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Support\NativeSession;

use RuntimeException;

final class NativeSessionProcess
{
    /** @return array{array<string, mixed>, array<string, mixed>} */
    public static function concurrent(string $directory, string $id, string $serializer, string $operation): array
    {
        $workers = [];
        try {
            $first = self::start($directory, $id, $serializer, 'wait_' . $operation);
            $workers[] = $first;
            self::awaitMarker($directory . '/first-ready');
            $second = self::start($directory, $id, $serializer, 'queue_' . $operation);
            $workers[] = $second;
            self::awaitMarker($directory . '/second-ready');
            self::writeRelease($directory . '/first-release');

            return [self::finish($first), self::finish($second)];
        } finally {
            self::cleanup($workers);
        }
    }

    /** @return list<array<string, mixed>> */
    public static function interposed(string $directory, string $id, string $serializer, string $mode, int $followers): array
    {
        $workers = [];
        try {
            $writer = self::start($directory, $id, $serializer, 'interposed_' . $mode);
            $workers[] = $writer;
            self::awaitMarker($directory . '/fork-ready');
            $queued = self::queueFollowers($directory, $id, $serializer, $followers, $workers);
            self::writeRelease($directory . '/fork-release');

            $results = [self::finish($writer)];
            foreach ($queued as $process) {
                $results[] = self::finish($process);
            }

            return $results;
        } finally {
            self::cleanup($workers);
        }
    }

    /** @return array{array<string, mixed>, array<string, mixed>} */
    public static function identityReplacement(string $directory, string $id, string $serializer): array
    {
        $workers = [];
        try {
            $writer = self::start($directory, $id, $serializer, 'wait_wrong');
            $workers[] = $writer;
            self::awaitMarker($directory . '/first-ready');
            $peer = self::start($directory, $id, $serializer, 'identity_peer');
            $workers[] = $peer;
            self::awaitMarker($directory . '/second-ready');
            self::writeRelease($directory . '/first-release');

            return [self::finish($writer), self::finish($peer)];
        } finally {
            self::cleanup($workers);
        }
    }

    /** @return array<string, mixed> */
    public static function run(string $directory, string $id, string $serializer, string $operation): array
    {
        $worker = self::start($directory, $id, $serializer, $operation);
        try {
            return self::finish($worker);
        } finally {
            self::cleanup([$worker]);
        }
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
        if (!stream_set_blocking($pipes[1], false) || !stream_set_blocking($pipes[2], false)) {
            throw new RuntimeException('Could not configure native session worker pipes.');
        }
        $output = '';
        $error = '';
        $deadline = microtime(true) + 20;
        do {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            if ($stdout === false || $stderr === false) {
                throw new RuntimeException('Could not read native session worker output.');
            }
            $output .= $stdout;
            $error .= $stderr;
            $status = proc_get_status($process);
            if (!$status['running'] && feof($pipes[1]) && feof($pipes[2])) {
                break;
            }
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Native session worker completion timed out: ' . $error);
            }
            usleep(1000);
        } while (true);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        if ($exit !== 0 || $error !== '') {
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

    private static function writeRelease(string $path): void
    {
        if (file_put_contents($path, 'go') !== 2) {
            throw new RuntimeException('Could not write native session release marker: ' . $path);
        }
    }

    /** @param list<array{resource, array<int, resource>}> $workers */
    private static function cleanup(array $workers): void
    {
        foreach ($workers as [$process, $pipes]) {
            if (is_resource($process)) {
                proc_terminate($process);
            }
        }
        $deadline = microtime(true) + 1;
        foreach ($workers as [$process, $pipes]) {
            if (is_resource($process)) {
                while (proc_get_status($process)['running']) {
                    if (microtime(true) >= $deadline) {
                        if (!proc_terminate($process, 9)) {
                            throw new RuntimeException('Could not stop native session worker.');
                        }
                        break;
                    }
                    usleep(1000);
                }
            }
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            if (is_resource($process)) {
                proc_close($process);
            }
        }
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
     * @param list<array{resource, array<int, resource>}> $workers
     * @return list<array{resource, array<int, resource>}>
     */
    private static function queueFollowers(string $directory, string $id, string $serializer, int $count, array &$workers): array
    {
        $queued = [];
        for ($i = 0; $i < $count; ++$i) {
            $number = 2 + $i;
            $process = self::start($directory, $id, $serializer, 'queue' . $number . '_wrong');
            $workers[] = $process;
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
            if ($locks === false) {
                throw new RuntimeException('Could not read native session lock waiters.');
            }
            foreach ($locks as $line) {
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
