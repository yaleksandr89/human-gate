<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Session;

use Yaleksandr\HumanGate\Internal\Session\SessionBucketCodec;

/**
 * EN: Authority for one native files-session commit and one read-only verification reopen.
 * RU: Полномочие на одну фиксацию файловой сессии PHP и одно повторное открытие только для проверки.
 */
final class ActiveNativeSessionScope
{
    private bool $consumed = false;

    private function __construct(private readonly string $id, private readonly string $name) {}

    public static function capture(): self
    {
        self::assertSupported();

        $id = session_id();
        $name = session_name();
        if (!is_string($id) || !is_string($name)) {
            throw new NativeSessionStorageException(NativeSessionStorageFailureReason::SessionIdentity);
        }

        return new self($id, $name);
    }

    public function assertActive(): void
    {
        if ($this->consumed || session_status() !== PHP_SESSION_ACTIVE) {
            throw new NativeSessionStorageException(NativeSessionStorageFailureReason::UnsupportedSession);
        }
        self::assertSupported();
        if (session_id() !== $this->id || session_name() !== $this->name) {
            throw new NativeSessionStorageException(NativeSessionStorageFailureReason::SessionIdentity);
        }
    }

    public function commitAndRead(): mixed
    {
        $this->assertActive();
        $this->consumed = true;

        if (!@session_write_close()) {
            throw new NativeSessionStorageException(NativeSessionStorageFailureReason::Commit);
        }

        $useCookies = ini_get('session.use_cookies');
        $cacheLimiter = ini_get('session.cache_limiter');
        try {
            if (!@session_start(['read_and_close' => true, 'use_cookies' => false, 'cache_limiter' => ''])) {
                throw new NativeSessionStorageException(NativeSessionStorageFailureReason::Verification);
            }
        } finally {
            if (is_string($useCookies) && ini_get('session.use_cookies') !== $useCookies) {
                ini_set('session.use_cookies', $useCookies);
            }
            if (is_string($cacheLimiter) && ini_get('session.cache_limiter') !== $cacheLimiter) {
                ini_set('session.cache_limiter', $cacheLimiter);
            }
            if (ini_get('session.use_cookies') !== $useCookies || ini_get('session.cache_limiter') !== $cacheLimiter) {
                throw new NativeSessionStorageException(NativeSessionStorageFailureReason::Verification);
            }
        }

        if (session_status() !== PHP_SESSION_NONE || session_id() !== $this->id || session_name() !== $this->name) {
            throw new NativeSessionStorageException(NativeSessionStorageFailureReason::SessionIdentity);
        }
        if (!array_key_exists(SessionBucketCodec::SESSION_KEY, $_SESSION)) {
            throw new NativeSessionStorageException(NativeSessionStorageFailureReason::Verification);
        }

        return $_SESSION[SessionBucketCodec::SESSION_KEY];
    }

    private static function assertSupported(): void
    {
        if (!extension_loaded('session') || session_status() !== PHP_SESSION_ACTIVE
            || session_module_name() !== 'files' || ini_get('session.save_handler') !== 'files'
            || !in_array(ini_get('session.serialize_handler'), ['php', 'php_serialize'], true)) {
            throw new NativeSessionStorageException(NativeSessionStorageFailureReason::UnsupportedSession);
        }
    }
}
