<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\TextImage;

/**
 * EN: Text softness presets only for the TextImage challenge/rendering strategy.
 * RU: Пресеты мягкости текста только для стратегии задания/отрисовки TextImage.
 */
enum TextImageBlur
{
    /**
     * EN: No text blur.
     * RU: Без размытия текста.
     */
    case None;
    /**
     * EN: Light text blur.
     * RU: Лёгкое размытие текста.
     */
    case Light;
    /**
     * EN: Standard text blur: the accepted default profile.
     * RU: Стандартное размытие текста: принятый профиль по умолчанию.
     */
    case Standard;
    /**
     * EN: Stronger text blur than Standard.
     * RU: Более сильное размытие текста, чем Standard.
     */
    case Strong;
}
