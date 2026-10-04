<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Internal\IconSequence;

use GdImage;
use InvalidArgumentException;
use Random\RandomException;
use Yaleksandr\HumanGate\Exception\RenderingException;
use Yaleksandr\HumanGate\Presentation\ImagePresentation;

final class IconSequenceRenderer
{
    private const int MAX_PNG_BYTES = 131_072;
    private const string PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    /**
     * EN: Accepts only a list of three to six distinct package icon images.
     * RU: Принимает только список из трёх–шести различных изображений значков пакета.
     * EN: Random-source failures propagate unchanged.
     * RU: Ошибки источника случайности передаются без изменений.
     *
     * @param array<array-key, ImagePresentation> $sequence
     * @throws InvalidArgumentException
     * @throws RandomException
     * @throws RenderingException
     */
    public function render(array $sequence): ImagePresentation
    {
        if (!array_is_list($sequence) || count($sequence) < 3 || count($sequence) > 6) {
            throw new InvalidArgumentException('Invalid icon sequence rendering input.');
        }
        self::requireGd();
        $width = count($sequence) * 112 + 24;
        $height = 128;
        $canvas = imagecreatetruecolor($width, $height);
        if (!$canvas instanceof GdImage) {
            throw new RenderingException('Sequence image allocation failed.');
        }
        $background = imagecolorallocate($canvas, 245, 246, 248);
        if ($background === false) {
            throw new RenderingException('Sequence background failed.');
        }
        imagefill($canvas, 0, 0, $background);
        $seen = [];
        foreach ($sequence as $index => $presentation) {
            $source = self::decode($presentation);
            if (in_array($presentation->bytes, $seen, true)) {
                throw new InvalidArgumentException('Duplicate sequence image.');
            }
            $seen[] = $presentation->bytes;
            $size = random_int(76, 84);
            $scaled = imagescale($source, $size, $size, IMG_BICUBIC_FIXED);
            if (!$scaled instanceof GdImage) {
                throw new RenderingException('Icon scaling failed.');
            }
            $transparent = imagecolorallocatealpha($scaled, 0, 0, 0, 127);
            if ($transparent === false) {
                throw new RenderingException('Icon transparency allocation failed.');
            }
            $rotated = imagerotate($scaled, random_int(-7, 7), $transparent);
            if (!$rotated instanceof GdImage) {
                throw new RenderingException('Icon rotation failed.');
            }
            $iconWidth = imagesx($rotated);
            $iconHeight = imagesy($rotated);
            $x = 12 + $index * 112 + intdiv(112 - $iconWidth, 2);
            $y = intdiv($height - $iconHeight, 2) + random_int(-4, 4);
            imagecopy($canvas, $rotated, $x, $y, 0, 0, $iconWidth, $iconHeight);
        }

        $bufferLevel = ob_get_level();
        if (!ob_start()) {
            throw new RenderingException('PNG buffer setup failed.');
        }
        try {
            if (imagepng($canvas, null, 6) === false) {
                throw new RenderingException('PNG encoding failed.');
            }
            $bytes = ob_get_clean();
            if (!is_string($bytes) || $bytes === '' || strlen($bytes) > self::MAX_PNG_BYTES) {
                throw new RenderingException('Sequence PNG size invalid.');
            }
        } finally {
            while (ob_get_level() > $bufferLevel) {
                if (!ob_end_clean()) {
                    throw new RenderingException('PNG buffer cleanup failed.');
                }
            }
        }
        $metadata = getimagesizefromstring($bytes);
        if ($metadata === false || $metadata[0] !== $width || $metadata[1] !== $height || $metadata[2] !== IMAGETYPE_PNG) {
            throw new RenderingException('Sequence PNG metadata invalid.');
        }

        return new ImagePresentation('image/png', $bytes, $width, $height);
    }

    public static function decode(ImagePresentation $image): GdImage
    {
        self::requireGd();
        if (
            $image->mimeType !== 'image/png'
            || $image->width !== 96
            || $image->height !== 96
            || !str_starts_with($image->bytes, self::PNG_SIGNATURE)
        ) {
            throw new RenderingException('Package icon PNG profile invalid.');
        }
        $metadata = @getimagesizefromstring($image->bytes);
        if ($metadata === false || $metadata[0] !== 96 || $metadata[1] !== 96 || $metadata[2] !== IMAGETYPE_PNG) {
            throw new RenderingException('Package icon PNG metadata invalid.');
        }
        $decoded = @imagecreatefromstring($image->bytes);
        if (!$decoded instanceof GdImage || imagesx($decoded) !== 96 || imagesy($decoded) !== 96) {
            throw new RenderingException('Package icon PNG decoding failed.');
        }

        return $decoded;
    }

    private static function requireGd(): void
    {
        foreach ([
            'imagecreatetruecolor',
            'imagecolorallocate',
            'imagecolorallocatealpha',
            'imagefill',
            'imagescale',
            'imagerotate',
            'imagecopy',
            'imagepng',
            'imagecreatefromstring',
            'getimagesizefromstring',
            'imagesx',
            'imagesy',
        ] as $function) {
            if (!function_exists($function)) {
                throw new RenderingException('Required GD capability unavailable.');
            }
        }
    }
}
