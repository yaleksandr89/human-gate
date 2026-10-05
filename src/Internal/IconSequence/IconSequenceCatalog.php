<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Internal\IconSequence;

use InvalidArgumentException;
use Yaleksandr\HumanGate\Exception\RenderingException;
use Yaleksandr\HumanGate\Presentation\ImagePresentation;

final class IconSequenceCatalog
{
    private const int MAX_PNG_BYTES = 131_072;

    private const array NAMES = [
        'anchor',
        'apple',
        'bell',
        'camera',
        'car',
        'fish',
        'gift',
        'home',
        'key',
        'moon',
        'plane',
        'rocket',
        'star',
        'sun',
        'tree',
        'umbrella',
    ];

    /** @return list<string> */
    public static function names(): array
    {
        return self::NAMES;
    }

    public static function image(string $name): ImagePresentation
    {
        self::requireKnown($name);
        $path = dirname(__DIR__, 3) . '/resources/icons/tabler/' . $name . '.png';
        if (!is_file($path) || !is_readable($path)) {
            throw new RenderingException('Package icon unavailable.');
        }
        $bytes = @file_get_contents($path, false, null, 0, self::MAX_PNG_BYTES + 1);
        if ($bytes === false || $bytes === '' || strlen($bytes) > self::MAX_PNG_BYTES) {
            throw new RenderingException('Package icon read failed or size invalid.');
        }
        $image = new ImagePresentation('image/png', $bytes, 96, 96);
        IconSequenceRenderer::decode($image);

        return $image;
    }

    private static function requireKnown(string $name): void
    {
        if (!in_array($name, self::NAMES, true)) {
            throw new InvalidArgumentException('Unknown package icon.');
        }
    }
}
