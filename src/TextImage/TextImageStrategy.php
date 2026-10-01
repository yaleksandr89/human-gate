<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\TextImage;

use InvalidArgumentException;
use Random\RandomException;
use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\PreparedChallenge;
use Yaleksandr\HumanGate\Challenge\Purpose;
use Yaleksandr\HumanGate\Internal\AnswerDigest;
use Yaleksandr\HumanGate\Internal\TextImage\TextImageAnswer;
use Yaleksandr\HumanGate\Port\ChallengeStrategy;
use Yaleksandr\HumanGate\Port\TextImageRenderer;
use Yaleksandr\HumanGate\State\ActiveChallenge;
use Yaleksandr\HumanGate\State\AnswerProof;

final readonly class TextImageStrategy implements ChallengeStrategy
{
    public function __construct(
        private TextImageRenderer $renderer,
        private TextImageAnswerOptions $answerOptions = new TextImageAnswerOptions(),
    ) {}

    public function kind(): ChallengeKind
    {
        return ChallengeKind::TextImage;
    }

    /** @throws RandomException */
    public function prepare(ChallengeId $id, Purpose $purpose): PreparedChallenge
    {
        $answer = TextImageAnswer::generate($this->answerOptions->caseSensitive);
        $presentation = $this->renderer->render($answer);
        if ($presentation->mimeType !== 'image/png') {
            throw new InvalidArgumentException('Invalid TextImage presentation.');
        }

        return new PreparedChallenge(
            new AnswerProof(1, AnswerDigest::forAnswer($id, $purpose, ChallengeKind::TextImage, $answer)),
            $presentation,
        );
    }

    public function verify(ActiveChallenge $challenge, string $submittedAnswer): bool
    {
        if ($challenge->kind !== ChallengeKind::TextImage) {
            throw new InvalidArgumentException('Unexpected challenge kind.');
        }

        $answer = TextImageAnswer::normalize($submittedAnswer, $this->answerOptions->caseSensitive);
        if ($answer === null) {
            return false;
        }

        return hash_equals($challenge->proof->digest, AnswerDigest::forAnswer($challenge->id, $challenge->purpose, $challenge->kind, $answer));
    }
}
