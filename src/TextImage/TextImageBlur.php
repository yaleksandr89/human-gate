<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\TextImage;

/**
 * RU: Пресеты мягкости текста только для стратегии задания/отрисовки TextImage.
 * EN: Text softness presets only for the TextImage challenge/rendering strategy.
 */
enum TextImageBlur
{
    /**
     * RU: Без размытия текста.
     * EN: No text blur.
     */
    case None;
    /**
     * RU: Лёгкое размытие текста.
     * EN: Light text blur.
     */
    case Light;
    /**
     * RU: Стандартное размытие текста: принятый профиль по умолчанию.
     * EN: Standard text blur: the accepted default profile.
     */
    case Standard;
    /**
     * RU: Более сильное размытие текста, чем Standard.
     * EN: Stronger text blur than Standard.
     */
    case Strong;
}
