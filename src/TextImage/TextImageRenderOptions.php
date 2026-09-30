<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\TextImage;

use InvalidArgumentException;

/**
 * EN: Options only for the TextImage challenge/rendering strategy, validated before rendering or allocation.
 * RU: Настройки только для стратегии задания/отрисовки TextImage, проверяемые до отрисовки и выделения памяти.
 */
final readonly class TextImageRenderOptions
{
    /**
     * EN: scalePercent supports 100, 105, 110, 115, 120; 100 is the canonical 240×80 profile.
     * RU: scalePercent поддерживает 100, 105, 110, 115, 120; 100 — канонический профиль 240×80.
     * EN: Scaling preserves the aspect ratio. blur selects text softness; Standard is the accepted default profile.
     * RU: Масштаб сохраняет пропорции. blur задаёт мягкость текста; Standard — принятый профиль по умолчанию.
     * EN: lightNoisePercent (0..150) affects only light-colored interference; 100 preserves its default density.
     * RU: lightNoisePercent (0..150) влияет только на светлые помехи; 100 сохраняет их плотность по умолчанию.
     * EN: maxRotationDegrees (0..28) is the maximum absolute per-glyph rotation; 0 disables rotation.
     * RU: maxRotationDegrees (0..28) — максимальный абсолютный угол поворота каждого глифа; 0 отключает поворот.
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        public int $scalePercent = 100,
        public TextImageBlur $blur = TextImageBlur::Standard,
        public int $lightNoisePercent = 100,
        public int $maxRotationDegrees = 28,
    ) {
        if (!in_array($scalePercent, [100, 105, 110, 115, 120], true)) {
            throw new InvalidArgumentException('Invalid TextImage scale percent.');
        }
        if ($lightNoisePercent < 0 || $lightNoisePercent > 150) {
            throw new InvalidArgumentException('Invalid TextImage light noise percent.');
        }
        if ($maxRotationDegrees < 0 || $maxRotationDegrees > 28) {
            throw new InvalidArgumentException('Invalid TextImage maximum rotation degrees.');
        }
    }
}
