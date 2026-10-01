<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\TextImage;

/**
 * EN: Controls TextImage answer generation and verification independently of rendering.
 * RU: Управляет генерацией и проверкой ответа TextImage независимо от отрисовки.
 */
final readonly class TextImageAnswerOptions
{
    /**
     * EN: Case-insensitive answers are the default; sensitive answers preserve exact ASCII case.
     * RU: По умолчанию регистр не учитывается; чувствительные к регистру ответы сохраняют точный регистр ASCII.
     */
    public function __construct(
        public bool $caseSensitive = false,
    ) {}
}
