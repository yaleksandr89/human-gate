<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Internal\TextImage;

use Random\RandomException;

final class TextImageAnswer
{
    public const ALPHABET = '23456789ACDEFGHJKMNPQRTUVWXY';

    /** @throws RandomException */
    public static function generate(): string
    {
        $answer = '';
        for ($i = 0; $i < 6; ++$i) {
            $answer .= self::ALPHABET[random_int(0, 27)];
        }

        return $answer;
    }

    public static function normalize(string $raw): ?string
    {
        if (strlen($raw) > 64) {
            return null;
        }

        $answer = strtr(trim($raw, " \t\r\n"), 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
        if (strlen($answer) !== 6 || strspn($answer, self::ALPHABET) !== 6) {
            return null;
        }

        return $answer;
    }
}
