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
use Yaleksandr\HumanGate\Internal\TextImage\TextImageAnswer;
use Yaleksandr\HumanGate\Port\TextImageRenderer;
use Yaleksandr\HumanGate\Presentation\ImagePresentation;
use Yaleksandr\HumanGate\State\ActiveChallenge;
use Yaleksandr\HumanGate\TextImage\TextImageStrategy;

#[TestDox('Текстовое изображение создаёт и проверяет ответ по строгим правилам')]
final class TextImageStrategyTest extends TestCase
{
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
        self::assertSame(6, strspn($renderer->answer, TextImageAnswer::ALPHABET));
        self::assertInstanceOf(ImagePresentation::class, $prepared->presentation);
        self::assertSame('png bytes', $prepared->presentation->bytes);
        self::assertSame(['proof', 'presentation'], array_keys(get_object_vars($prepared)));
        $active = new ActiveChallenge($id, $purpose, ChallengeKind::TextImage, 1000, 1180, $prepared->proof);
        self::assertTrue($strategy->verify($active, $renderer->answer));
        self::assertTrue($strategy->verify($active, " \t" . strtolower($renderer->answer) . "\r\n"));
        foreach (['', '23456', '2345678', 'IIIIII', "23 567", "23\t567", "\0" . $renderer->answer,
            "\v" . $renderer->answer, "\xC2\xA0" . $renderer->answer, 'А23456', str_repeat('x', 65),
            str_repeat(' ', 65) . $renderer->answer] as $wrong) {
            self::assertFalse($strategy->verify($active, $wrong));
        }
        self::assertFalse($strategy->verify($active, $renderer->answer === '234567' ? '234568' : '234567'));
    }

    public function testRendererFailureAndProfileRejection(): void
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
        $failing->prepare($id, $purpose);
    }

    public function testWrongProfileRejected(): void
    {
        foreach ([['image/jpeg', 240, 80], ['image/png', 241, 80], ['image/png', 240, 81]] as [$mime, $width, $height]) {
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
