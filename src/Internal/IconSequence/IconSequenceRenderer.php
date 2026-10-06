<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Internal\IconSequence;

use GdImage;
use InvalidArgumentException;
use Random\RandomException;
use Yaleksandr\HumanGate\Exception\RenderingException;
use Yaleksandr\HumanGate\IconSequence\IconSequenceBlur;
use Yaleksandr\HumanGate\IconSequence\IconSequenceRenderOptions;
use Yaleksandr\HumanGate\Presentation\ImagePresentation;

final class IconSequenceRenderer
{
    private const int MAX_PNG_BYTES = 131_072;
    private const string PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    public function __construct(private readonly IconSequenceRenderOptions $options = new IconSequenceRenderOptions()) {}

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
            $size = random_int($this->options->minIconSize, $this->options->maxIconSize);
            $scaled = $size === 96 ? $source : imagescale($source, $size, $size, IMG_BICUBIC_FIXED);
            if (!$scaled instanceof GdImage) {
                throw new RenderingException('Icon scaling failed.');
            }
            $rotated = $scaled;
            if ($this->options->maxRotationDegrees > 0) {
                $transparent = imagecolorallocatealpha($scaled, 0, 0, 0, 127);
                if ($transparent === false) {
                    throw new RenderingException('Icon transparency allocation failed.');
                }
                $rotated = imagerotate(
                    $scaled,
                    random_int(-$this->options->maxRotationDegrees, $this->options->maxRotationDegrees),
                    $transparent,
                );
                if (!$rotated instanceof GdImage) {
                    throw new RenderingException('Icon rotation failed.');
                }
            }
            $iconWidth = imagesx($rotated);
            $iconHeight = imagesy($rotated);
            if ($iconHeight > $height) {
                throw new RenderingException('Rotated icon exceeds sequence target height.');
            }
            $x = 12 + $index * 112 + intdiv(112 - $iconWidth, 2);
            $centerY = intdiv($height - $iconHeight, 2);
            $jitter = min(4, $centerY);
            $y = $centerY + random_int(-$jitter, $jitter);
            imagecopy($canvas, $rotated, $x, $y, 0, 0, $iconWidth, $iconHeight);
        }

        $this->blur($canvas);
        $this->drawNoise($canvas, $width, $height);

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

    private function blur(GdImage $canvas): void
    {
        $passes = match ($this->options->blur) {
            IconSequenceBlur::None => 0,
            IconSequenceBlur::Light => 1,
            IconSequenceBlur::Standard => 3,
            IconSequenceBlur::Strong => 7,
        };
        $gaussian = [
            [1.0, 2.0, 1.0],
            [2.0, 4.0, 2.0],
            [1.0, 2.0, 1.0],
        ];
        for ($pass = 0; $pass < $passes; ++$pass) {
            if (!imageconvolution($canvas, $gaussian, 16.0, 0.0)) {
                throw new RenderingException('Sequence blur failed.');
            }
        }
    }

    private function drawNoise(GdImage $canvas, int $width, int $height): void
    {
        if ($this->options->noisePercent === 0) {
            return;
        }
        foreach ([[240, 221], [120, 185]] as [$baseCount, $shade]) {
            $color = imagecolorallocate($canvas, $shade, $shade, $shade);
            if ($color === false) {
                throw new RenderingException('Sequence noise color allocation failed.');
            }
            $count = (int) round($baseCount * $width / 472 * $this->options->noisePercent / 100);
            for ($dot = 0; $dot < $count; ++$dot) {
                imagesetpixel($canvas, random_int(0, $width - 1), random_int(0, $height - 1), $color);
            }
        }
        $lineColor = imagecolorallocate($canvas, 210, 210, 210);
        if ($lineColor === false) {
            throw new RenderingException('Sequence line color allocation failed.');
        }
        $lines = (int) round(5 * $this->options->noisePercent / 100);
        for ($line = 0; $line < $lines; ++$line) {
            imageline(
                $canvas,
                random_int(0, $width - 1),
                random_int(0, $height - 1),
                random_int(0, $width - 1),
                random_int(0, $height - 1),
                $lineColor,
            );
        }
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
            'imageconvolution',
            'imagesetpixel',
            'imageline',
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
