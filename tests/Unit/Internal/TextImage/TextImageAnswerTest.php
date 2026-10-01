<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Unit\Internal\TextImage;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\HumanGate\Internal\TextImage\TextImageAnswer;

#[TestDox('Протокол ответа TextImage задаёт точные алфавиты и ограниченную нормализацию ASCII')]
final class TextImageAnswerTest extends TestCase
{
    public function testExactAlphabets(): void
    {
        self::assertSame('23456789ACDEFGHJKMNPQRTUVWXY', TextImageAnswer::CASE_INSENSITIVE_ALPHABET);
        self::assertSame('23456789ACDEFGHJKMNPQRTUVWXYadefhmnrt', TextImageAnswer::CASE_SENSITIVE_ALPHABET);
        self::assertSame(TextImageAnswer::CASE_SENSITIVE_ALPHABET, TextImageAnswer::RENDERABLE_ALPHABET);
    }

    #[TestDox('Каждый индекс обоих алфавитов детерминированно доступен генератору с точными границами RNG')]
    public function testGenerationMapsEveryAlphabetIndex(): void
    {
        $autoload = dirname(__DIR__, 4) . '/vendor/autoload.php';
        foreach ([false, true] as $caseSensitive) {
            $alphabet = $caseSensitive ? TextImageAnswer::CASE_SENSITIVE_ALPHABET : TextImageAnswer::CASE_INSENSITIVE_ALPHABET;
            // EN: The namespaced RNG exists only in this child process; each index is returned six times.
            // RU: RNG в пространстве имён существует только в дочернем процессе; каждый индекс возвращается шесть раз.
            $script = 'namespace Yaleksandr\\HumanGate\\Internal\\TextImage; '
                . 'function random_int(int $min, int $max): int { static $calls = 0; '
                . 'if ($min !== 0 || $max !== ' . (strlen($alphabet) - 1) . ') { throw new \\RuntimeException("Invalid RNG bounds"); } '
                . 'return intdiv($calls++, 6); } '
                . 'require ' . var_export($autoload, true) . '; $answers = []; '
                . 'for ($i = 0; $i < ' . strlen($alphabet) . '; ++$i) { '
                . '$answers[] = TextImageAnswer::generate(' . ($caseSensitive ? 'true' : 'false') . '); } '
                . 'echo json_encode($answers, JSON_THROW_ON_ERROR);';
            $process = proc_open(
                [PHP_BINARY, '-r', $script],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
            );
            self::assertIsResource($process);
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process), (string) $errors);
            self::assertIsString($output);
            self::assertSame(
                array_map(static fn(string $character): string => str_repeat($character, 6), str_split($alphabet)),
                json_decode($output, true, flags: JSON_THROW_ON_ERROR),
            );
        }
    }

    public function testRealGenerationProducesCanonicalSixByteAnswers(): void
    {
        foreach ([false, true] as $caseSensitive) {
            $answer = TextImageAnswer::generate($caseSensitive);
            $alphabet = $caseSensitive ? TextImageAnswer::CASE_SENSITIVE_ALPHABET : TextImageAnswer::CASE_INSENSITIVE_ALPHABET;
            self::assertSame(6, strlen($answer));
            self::assertSame(6, strspn($answer, $alphabet));
            self::assertSame($answer, TextImageAnswer::normalize($answer, $caseSensitive));
        }
    }

    public function testNormalizationUsesExactlyTheSelectedAlphabet(): void
    {
        foreach (str_split(TextImageAnswer::CASE_INSENSITIVE_ALPHABET) as $character) {
            self::assertSame(str_repeat($character, 6), TextImageAnswer::normalize(str_repeat(strtolower($character), 6), false));
        }
        foreach (str_split(TextImageAnswer::CASE_SENSITIVE_ALPHABET) as $character) {
            self::assertSame(str_repeat($character, 6), TextImageAnswer::normalize(str_repeat($character, 6), true));
        }
        self::assertSame('AG29RT', TextImageAnswer::normalize(" \tAg29rt\r\n", false));
        self::assertSame('Ad29rt', TextImageAnswer::normalize(" \tAd29rt\r\n", true));
        for ($byte = 0; $byte < 256; ++$byte) {
            $character = chr($byte);
            if (!str_contains(TextImageAnswer::CASE_SENSITIVE_ALPHABET, $character)) {
                self::assertNull(TextImageAnswer::normalize(str_repeat($character, 6), true), "byte=$byte");
            }
            $canonical = strtr($character, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
            if (!str_contains(TextImageAnswer::CASE_INSENSITIVE_ALPHABET, $canonical)) {
                self::assertNull(TextImageAnswer::normalize(str_repeat($character, 6), false), "byte=$byte");
            }
        }
    }

    public function testRawInputBoundaryAndInvalidInputs(): void
    {
        foreach ([false, true] as $caseSensitive) {
            $answer = $caseSensitive ? 'Ad29rt' : 'AG29RT';
            self::assertSame($answer, TextImageAnswer::normalize(str_repeat(' ', 58) . $answer, $caseSensitive));
            self::assertNull(TextImageAnswer::normalize(str_repeat(' ', 59) . $answer, $caseSensitive));
            foreach (['', '23456', '2345678', 'IIIIII', 'BBBBBB', "23 567", "23\t567", "\0" . $answer,
                "\v" . $answer, $answer . "\f", "\xC2\xA0" . $answer, 'А23456', 'Ａ23456', "\xFF23456"] as $raw) {
                self::assertNull(TextImageAnswer::normalize($raw, $caseSensitive));
            }
        }
    }
}
