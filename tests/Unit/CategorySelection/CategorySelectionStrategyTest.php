<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Unit\CategorySelection;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\HumanGate\CategorySelection\CategorySelectionCategory;
use Yaleksandr\HumanGate\CategorySelection\CategorySelectionOptions;
use Yaleksandr\HumanGate\CategorySelection\CategorySelectionStrategy;
use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\Purpose;
use Yaleksandr\HumanGate\Internal\AnswerDigest;
use Yaleksandr\HumanGate\Internal\CategorySelection\CategorySelectionAnswer;
use Yaleksandr\HumanGate\Presentation\CategorySelectionPresentation;
use Yaleksandr\HumanGate\State\ActiveChallenge;
use Yaleksandr\HumanGate\State\AnswerProof;

#[TestDox('Выбор категории генерирует непрозрачные значения и проверяет точный набор выбранных карточек')]
final class CategorySelectionStrategyTest extends TestCase
{
    public function testGenerationVerificationAndProofBinding(): void
    {
        $categories = self::categories();
        foreach ([[6, 2], [3, 1], [12, 4]] as [$cardCount, $targetCount]) {
            $strategy = new CategorySelectionStrategy($categories, new CategorySelectionOptions($cardCount, $targetCount));
            self::assertSame(ChallengeKind::CategorySelection, $strategy->kind());
            $id = ChallengeId::fromString(str_repeat('a', 64));
            $purpose = new Purpose('login');
            $prepared = $strategy->prepare($id, $purpose);
            self::assertInstanceOf(CategorySelectionPresentation::class, $prepared->presentation);
            $presentation = $prepared->presentation;
            self::assertCount($cardCount, $presentation->cards);
            $targetItems = [];
            $allItems = [];
            foreach ($categories as $category) {
                array_push($allItems, ...$category->items);
                if ($category->label === $presentation->category) {
                    $targetItems = $category->items;
                }
            }
            self::assertNotEmpty($targetItems);
            self::assertEqualsCanonicalizing(['category', 'cards'], array_keys(get_object_vars($presentation)));
            $targetTokens = [];
            $distractorTokens = [];
            $allTokens = [];
            $shownLabels = [];
            foreach ($presentation->cards as $card) {
                self::assertContains($card->label, $allItems);
                self::assertMatchesRegularExpression('/\A[0-9a-f]{32}\z/', $card->token);
                self::assertSame(['token', 'label'], array_keys(get_object_vars($card)));
                $allTokens[] = $card->token;
                $shownLabels[] = $card->label;
                if (in_array($card->label, $targetItems, true)) {
                    $targetTokens[] = $card->token;
                } else {
                    $distractorTokens[] = $card->token;
                }
            }
            self::assertCount($cardCount, array_unique($allTokens));
            self::assertCount($cardCount, array_unique($shownLabels));
            self::assertCount($targetCount, $targetTokens);
            self::assertCount($cardCount - $targetCount, $distractorTokens);
            $answer = CategorySelectionAnswer::canonicalize($targetTokens);
            self::assertSame(1, $prepared->proof->format);
            self::assertSame(AnswerDigest::forAnswer($id, $purpose, ChallengeKind::CategorySelection, $answer), $prepared->proof->digest);
            self::assertSame(['format', 'digest'], array_keys(get_object_vars($prepared->proof)));
            $active = new ActiveChallenge($id, $purpose, ChallengeKind::CategorySelection, 1000, 1180, $prepared->proof);
            self::assertSame(['id', 'purpose', 'kind', 'issuedAt', 'expiresAt', 'proof', 'wrongAttempts'], array_keys(get_object_vars($active)));
            self::assertTrue($strategy->verify($active, implode(',', $targetTokens)));
            self::assertTrue($strategy->verify($active, implode(',', array_reverse($targetTokens))));
            self::assertFalse($strategy->verify($active, implode(',', array_slice($targetTokens, 1))));
            self::assertFalse($strategy->verify($active, $answer . ',' . $distractorTokens[0]));
            self::assertFalse($strategy->verify($active, $answer . ',' . $targetTokens[0]));
            $tampered = $targetTokens;
            $tampered[0] = $distractorTokens[0];
            self::assertFalse($strategy->verify($active, implode(',', $tampered)));
            $unknown = str_repeat('0', 32);
            for ($index = 0; in_array($unknown, $allTokens, true) && $index < 12; ++$index) {
                $unknown = str_pad(dechex($index + 1), 32, '0', STR_PAD_LEFT);
            }
            self::assertNotContains($unknown, $allTokens);
            self::assertFalse($strategy->verify($active, $unknown));
            foreach (['', ' ' . $answer, $answer . ',', strtoupper($answer) . 'G', str_repeat('x', 513)] as $malformed) {
                self::assertFalse($strategy->verify($active, $malformed));
            }
            self::assertFalse($strategy->verify(new ActiveChallenge(ChallengeId::fromString(str_repeat('b', 64)), $purpose, ChallengeKind::CategorySelection, 1000, 1180, $prepared->proof), $answer));
            self::assertFalse($strategy->verify(new ActiveChallenge($id, new Purpose('signup'), ChallengeKind::CategorySelection, 1000, 1180, $prepared->proof), $answer));
            $wrongKindProof = new AnswerProof(1, AnswerDigest::forAnswer($id, $purpose, ChallengeKind::TextImage, $answer));
            self::assertFalse($strategy->verify(new ActiveChallenge($id, $purpose, ChallengeKind::CategorySelection, 1000, 1180, $wrongKindProof), $answer));
        }
    }

    public function testUnexpectedChallengeKind(): void
    {
        $challenge = new ActiveChallenge(
            ChallengeId::fromString(str_repeat('a', 64)),
            new Purpose('login'),
            ChallengeKind::TextImage,
            1000,
            1180,
            new AnswerProof(1, str_repeat('b', 64)),
        );
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/^Unexpected challenge kind\.$/');
        new CategorySelectionStrategy(self::categories())->verify($challenge, '');
    }

    public function testInvalidConfiguration(): void
    {
        $categories = self::categories();
        $fewTargets = new CategorySelectionCategory('Small', ['Single']);
        $sameLabel = new CategorySelectionCategory($categories[0]->label, ['a', 'b', 'c', 'd']);
        $sameItem = new CategorySelectionCategory('Other', [$categories[0]->items[0], 'b', 'c', 'd']);
        $many = array_map(static fn(int $index): CategorySelectionCategory => new CategorySelectionCategory('Category ' . $index, ['a' . $index, 'b' . $index, 'c' . $index, 'd' . $index]), range(1, 17));
        foreach ([
            [],
            [$categories[0]],
            [1 => $categories[0], 2 => $categories[1]],
            $many,
            [$categories[0], $sameLabel],
            [$categories[0], $sameItem],
            [$categories[0], $fewTargets],
            [$categories[0], new CategorySelectionCategory('Small', ['a', 'b'])],
            [$categories[0], 'invalid'],
        ] as $invalid) {
            try {
                new CategorySelectionStrategy($invalid);
                self::fail('Invalid configuration was accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
        $sixteen = array_slice($many, 0, 16);
        self::assertSame(ChallengeKind::CategorySelection, new CategorySelectionStrategy($sixteen)->kind());
        self::assertSame(ChallengeKind::CategorySelection, new CategorySelectionStrategy([$categories[0], $categories[1]])->kind());
    }

    public function testDistractorPoolMustSufficeForEveryTarget(): void
    {
        $categories = self::categories();
        $large = new CategorySelectionCategory('Large', array_map(static fn(int $index): string => 'Item ' . $index, range(1, 8)));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/^Insufficient distractor items\.$/');
        new CategorySelectionStrategy([$categories[0], $large], new CategorySelectionOptions(8, 2));
    }

    #[TestDox('Коллизия токена повторяется с ограничением, а отказ источника случайности передаётся вызывающей стороне')]
    public function testTokenCollisionRecoveryExhaustionAndRandomFailure(): void
    {
        $autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
        foreach (['recover' => 'ok:4', 'exhaust' => 'failed:5', 'bytesFail' => 'source:1', 'intFail' => 'source:0'] as $mode => $expected) {
            // EN: Namespaced random functions are isolated in a child process and do not affect other tests.
            // RU: Функции случайности в пространстве имён изолированы в дочернем процессе и не влияют на другие тесты.
            $script = 'namespace Yaleksandr\\HumanGate\\CategorySelection; '
                . '$mode = ' . var_export($mode, true) . '; $calls = 0; '
                . 'function random_int(int $min, int $max): int { global $mode; '
                . 'if ($mode === "intFail") { throw new \\Random\\RandomException("source"); } return $min; } '
                . 'function random_bytes(int $length): string { global $mode, $calls; ++$calls; '
                . 'if ($length !== 16) { throw new \\RuntimeException("Wrong token length"); } '
                . 'if ($mode === "bytesFail") { throw new \\Random\\RandomException("source"); } '
                . 'if ($mode === "exhaust" || $calls <= 2) { return str_repeat("a", 16); } '
                . 'return str_repeat(chr($calls), 16); } '
                . 'require ' . var_export($autoload, true) . '; '
                . '$strategy = new CategorySelectionStrategy(['
                . 'new CategorySelectionCategory("Fruit", ["Apple", "Pear"]), '
                . 'new CategorySelectionCategory("Tools", ["Saw", "Drill"])], new CategorySelectionOptions(3, 1)); '
                . 'try { $prepared = $strategy->prepare('
                . '\\Yaleksandr\\HumanGate\\Challenge\\ChallengeId::fromString(str_repeat("a", 64)), '
                . 'new \\Yaleksandr\\HumanGate\\Challenge\\Purpose("login")); '
                . '$tokens = array_map(fn($card) => $card->token, $prepared->presentation->cards); '
                . 'if (count(array_unique($tokens)) !== 3) { throw new \\RuntimeException("Duplicate tokens"); } '
                . 'echo "ok:" . $calls; '
                . '} catch (\\Random\\RandomException $exception) { '
                . 'echo ($exception->getMessage() === "source" ? "source:" : "failed:") . $calls; }';
            $process = proc_open([PHP_BINARY, '-r', $script], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process), (string) $errors);
            self::assertSame($expected, $output);
        }
    }

    /** @return list<CategorySelectionCategory> */
    private static function categories(): array
    {
        return [
            new CategorySelectionCategory('Фрукты', ['Яблоко', 'Груша', 'Банан', 'Апельсин']),
            new CategorySelectionCategory('Инструменты', ['Молоток', 'Пила', 'Дрель', 'Рубанок']),
            new CategorySelectionCategory('Животные', ['Лиса', 'Волк', 'Заяц', 'Медведь']),
        ];
    }
}
