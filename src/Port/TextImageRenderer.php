<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Port;

use Yaleksandr\HumanGate\Presentation\ImagePresentation;

interface TextImageRenderer
{
    /**
     * RU: Отрисовывает канонический шестибайтовый ASCII-ответ любого протокола TextImage, сохраняя регистр.
     * RU: Возвращает байты изображения без вывода данных; при ошибке выбрасывает исключение.
     * EN: Render a canonical six-byte ASCII answer from either TextImage protocol, preserving case.
     * EN: Returns image bytes without emitting output; failures throw.
     */
    public function render(string $canonicalAnswer): ImagePresentation;
}
