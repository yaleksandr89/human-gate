<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Unit\IconSequence;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\PreparedChallenge;
use Yaleksandr\HumanGate\Challenge\Purpose;
use Yaleksandr\HumanGate\IconSequence\IconSequenceOptions;
use Yaleksandr\HumanGate\IconSequence\IconSequenceStrategy;
use Yaleksandr\HumanGate\Internal\AnswerDigest;
use Yaleksandr\HumanGate\Internal\IconSequence\IconSequenceCatalog;
use Yaleksandr\HumanGate\Presentation\IconSequencePresentation;
use Yaleksandr\HumanGate\State\ActiveChallenge;
use Yaleksandr\HumanGate\State\AnswerProof;

#[TestDox('Последовательность проверяет порядок непрозрачных токенов и сохраняет привязку доказательства')]
final class IconSequenceStrategyTest extends TestCase
{
    public function testGenerationAndVerification(): void
    {
        foreach ([[3, 5], [4, 8], [6, 12]] as [$length, $count]) {
            $output = self::child('normal', $length, $count);
            $prepared = unserialize($output);
            self::assertInstanceOf(PreparedChallenge::class, $prepared);
            self::assertInstanceOf(IconSequencePresentation::class, $prepared->presentation);
            $presentation = $prepared->presentation;
            self::assertSame($length, $presentation->requiredSelections);
            self::assertCount($count, $presentation->choices);
            self::assertEqualsCanonicalizing(['target', 'requiredSelections', 'choices'], array_keys(get_object_vars($presentation)));
            $allTokens = [];
            $images = [];
            foreach ($presentation->choices as $choice) {
                self::assertSame(['token', 'image'], array_keys(get_object_vars($choice)));
                self::assertMatchesRegularExpression('/\A[0-9a-f]{32}\z/', $choice->token);
                $allTokens[] = $choice->token;
                $images[] = hash('sha256', $choice->image->bytes);
                self::assertSame('image/png', $choice->image->mimeType);
                self::assertSame(96, $choice->image->width);
                self::assertSame(96, $choice->image->height);
            }
            self::assertCount($count, array_unique($allTokens));
            self::assertCount($count, array_unique($images));
            $expectedImages = array_map(
                static fn(string $name): string => hash('sha256', IconSequenceCatalog::image($name)->bytes),
                array_slice(IconSequenceCatalog::names(), 0, $count),
            );
            self::assertEqualsCanonicalizing($expectedImages, $images);
            $targetImages = array_slice($expectedImages, 0, $length);
            self::assertCount($length, array_intersect($targetImages, $images));
            self::assertGreaterThanOrEqual(2, count(array_diff($images, $targetImages)));
            foreach ($presentation->choices as $choice) {
                $index = array_search(hash('sha256', $choice->image->bytes), $expectedImages, true);
                self::assertIsInt($index);
                self::assertSame(bin2hex(str_repeat(chr($index + 1), 16)), $choice->token);
            }
            $tokens = array_map(static fn(int $index): string => bin2hex(str_repeat(chr($index), 16)), range(1, $length));
            $answer = implode(',', $tokens);
            self::assertNotSame($tokens, array_slice($allTokens, 0, $length));
            $id = ChallengeId::fromString(str_repeat('a', 64));
            $purpose = new Purpose('login');
            $strategy = new IconSequenceStrategy(new IconSequenceOptions($length, $count));
            self::assertSame(ChallengeKind::IconSequence, $strategy->kind());
            self::assertSame(1, $prepared->proof->format);
            self::assertSame(AnswerDigest::forAnswer($id, $purpose, ChallengeKind::IconSequence, $answer), $prepared->proof->digest);
            $active = new ActiveChallenge($id, $purpose, ChallengeKind::IconSequence, 1000, 1180, $prepared->proof);
            self::assertTrue($strategy->verify($active, $answer));
            $wrong = $tokens;
            $wrong[0] = bin2hex(str_repeat(chr($length + 1), 16));
            $duplicate = $tokens;
            $duplicate[0] = $tokens[1];
            $unknown = $tokens;
            $unknown[0] = str_repeat('f', 32);
            foreach ([
                implode(',', array_reverse($tokens)), implode(',', array_slice($tokens, 1)),
                $answer . ',' . bin2hex(str_repeat(chr($length + 1), 16)),
                implode(',', $wrong), implode(',', $duplicate), implode(',', $unknown),
                '', ' ' . $answer, $answer . "\n", $answer . ',', str_repeat('x', 198),
            ] as $invalid) {
                self::assertFalse($strategy->verify($active, $invalid));
            }
            self::assertFalse($strategy->verify(new ActiveChallenge(ChallengeId::fromString(str_repeat('b', 64)), $purpose, ChallengeKind::IconSequence, 1000, 1180, $prepared->proof), $answer));
            self::assertFalse($strategy->verify(new ActiveChallenge($id, new Purpose('signup'), ChallengeKind::IconSequence, 1000, 1180, $prepared->proof), $answer));
            foreach ([ChallengeKind::TextImage, ChallengeKind::CategorySelection] as $kind) {
                $proof = new AnswerProof(1, AnswerDigest::forAnswer($id, $purpose, $kind, $answer));
                self::assertFalse($strategy->verify(new ActiveChallenge($id, $purpose, ChallengeKind::IconSequence, 1000, 1180, $proof), $answer));
            }
            self::assertSame($length * 112 + 24, $presentation->target->width);
            self::assertSame(128, $presentation->target->height);
            $metadata = getimagesizefromstring($presentation->target->bytes);
            self::assertIsArray($metadata);
            self::assertSame(IMAGETYPE_PNG, $metadata[2]);
        }
    }

    public function testProductionRandomness(): void
    {
        $prepared = new IconSequenceStrategy()->prepare(ChallengeId::generate(), new Purpose('login'));
        self::assertInstanceOf(IconSequencePresentation::class, $prepared->presentation);
        foreach ($prepared->presentation->choices as $choice) {
            self::assertSame(['token', 'image'], array_keys(get_object_vars($choice)));
            self::assertMatchesRegularExpression('/\A[0-9a-f]{32}\z/', $choice->token);
        }
        self::assertCount(8, array_unique(array_map(static fn($choice): string => $choice->token, $prepared->presentation->choices)));
    }

    public function testUnexpectedKind(): void
    {
        $active = new ActiveChallenge(ChallengeId::generate(), new Purpose('login'), ChallengeKind::TextImage, 1000, 1180, new AnswerProof(1, str_repeat('a', 64)));
        $this->expectException(InvalidArgumentException::class);
        new IconSequenceStrategy()->verify($active, '');
    }

    public function testCollisionRecoveryExhaustionAndRandomFailure(): void
    {
        $prepared = unserialize(self::child('recover', 4, 8));
        self::assertInstanceOf(PreparedChallenge::class, $prepared);
        self::assertInstanceOf(IconSequencePresentation::class, $prepared->presentation);
        self::assertCount(8, array_unique(array_map(static fn($choice): string => $choice->token, $prepared->presentation->choices)));
        self::assertSame('failed:5', self::child('exhaust', 4, 8));
        self::assertSame('source:1', self::child('bytesFail', 4, 8));
        self::assertSame('source:0', self::child('intFail', 4, 8));
        self::assertSame('source:8', self::child('renderFail', 4, 8));
        foreach (['assetReadFail', 'assetOversize', 'assetCorrupt'] as $mode) {
            self::assertSame('rendering:1', self::child($mode, 4, 8));
        }
    }

    private static function child(string $mode, int $length, int $count): string
    {
        $script = 'namespace Yaleksandr\\HumanGate\\IconSequence; $mode = ' . var_export($mode, true) . '; $calls = 0; '
            . <<<'PHP'
                function random_int(int $min, int $max): int {
                    global $mode;
                    if ($mode === 'intFail') { throw new \Random\RandomException('source'); }
                    return $min;
                }
                function random_bytes(int $length): string {
                    global $mode, $calls;
                    ++$calls;
                    if ($length !== 16) { throw new \RuntimeException('Wrong token length'); }
                    if ($mode === 'bytesFail') { throw new \Random\RandomException('source'); }
                    if ($mode === 'exhaust' || ($mode === 'recover' && $calls <= 2)) { return str_repeat(chr(1), 16); }
                    return str_repeat(chr($calls), 16);
                }
                namespace Yaleksandr\HumanGate\Internal\IconSequence;
                function random_int(int $min, int $max): int {
                    if ($GLOBALS['mode'] === 'renderFail') { throw new \Random\RandomException('source'); }
                    if ($GLOBALS['verifyOnly'] ?? false) { throw new \RuntimeException('Verification called rendering'); }
                    return $min;
                }
                function file_get_contents(string $path, bool $includePath, mixed $context, int $offset, int $length): string|false {
                    if ($GLOBALS['verifyOnly'] ?? false) { throw new \RuntimeException('Verification read an asset'); }
                    if ($GLOBALS['mode'] === 'assetReadFail') { return false; }
                    if ($GLOBALS['mode'] === 'assetOversize') { return str_repeat('x', 131073); }
                    if ($GLOBALS['mode'] === 'assetCorrupt') { return 'broken'; }
                    return \file_get_contents($path, $includePath, $context, $offset, $length);
                }
                namespace Yaleksandr\HumanGate\IconSequence;
                PHP
            . 'require ' . var_export(dirname(__DIR__, 3) . '/vendor/autoload.php', true) . '; '
            . '$strategy = new IconSequenceStrategy(new IconSequenceOptions(' . $length . ', ' . $count . ')); '
            . '$id = \\Yaleksandr\\HumanGate\\Challenge\\ChallengeId::fromString(str_repeat("a", 64)); '
            . '$purpose = new \\Yaleksandr\\HumanGate\\Challenge\\Purpose("login"); '
            . 'try { $prepared = $strategy->prepare($id, $purpose); '
            . 'if ($mode === "normal") { '
            . '$sequence = array_map(\\Yaleksandr\\HumanGate\\Internal\\IconSequence\\IconSequenceCatalog::image(...), '
            . 'array_slice(\\Yaleksandr\\HumanGate\\Internal\\IconSequence\\IconSequenceCatalog::names(), 0, ' . $length . ')); '
            . '$expected = new \\Yaleksandr\\HumanGate\\Internal\\IconSequence\\IconSequenceRenderer()->render($sequence); '
            . '\\PHPUnit\\Framework\\TestCase::assertSame($expected->bytes, $prepared->presentation->target->bytes); '
            . '$answer = implode(",", array_map(fn($n) => bin2hex(str_repeat(chr($n), 16)), range(1, ' . $length . '))); '
            . '$active = new \\Yaleksandr\\HumanGate\\State\\ActiveChallenge($id, $purpose, $strategy->kind(), 1000, 1180, $prepared->proof); '
            . '$GLOBALS["verifyOnly"] = true; '
            . '\\PHPUnit\\Framework\\TestCase::assertTrue($strategy->verify($active, $answer)); '
            . '\\PHPUnit\\Framework\\TestCase::assertFalse($strategy->verify($active, "bad")); } '
            . 'echo serialize($prepared); '
            . '} catch (\\Random\\RandomException $exception) { echo ($exception->getMessage() === "source" ? "source:" : "failed:") . $calls; '
            . '} catch (\\Yaleksandr\\HumanGate\\Exception\\RenderingException) { echo "rendering:" . $calls; }';
        $process = proc_open([PHP_BINARY, '-r', $script], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), (string) $errors);
        self::assertIsString($output);

        return $output;
    }
}
