<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Presentation;

use InvalidArgumentException;

final readonly class IconSequenceChoice
{
    public function __construct(public string $token, public ImagePresentation $image)
    {
        if (preg_match('/\A[0-9a-f]{32}\z/', $token) !== 1) {
            throw new InvalidArgumentException('Invalid icon sequence choice token.');
        }
        if ($image->mimeType !== 'image/png') {
            throw new InvalidArgumentException('Invalid icon sequence choice image.');
        }
    }
}
