<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\IconSequence;

use InvalidArgumentException;
use Random\RandomException;
use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\PreparedChallenge;
use Yaleksandr\HumanGate\Challenge\Purpose;
use Yaleksandr\HumanGate\Internal\AnswerDigest;
use Yaleksandr\HumanGate\Internal\IconSequence\IconSequenceAnswer;
use Yaleksandr\HumanGate\Internal\IconSequence\IconSequenceCatalog;
use Yaleksandr\HumanGate\Internal\IconSequence\IconSequenceRenderer;
use Yaleksandr\HumanGate\Port\ChallengeStrategy;
use Yaleksandr\HumanGate\Presentation\IconSequenceChoice;
use Yaleksandr\HumanGate\Presentation\IconSequencePresentation;
use Yaleksandr\HumanGate\State\ActiveChallenge;
use Yaleksandr\HumanGate\State\AnswerProof;

final readonly class IconSequenceStrategy implements ChallengeStrategy
{
    private const int TOKEN_ATTEMPTS = 4;

    public function __construct(
        private IconSequenceOptions $options = new IconSequenceOptions(),
        private IconSequenceRenderOptions $renderOptions = new IconSequenceRenderOptions(),
    ) {}

    public function kind(): ChallengeKind
    {
        return ChallengeKind::IconSequence;
    }

    /** @throws RandomException */
    public function prepare(ChallengeId $id, Purpose $purpose): PreparedChallenge
    {
        $pool = IconSequenceCatalog::names();
        for ($index = 0; $index < $this->options->choiceCount; ++$index) {
            $other = random_int($index, count($pool) - 1);
            [$pool[$index], $pool[$other]] = [$pool[$other], $pool[$index]];
        }
        $choices = [];
        $tokens = [];
        $targetTokens = [];
        $sequence = [];
        foreach (array_slice($pool, 0, $this->options->choiceCount) as $index => $name) {
            $token = self::uniqueToken($tokens);
            $tokens[] = $token;
            $image = IconSequenceCatalog::image($name);
            $choices[] = new IconSequenceChoice($token, $image);
            if ($index < $this->options->sequenceLength) {
                $targetTokens[] = $token;
                $sequence[] = $image;
            }
        }
        for ($index = count($choices) - 1; $index > 0; --$index) {
            $other = random_int(0, $index);
            [$choices[$index], $choices[$other]] = [$choices[$other], $choices[$index]];
        }
        $answer = IconSequenceAnswer::canonicalize($targetTokens);
        $target = new IconSequenceRenderer($this->renderOptions)->render($sequence);

        return new PreparedChallenge(
            new AnswerProof(1, AnswerDigest::forAnswer($id, $purpose, ChallengeKind::IconSequence, $answer)),
            new IconSequencePresentation($target, $this->options->sequenceLength, $choices),
        );
    }

    public function verify(ActiveChallenge $challenge, string $submittedAnswer): bool
    {
        if ($challenge->kind !== ChallengeKind::IconSequence) {
            throw new InvalidArgumentException('Unexpected challenge kind.');
        }
        $answer = IconSequenceAnswer::normalize($submittedAnswer);
        if ($answer === null) {
            return false;
        }

        return hash_equals(
            $challenge->proof->digest,
            AnswerDigest::forAnswer($challenge->id, $challenge->purpose, $challenge->kind, $answer),
        );
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

        throw new RandomException('Unable to generate unique icon sequence tokens.');
    }
}
