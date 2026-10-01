<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Support\NativeSession;

use RuntimeException;
use Throwable;

final class NativeSessionFixture
{
    public readonly string $directory;
    public readonly string $id;
    private bool $cleaned = false;

    public function __construct()
    {
        $this->directory = sys_get_temp_dir() . '/human-gate-native-' . bin2hex(random_bytes(12));
        $this->id = bin2hex(random_bytes(16));
        if (!mkdir($this->directory, 0700)) {
            throw new RuntimeException('Could not create isolated native session directory.');
        }
    }

    /** @return array<string, mixed> */
    public function run(string $serializer, string $operation): array
    {
        return NativeSessionProcess::run($this->directory, $this->id, $serializer, $operation);
    }

    public function cleanup(): void
    {
        if ($this->cleaned) {
            return;
        }
        $names = scandir($this->directory);
        if ($names === false) {
            throw new RuntimeException('Could not list native session fixture directory: ' . $this->directory);
        }
        foreach ($names as $name) {
            if ($name !== '.' && $name !== '..' && !unlink($this->directory . '/' . $name)) {
                throw new RuntimeException('Could not remove native session fixture file: ' . $name);
            }
        }
        if (!rmdir($this->directory)) {
            throw new RuntimeException('Could not remove native session fixture directory: ' . $this->directory);
        }
        $this->cleaned = true;
    }

    public function __destruct()
    {
        try {
            $this->cleanup();
        } catch (Throwable $exception) {
            try {
                trigger_error($exception->getMessage(), E_USER_WARNING);
            } catch (Throwable) {
                try {
                    error_log($exception->getMessage());
                } catch (Throwable) {
                    // EN: Reporting failures must not escape destructor fallback and mask a failing test.
                    // RU: Ошибки диагностики не должны выходить из деструктора и скрывать падение теста.
                }
            }
        }
    }
}
