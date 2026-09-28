<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Presentation;

use InvalidArgumentException;

final readonly class ImagePresentation implements Presentation
{
    public function __construct(
        public string $mimeType,
        public string $bytes,
        public int $width,
        public int $height,
    ) {
        if (!in_array($mimeType, ['image/png', 'image/jpeg', 'image/webp'], true)
            || $bytes === '' || strlen($bytes) > 131072
            || $width < 1 || $height < 1 || $width > 4096 || $height > 4096) {
            throw new InvalidArgumentException('Invalid image presentation.');
        }
    }
}
