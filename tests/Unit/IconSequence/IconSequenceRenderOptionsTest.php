<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Unit\IconSequence;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\HumanGate\IconSequence\IconSequenceBlur;
use Yaleksandr\HumanGate\IconSequence\IconSequenceRenderOptions;

#[TestDox('Настройки отрисовки IconSequence проверяются до выделения изображения')]
final class IconSequenceRenderOptionsTest extends TestCase
{
    public function testDefaults(): void
    {
        $options = new IconSequenceRenderOptions();
        self::assertSame(72, $options->minIconSize);
        self::assertSame(80, $options->maxIconSize);
        self::assertSame(IconSequenceBlur::Strong, $options->blur);
        self::assertSame(150, $options->noisePercent);
        self::assertSame(90, $options->maxRotationDegrees);
    }

    #[TestDox('Граничные значения и все пресеты размытия допустимы')]
    public function testValidBoundaries(): void
    {
        foreach ([[72, 72, 90], [72, 90, 90], [80, 88, 45], [76, 84, 60], [72, 80, 90], [72, 92, 30], [92, 92, 30], [72, 96, 25], [96, 96, 25]] as [$min, $max, $limit]) {
            foreach (IconSequenceBlur::cases() as $blur) {
                foreach ([0, 150] as $noise) {
                    foreach ([0, 15, $limit] as $rotation) {
                        $options = new IconSequenceRenderOptions($min, $max, $blur, $noise, $rotation);
                        self::assertSame([$min, $max, $blur, $noise, $rotation], [
                            $options->minIconSize,
                            $options->maxIconSize,
                            $options->blur,
                            $options->noisePercent,
                            $options->maxRotationDegrees,
                        ]);
                    }
                }
            }
        }
    }

    #[TestDox('Неверные диапазоны сразу отклоняются с явной причиной')]
    public function testInvalidValues(): void
    {
        foreach ([
            ['minIconSize', [PHP_INT_MIN, 71, 97, PHP_INT_MAX], 'Invalid IconSequence minimum icon size.'],
            ['maxIconSize', [PHP_INT_MIN, 71, 97, PHP_INT_MAX], 'Invalid IconSequence maximum icon size.'],
            ['noisePercent', [PHP_INT_MIN, -1, 151, PHP_INT_MAX], 'Invalid IconSequence noise percent.'],
            ['maxRotationDegrees', [PHP_INT_MIN, -1, 91, PHP_INT_MAX], 'Invalid IconSequence maximum rotation degrees.'],
        ] as [$option, $values, $message]) {
            foreach ($values as $value) {
                try {
                    new IconSequenceRenderOptions(...[$option => $value]);
                    self::fail('Invalid render option accepted.');
                } catch (InvalidArgumentException $exception) {
                    self::assertSame($message, $exception->getMessage());
                }
            }
        }
        try {
            new IconSequenceRenderOptions(minIconSize: 80, maxIconSize: 79);
            self::fail('Inverted icon size range accepted.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Invalid IconSequence maximum icon size.', $exception->getMessage());
        }
    }

    #[TestDox('Сочетания размера и поворота, превышающие высоту цели, отклоняются конструктором')]
    public function testImpossibleSizeAndRotationCombinations(): void
    {
        foreach ([[94, 30], [96, 26], [96, 30], [91, 45], [91, 60], [91, 90], [96, 90]] as [$size, $rotation]) {
            try {
                new IconSequenceRenderOptions(maxIconSize: $size, maxRotationDegrees: $rotation);
                self::fail('Impossible render geometry accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Invalid IconSequence size and rotation combination.', $exception->getMessage());
            }
        }
    }
}
