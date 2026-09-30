<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Unit\TextImage;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\HumanGate\TextImage\TextImageBlur;
use Yaleksandr\HumanGate\TextImage\TextImageRenderOptions;

#[TestDox('Настройки TextImage проверяются до отрисовки')]
final class TextImageRenderOptionsTest extends TestCase
{
    #[TestDox('Значения по умолчанию сохраняют исходный профиль')]
    public function testDefaults(): void
    {
        $options = new TextImageRenderOptions();
        self::assertSame(100, $options->scalePercent);
        self::assertSame(TextImageBlur::Standard, $options->blur);
        self::assertSame(100, $options->lightNoisePercent);
        self::assertSame(28, $options->maxRotationDegrees);
    }

    #[TestDox('Недопустимые числовые настройки сразу вызывают явное исключение')]
    public function testInvalidValuesRejectedDuringConstruction(): void
    {
        foreach ([
            ['scalePercent', [-1, 0, 99, 101, 104, 106, 109, 111, 114, 116, 119, 121, PHP_INT_MAX], 'Invalid TextImage scale percent.'],
            ['lightNoisePercent', [-1, 151, PHP_INT_MAX], 'Invalid TextImage light noise percent.'],
            ['maxRotationDegrees', [-1, 29, PHP_INT_MAX], 'Invalid TextImage maximum rotation degrees.'],
        ] as [$option, $values, $message]) {
            foreach ($values as $value) {
                try {
                    new TextImageRenderOptions(...[$option => $value]);
                    self::fail('Invalid render option was accepted.');
                } catch (InvalidArgumentException $exception) {
                    self::assertSame($message, $exception->getMessage());
                }
            }
        }
    }
}
