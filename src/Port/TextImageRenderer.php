<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Port;

use Yaleksandr\HumanGate\Presentation\ImagePresentation;

interface TextImageRenderer
{
    /**
     * EN: Render a canonical answer into image bytes without emitting output; failures throw.
     * RU: Преобразует канонический ответ в байты изображения без вывода данных; при ошибке выбрасывает исключение.
     */
    public function render(string $canonicalAnswer): ImagePresentation;
}
