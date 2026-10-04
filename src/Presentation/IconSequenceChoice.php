<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Presentation;

use InvalidArgumentException;

/**
 * EN: Labels are plain text; consumers must escape them for their output context. Human Gate does not emit HTML.
 * RU: Подписи являются обычным текстом; приложения должны экранировать их для контекста вывода. Human Gate не выдаёт HTML.
 * EN: Consumers use these labels for alt, aria-label, or an equivalent accessible image label.
 * RU: Приложения используют эти подписи для alt, aria-label или равноценной доступной подписи изображения.
 */
final readonly class IconSequenceChoice
{
    public function __construct(public string $token, public string $label, public ImagePresentation $image)
    {
        if (preg_match('/\A[0-9a-f]{32}\z/', $token) !== 1) {
            throw new InvalidArgumentException('Invalid icon sequence choice token.');
        }
        if (
            $label === ''
            || strlen($label) > 80
            || preg_match('//u', $label) !== 1
            || preg_match('/[\x00-\x1f\x7f]/', $label) !== 0
            || trim($label, " \t\r\n") !== $label
        ) {
            throw new InvalidArgumentException('Invalid icon sequence choice label.');
        }
        if ($image->mimeType !== 'image/png') {
            throw new InvalidArgumentException('Invalid icon sequence choice image.');
        }
    }
}
