<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\TextImage;

/**
 * RU: Управляет генерацией и проверкой ответа TextImage независимо от отрисовки.
 * EN: Controls TextImage answer generation and verification independently of rendering.
 */
final readonly class TextImageAnswerOptions
{
    /**
     * RU: По умолчанию регистр не учитывается; чувствительные к регистру ответы сохраняют точный регистр ASCII.
     * EN: Case-insensitive answers are the default; sensitive answers preserve exact ASCII case.
     */
    public function __construct(
        public bool $caseSensitive = false,
    ) {}
}
