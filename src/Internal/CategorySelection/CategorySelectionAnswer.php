<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Internal\CategorySelection;

use InvalidArgumentException;

final class CategorySelectionAnswer
{
    /** @param array<array-key, string> $tokens */
    public static function canonicalize(array $tokens): string
    {
        $answer = self::canonicalTokens($tokens);
        if ($answer === null) {
            throw new InvalidArgumentException('Invalid category selection answer tokens.');
        }

        return $answer;
    }

    public static function normalize(string $raw): ?string
    {
        if (strlen($raw) > 512) {
            return null;
        }

        return self::canonicalTokens(explode(',', $raw));
    }

    /** @param array<array-key, string> $tokens */
    private static function canonicalTokens(array $tokens): ?string
    {
        if (!array_is_list($tokens) || count($tokens) < 1 || count($tokens) > 12) {
            return null;
        }
        $seen = [];
        foreach ($tokens as $token) {
            if (preg_match('/\A[0-9a-f]{32}\z/', $token) !== 1 || in_array($token, $seen, true)) {
                return null;
            }
            $seen[] = $token;
        }
        sort($tokens, SORT_STRING);

        return implode(',', $tokens);
    }
}
