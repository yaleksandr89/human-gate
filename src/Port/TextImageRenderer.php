<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Port;

use Yaleksandr\HumanGate\Presentation\ImagePresentation;

interface TextImageRenderer
{
    /**
     * EN: Render a canonical six-byte ASCII answer from either TextImage protocol, preserving case.
     * RU: Отрисовывает канонический шестибайтовый ASCII-ответ любого протокола TextImage, сохраняя регистр.
     * EN: Returns image bytes without emitting output; failures throw.
     * RU: Возвращает байты изображения без вывода данных; при ошибке выбрасывает исключение.
     */
    public function render(string $canonicalAnswer): ImagePresentation;
}
