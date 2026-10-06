<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\IconSequence;

use InvalidArgumentException;

final readonly class IconSequenceRenderOptions
{
    /**
     * EN: Controls target rendering only; choice images and target geometry remain fixed.
     * RU: Управляет только отрисовкой цели; изображения вариантов и геометрия цели остаются фиксированными.
     * EN: Icon sizes are 72..96 pixels; noisePercent is 0..150; maxRotationDegrees is 0..90.
     * RU: Размеры значков — 72..96 пикселей; noisePercent — 0..150; maxRotationDegrees — 0..90.
     * EN: The maximum rotated square extent must fit within the fixed 128-pixel target height.
     * RU: Максимальный габарит повёрнутого квадрата должен помещаться в фиксированную высоту цели 128 пикселей.
     * EN: Zero noise or rotation disables that effect; 100 sets the base noise density.
     * RU: Нулевой шум или поворот отключает соответствующий эффект; 100 задаёт базовую плотность шума.
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        public int $minIconSize = 72,
        public int $maxIconSize = 80,
        public IconSequenceBlur $blur = IconSequenceBlur::Strong,
        public int $noisePercent = 150,
        public int $maxRotationDegrees = 90,
    ) {
        if ($minIconSize < 72 || $minIconSize > 96) {
            throw new InvalidArgumentException('Invalid IconSequence minimum icon size.');
        }
        if ($maxIconSize < 72 || $maxIconSize > 96 || $maxIconSize < $minIconSize) {
            throw new InvalidArgumentException('Invalid IconSequence maximum icon size.');
        }
        if ($noisePercent < 0 || $noisePercent > 150) {
            throw new InvalidArgumentException('Invalid IconSequence noise percent.');
        }
        if ($maxRotationDegrees < 0 || $maxRotationDegrees > 90) {
            throw new InvalidArgumentException('Invalid IconSequence maximum rotation degrees.');
        }
        $angle = deg2rad(min($maxRotationDegrees, 45));
        $rotatedExtent = (int) ceil($maxIconSize * (cos($angle) + sin($angle)));
        if ($rotatedExtent > 128) {
            throw new InvalidArgumentException('Invalid IconSequence size and rotation combination.');
        }
    }
}
