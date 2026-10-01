<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Internal\TextImage;

use Random\RandomException;

final class TextImageAnswer
{
    public const string CASE_INSENSITIVE_ALPHABET = '23456789ACDEFGHJKMNPQRTUVWXY';
    public const string CASE_SENSITIVE_ALPHABET = '23456789ACDEFGHJKMNPQRTUVWXYadefhmnrt';
    public const string RENDERABLE_ALPHABET = self::CASE_SENSITIVE_ALPHABET;

    /** @throws RandomException */
    public static function generate(bool $caseSensitive): string
    {
        $alphabet = $caseSensitive ? self::CASE_SENSITIVE_ALPHABET : self::CASE_INSENSITIVE_ALPHABET;
        $maxIndex = strlen($alphabet) - 1;
        $answer = '';
        for ($i = 0; $i < 6; ++$i) {
            $answer .= $alphabet[random_int(0, $maxIndex)];
        }

        return $answer;
    }

    public static function normalize(string $raw, bool $caseSensitive): ?string
    {
        if (strlen($raw) > 64) {
            return null;
        }

        $answer = trim($raw, " \t\r\n");
        if (!$caseSensitive) {
            $answer = strtr($answer, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
        }
        $alphabet = $caseSensitive ? self::CASE_SENSITIVE_ALPHABET : self::CASE_INSENSITIVE_ALPHABET;
        if (strlen($answer) !== 6 || strspn($answer, $alphabet) !== 6) {
            return null;
        }

        return $answer;
    }
}
