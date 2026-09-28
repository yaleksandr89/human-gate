<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Support\NativeSession;

use RuntimeException;

final class NativeSessionFixture
{
    public readonly string $directory;
    public readonly string $id;

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

    public function __destruct()
    {
        foreach (scandir($this->directory) ?: [] as $name) {
            if ($name !== '.' && $name !== '..') {
                unlink($this->directory . '/' . $name);
            }
        }
        rmdir($this->directory);
    }
}
