<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\CategorySelection;

use InvalidArgumentException;
use Random\RandomException;
use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\PreparedChallenge;
use Yaleksandr\HumanGate\Challenge\Purpose;
use Yaleksandr\HumanGate\Internal\AnswerDigest;
use Yaleksandr\HumanGate\Internal\CategorySelection\CategorySelectionAnswer;
use Yaleksandr\HumanGate\Port\ChallengeStrategy;
use Yaleksandr\HumanGate\Presentation\CategorySelectionCard;
use Yaleksandr\HumanGate\Presentation\CategorySelectionPresentation;
use Yaleksandr\HumanGate\State\ActiveChallenge;
use Yaleksandr\HumanGate\State\AnswerProof;

final readonly class CategorySelectionStrategy implements ChallengeStrategy
{
    private const int TOKEN_ATTEMPTS = 4;

    /** @var list<CategorySelectionCategory> */
    private array $categories;

    /**
     * EN: Validates the supplied array as a list of configured categories.
     * RU: Проверяет переданный массив как список настроенных категорий.
     *
     * @param array<array-key, mixed> $categories
     */
    public function __construct(
        array $categories,
        private CategorySelectionOptions $options = new CategorySelectionOptions(),
    ) {
        if (!array_is_list($categories) || count($categories) < 2 || count($categories) > 16) {
            throw new InvalidArgumentException('Invalid category selection categories.');
        }
        $labels = [];
        $items = [];
        $validated = [];
        foreach ($categories as $category) {
            if (!$category instanceof CategorySelectionCategory) {
                throw new InvalidArgumentException('Invalid category selection category.');
            }
            if (in_array($category->label, $labels, true)) {
                throw new InvalidArgumentException('Duplicate category label.');
            }
            $labels[] = $category->label;
            if (count($category->items) < $options->targetCount) {
                throw new InvalidArgumentException('Insufficient target items.');
            }
            foreach ($category->items as $item) {
                if (in_array($item, $items, true)) {
                    throw new InvalidArgumentException('Duplicate item label across categories.');
                }
                $items[] = $item;
            }
            $validated[] = $category;
        }
        foreach ($validated as $category) {
            if (count($items) - count($category->items) < $options->cardCount - $options->targetCount) {
                throw new InvalidArgumentException('Insufficient distractor items.');
            }
        }
        $this->categories = $validated;
    }

    public function kind(): ChallengeKind
    {
        return ChallengeKind::CategorySelection;
    }

    /** @throws RandomException */
    public function prepare(ChallengeId $id, Purpose $purpose): PreparedChallenge
    {
        $targetIndex = random_int(0, count($this->categories) - 1);
        $target = $this->categories[$targetIndex];
        $targetItems = self::sample($target->items, $this->options->targetCount);
        $pool = [];
        foreach ($this->categories as $index => $category) {
            if ($index !== $targetIndex) {
                array_push($pool, ...$category->items);
            }
        }
        $distractors = self::sample($pool, $this->options->cardCount - $this->options->targetCount);
        $cards = [];
        $tokens = [];
        $targetTokens = [];
        foreach ([...$targetItems, ...$distractors] as $index => $label) {
            $token = self::uniqueToken($tokens);
            $tokens[] = $token;
            $cards[] = new CategorySelectionCard($token, $label);
            if ($index < $this->options->targetCount) {
                $targetTokens[] = $token;
            }
        }
        for ($index = count($cards) - 1; $index > 0; --$index) {
            $other = random_int(0, $index);
            [$cards[$index], $cards[$other]] = [$cards[$other], $cards[$index]];
        }
        $answer = CategorySelectionAnswer::canonicalize($targetTokens);

        return new PreparedChallenge(
            new AnswerProof(1, AnswerDigest::forAnswer($id, $purpose, ChallengeKind::CategorySelection, $answer)),
            new CategorySelectionPresentation($target->label, $cards),
        );
    }

    public function verify(ActiveChallenge $challenge, string $submittedAnswer): bool
    {
        if ($challenge->kind !== ChallengeKind::CategorySelection) {
            throw new InvalidArgumentException('Unexpected challenge kind.');
        }
        $answer = CategorySelectionAnswer::normalize($submittedAnswer);
        if ($answer === null) {
            return false;
        }

        return hash_equals(
            $challenge->proof->digest,
            AnswerDigest::forAnswer($challenge->id, $challenge->purpose, $challenge->kind, $answer),
        );
    }

    /**
     * @param list<string> $items
     * @return list<string>
     * @throws RandomException
     */
    private static function sample(array $items, int $count): array
    {
        $last = count($items) - 1;
        for ($index = 0; $index < $count; ++$index) {
            $other = random_int($index, $last);
            [$items[$index], $items[$other]] = [$items[$other], $items[$index]];
        }

        return array_slice($items, 0, $count);
    }

    /**
     * @param list<string> $used
     * @throws RandomException
     */
    private static function uniqueToken(array $used): string
    {
        for ($attempt = 0; $attempt < self::TOKEN_ATTEMPTS; ++$attempt) {
            $token = bin2hex(random_bytes(16));
            if (!in_array($token, $used, true)) {
                return $token;
            }
        }

        throw new RandomException('Unable to generate unique category selection tokens.');
    }
}
