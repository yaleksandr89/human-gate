<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Unit\Presentation;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\HumanGate\Presentation\ImagePresentation;

#[TestDox('Изображение ограничивает размер и метаданные')]
final class ImagePresentationTest extends TestCase
{
    public function testLimits(): void
    {
        self::assertSame(131072, strlen(new ImagePresentation('image/png', str_repeat('x', 131072), 240, 80)->bytes));
        foreach ([['', 'x', 1, 1], ['text/html', 'x', 1, 1], ['image/png', '', 1, 1], ['image/png', str_repeat('x', 131073), 1, 1], ['image/png', 'x', 0, 1], ['image/png', 'x', 4097, 1]] as [$mime, $bytes, $width, $height]) {
            try {
                new ImagePresentation($mime, $bytes, $width, $height);
                self::fail('Invalid presentation was accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Invalid image presentation.', $exception->getMessage());
            }
        }
    }
}
