<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Internal\IconSequence;

use InvalidArgumentException;

final class IconSequenceAnswer
{
    /** @param array<array-key, string> $tokens */
    public static function canonicalize(array $tokens): string
    {
        return self::canonicalTokens($tokens) ?? throw new InvalidArgumentException('Invalid icon sequence answer tokens.');
    }

    public static function normalize(string $raw): ?string
    {
        if (strlen($raw) > 197) {
            return null;
        }

        return self::canonicalTokens(explode(',', $raw));
    }

    /** @param array<array-key, string> $tokens */
    private static function canonicalTokens(array $tokens): ?string
    {
        if (!array_is_list($tokens) || count($tokens) < 3 || count($tokens) > 6) {
            return null;
        }
        $seen = [];
        foreach ($tokens as $token) {
            if (preg_match('/\A[0-9a-f]{32}\z/', $token) !== 1 || in_array($token, $seen, true)) {
                return null;
            }
            $seen[] = $token;
        }

        return implode(',', $tokens);
    }
}
