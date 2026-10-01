<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Unit\TextImage;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\Purpose;
use Yaleksandr\HumanGate\Internal\AnswerDigest;
use Yaleksandr\HumanGate\Internal\TextImage\TextImageAnswer;
use Yaleksandr\HumanGate\Port\TextImageRenderer;
use Yaleksandr\HumanGate\Presentation\ImagePresentation;
use Yaleksandr\HumanGate\State\ActiveChallenge;
use Yaleksandr\HumanGate\State\AnswerProof;
use Yaleksandr\HumanGate\TextImage\GdTextImageRenderer;
use Yaleksandr\HumanGate\TextImage\TextImageAnswerOptions;
use Yaleksandr\HumanGate\TextImage\TextImageRenderOptions;
use Yaleksandr\HumanGate\TextImage\TextImageStrategy;

#[TestDox('Текстовое изображение создаёт и проверяет ответ по строгим правилам')]
final class TextImageStrategyTest extends TestCase
{
    #[TestDox('Явные настройки управляют генерацией и проверкой; чувствительный режим не меняет регистр')]
    public function testExplicitAnswerOptions(): void
    {
        self::assertFalse(new TextImageAnswerOptions()->caseSensitive);
        foreach ([false, true] as $caseSensitive) {
            $renderer = new class implements TextImageRenderer {
                public string $answer = '';

                public function render(string $canonicalAnswer): ImagePresentation
                {
                    $this->answer = $canonicalAnswer;

                    return new ImagePresentation('image/png', 'png bytes', 240, 80);
                }
            };
            $strategy = new TextImageStrategy($renderer, new TextImageAnswerOptions(caseSensitive: $caseSensitive));
            $id = ChallengeId::fromString(str_repeat('a', 64));
            $purpose = new Purpose('login');
            $prepared = $strategy->prepare($id, $purpose);
            $alphabet = $caseSensitive ? TextImageAnswer::CASE_SENSITIVE_ALPHABET : TextImageAnswer::CASE_INSENSITIVE_ALPHABET;
            self::assertSame(6, strlen($renderer->answer));
            self::assertSame(6, strspn($renderer->answer, $alphabet));
            self::assertSame(1, $prepared->proof->format);
            self::assertSame(AnswerDigest::forAnswer($id, $purpose, ChallengeKind::TextImage, $renderer->answer), $prepared->proof->digest);
            $active = new ActiveChallenge($id, $purpose, ChallengeKind::TextImage, 1000, 1180, $prepared->proof);
            self::assertTrue($strategy->verify($active, $renderer->answer));
            if (!$caseSensitive) {
                self::assertTrue($strategy->verify($active, strtolower($renderer->answer)));
            }

            $answer = $caseSensitive ? 'Ad29rt' : 'AG29RT';
            $proof = new AnswerProof(1, AnswerDigest::forAnswer($id, $purpose, ChallengeKind::TextImage, $answer));
            $fixture = new ActiveChallenge($id, $purpose, ChallengeKind::TextImage, 1000, 1180, $proof);
            self::assertTrue($strategy->verify($fixture, " \t" . $answer . "\r\n"));
            self::assertSame(!$caseSensitive, $strategy->verify($fixture, strtolower($answer)));
            if ($caseSensitive) {
                self::assertFalse($strategy->verify($fixture, strtoupper($answer)));
                self::assertFalse($strategy->verify($fixture, 'AD29rt'));
                self::assertFalse($strategy->verify($fixture, 'Ad29rT'));
            }
            foreach (['', '23456', '2345678', 'IIIIII', 'g23456', "\0" . $answer, "\v" . $answer,
                "\xC2\xA0" . $answer, 'А23456', str_repeat(' ', 59) . $answer] as $wrong) {
                self::assertFalse($strategy->verify($fixture, $wrong));
            }
            self::assertFalse($strategy->verify(new ActiveChallenge(ChallengeId::fromString(str_repeat('b', 64)), $purpose, ChallengeKind::TextImage, 1000, 1180, $proof), $answer));
            self::assertFalse($strategy->verify(new ActiveChallenge($id, new Purpose('signup'), ChallengeKind::TextImage, 1000, 1180, $proof), $answer));
            $wrongKindProof = new AnswerProof(1, AnswerDigest::forAnswer($id, $purpose, ChallengeKind::IconSequence, $answer));
            self::assertFalse($strategy->verify(new ActiveChallenge($id, $purpose, ChallengeKind::TextImage, 1000, 1180, $wrongKindProof), $answer));
            self::assertFalse($strategy->verify($fixture, '234567'));
        }
    }

    public function testUnexpectedChallengeKindIsRejected(): void
    {
        $challenge = new ActiveChallenge(
            ChallengeId::fromString(str_repeat('a', 64)),
            new Purpose('login'),
            ChallengeKind::IconSequence,
            1000,
            1180,
            new AnswerProof(1, str_repeat('a', 64)),
        );
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/^Unexpected challenge kind\.$/');
        new TextImageStrategy(new GdTextImageRenderer())->verify($challenge, '234567');
    }

    #[TestDox('Генерация и проверка сохраняют строгие правила ответа')]
    public function testGenerationAndVerification(): void
    {
        $renderer = new class implements TextImageRenderer {
            public string $answer = '';

            public function render(string $canonicalAnswer): ImagePresentation
            {
                $this->answer = $canonicalAnswer;

                return new ImagePresentation('image/png', 'png bytes', 240, 80);
            }
        };
        $strategy = new TextImageStrategy($renderer);
        $id = ChallengeId::fromString(str_repeat('a', 64));
        $purpose = new Purpose('login');
        $prepared = $strategy->prepare($id, $purpose);
        self::assertSame(ChallengeKind::TextImage, $strategy->kind());
        self::assertSame(6, strlen($renderer->answer));
        self::assertSame(6, strspn($renderer->answer, TextImageAnswer::CASE_INSENSITIVE_ALPHABET));
        self::assertInstanceOf(ImagePresentation::class, $prepared->presentation);
        self::assertSame('png bytes', $prepared->presentation->bytes);
        self::assertSame(['proof', 'presentation'], array_keys(get_object_vars($prepared)));
        $active = new ActiveChallenge($id, $purpose, ChallengeKind::TextImage, 1000, 1180, $prepared->proof);
        self::assertTrue($strategy->verify($active, $renderer->answer));
        self::assertTrue($strategy->verify($active, " \t" . strtolower($renderer->answer) . "\r\n"));
        $letterProof = new AnswerProof(1, AnswerDigest::forAnswer($id, $purpose, ChallengeKind::TextImage, 'AG29RT'));
        self::assertTrue($strategy->verify(new ActiveChallenge($id, $purpose, ChallengeKind::TextImage, 1000, 1180, $letterProof), 'ag29rt'));
        foreach (['', '23456', '2345678', 'IIIIII', "23 567", "23\t567", "\0" . $renderer->answer,
            "\v" . $renderer->answer, "\xC2\xA0" . $renderer->answer, 'А23456', str_repeat('x', 65),
            str_repeat(' ', 65) . $renderer->answer] as $wrong) {
            self::assertFalse($strategy->verify($active, $wrong));
        }
        self::assertFalse($strategy->verify($active, $renderer->answer === '234567' ? '234568' : '234567'));
    }

    #[TestDox('Ошибка рендерера передаётся вызывающей стороне без замены')]
    public function testRendererFailurePropagates(): void
    {
        $id = ChallengeId::fromString(str_repeat('a', 64));
        $purpose = new Purpose('login');
        $failing = new TextImageStrategy(new class implements TextImageRenderer {
            public function render(string $canonicalAnswer): ImagePresentation
            {
                throw new RuntimeException('render failed');
            }
        });
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/^render failed$/');
        $failing->prepare($id, $purpose);
    }

    #[TestDox('PNG увеличенного размера принимается с сохранением проверки ответа')]
    public function testScaledPngAccepted(): void
    {
        $renderer = new class implements TextImageRenderer {
            public string $answer = '';

            public function render(string $canonicalAnswer): ImagePresentation
            {
                $this->answer = $canonicalAnswer;

                return new GdTextImageRenderer(new TextImageRenderOptions(scalePercent: 120))->render($canonicalAnswer);
            }
        };
        $strategy = new TextImageStrategy($renderer);
        $id = ChallengeId::fromString(str_repeat('a', 64));
        $purpose = new Purpose('login');
        $prepared = $strategy->prepare($id, $purpose);
        self::assertInstanceOf(ImagePresentation::class, $prepared->presentation);
        self::assertSame(288, $prepared->presentation->width);
        self::assertSame(96, $prepared->presentation->height);
        self::assertSame('image/png', $prepared->presentation->mimeType);
        $active = new ActiveChallenge($id, $purpose, ChallengeKind::TextImage, 1000, 1180, $prepared->proof);
        self::assertTrue($strategy->verify($active, $renderer->answer));
    }

    #[TestDox('Презентация с MIME вне PNG отклоняется')]
    public function testNonPngMimeRejected(): void
    {
        foreach ([['image/jpeg', 240, 80], ['image/webp', 288, 96]] as [$mime, $width, $height]) {
            $renderer = new class ($mime, $width, $height) implements TextImageRenderer {
                public function __construct(private string $mime, private int $width, private int $height) {}

                public function render(string $canonicalAnswer): ImagePresentation
                {
                    return new ImagePresentation($this->mime, 'bytes', $this->width, $this->height);
                }
            };
            try {
                new TextImageStrategy($renderer)->prepare(ChallengeId::fromString(str_repeat('a', 64)), new Purpose('login'));
                self::fail('Invalid TextImage profile was accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Invalid TextImage presentation.', $exception->getMessage());
            }
        }
    }
}
