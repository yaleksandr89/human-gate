<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\TextImage;

use GdImage;
use InvalidArgumentException;
use Random\RandomException;
use Yaleksandr\HumanGate\Exception\RenderingException;
use Yaleksandr\HumanGate\Internal\TextImage\TextImageAnswer;
use Yaleksandr\HumanGate\Port\TextImageRenderer;
use Yaleksandr\HumanGate\Presentation\ImagePresentation;

/**
 * EN: Renders the configurable TextImage profile using the package-owned Noto Sans font.
 * RU: Создаёт изображение по настраиваемому профилю TextImage со шрифтом Noto Sans из пакета.
 */
final class GdTextImageRenderer implements TextImageRenderer
{
    private const int WIDTH = 240;
    private const int HEIGHT = 80;
    private const int MAX_PNG_BYTES = 131072;
    private const string PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    public function __construct(private readonly TextImageRenderOptions $options = new TextImageRenderOptions()) {}

    private function scaled(int $coordinate): int
    {
        return (int) round($coordinate * $this->options->scalePercent / 100);
    }

    private function randomCoordinate(int $min, int $max): int
    {
        return random_int($this->scaled($min), $this->scaled($max));
    }

    /**
     * EN: Renders only a canonical six-character answer; random-source failures propagate.
     * RU: Отрисовывает только канонический ответ из шести символов; ошибки источника случайности проходят без замены.
     *
     * @throws RandomException
     */
    public function render(string $canonicalAnswer): ImagePresentation
    {
        if (strlen($canonicalAnswer) !== 6 || strspn($canonicalAnswer, TextImageAnswer::RENDERABLE_ALPHABET) !== 6) {
            throw new InvalidArgumentException('Invalid TextImage answer.');
        }

        foreach (['imagecreatetruecolor', 'imagecolorallocate', 'imagefill', 'imagesetpixel', 'imageline', 'imagearc', 'imagettfbbox', 'imagettftext', 'imageconvolution', 'imagecopy', 'imagepng', 'getimagesizefromstring'] as $function) {
            if (!function_exists($function)) {
                throw new RenderingException('Required GD or FreeType capability unavailable.');
            }
        }

        $font = dirname(__DIR__, 2) . '/resources/fonts/NotoSans-Regular.ttf';
        if (!is_file($font) || !is_readable($font)) {
            throw new RenderingException('Package font unavailable.');
        }

        $width = $this->scaled(self::WIDTH);
        $height = $this->scaled(self::HEIGHT);
        if ($width < 1 || $height < 1) {
            throw new RenderingException('Image dimensions invalid.');
        }
        $fontSize = 28 * $this->options->scalePercent / 100;

        $image = @imagecreatetruecolor($width, $height);
        if (!$image instanceof GdImage) {
            throw new RenderingException('Image allocation failed.');
        }

        $background = @imagecolorallocate($image, 188, 197, 208);
        $line = @imagecolorallocate($image, 108, 124, 145);
        $dot = @imagecolorallocate($image, 81, 100, 124);
        $foregroundNoise = @imagecolorallocate($image, 64, 82, 105);
        $lightNoise = @imagecolorallocate($image, 226, 231, 237);

        if (
            $background === false
            || $line === false
            || $dot === false
            || $foregroundNoise === false
            || $lightNoise === false
        ) {
            throw new RenderingException('Color allocation failed.');
        }

        if (!@imagefill($image, 0, 0, $background)) {
            throw new RenderingException('Background fill failed.');
        }

        $textLayer = @imagecreatetruecolor($width, $height);
        /** @var GdImage|false $textLayer */
        if ($textLayer === false) {
            throw new RenderingException('Text layer allocation failed.');
        }
        $textLayerBackground = @imagecolorallocate($textLayer, 188, 197, 208);
        $textLayerColor = @imagecolorallocate($textLayer, 39, 52, 70);
        if ($textLayerBackground === false || $textLayerColor === false) {
            throw new RenderingException('Text layer color allocation failed.');
        }
        if (!@imagefill($textLayer, 0, 0, $textLayerBackground)) {
            throw new RenderingException('Text layer fill failed.');
        }

        $x = $this->randomCoordinate(18, 22);
        for ($i = 0; $i < 6; ++$i) {
            $angle = random_int(-$this->options->maxRotationDegrees, $this->options->maxRotationDegrees);
            $character = $canonicalAnswer[$i];

            $bbox = @imagettfbbox($fontSize, $angle, $font, $character);
            if ($bbox === false) {
                throw new RenderingException('Text bounding box failed.');
            }

            foreach ([0, 2, 4, 6] as $corner) {
                if (
                    !isset($bbox[$corner], $bbox[$corner + 1])
                    || !is_int($bbox[$corner])
                    || !is_int($bbox[$corner + 1])
                ) {
                    throw new RenderingException('Text bounding box invalid.');
                }
            }

            $minX = min($bbox[0], $bbox[2], $bbox[4], $bbox[6]);
            $maxX = max($bbox[0], $bbox[2], $bbox[4], $bbox[6]);
            $minY = min($bbox[1], $bbox[3], $bbox[5], $bbox[7]);
            $maxY = max($bbox[1], $bbox[3], $bbox[5], $bbox[7]);

            // EN: Keep negative glyph bearings inside the scaled left guard.
            // RU: Удерживаем отрицательный вынос глифа внутри масштабированной левой границы.
            $x = max($x, $this->scaled(6) - $minX);

            $baselineMin = max($this->scaled(41), $this->scaled(10) - $minY);
            $baselineMax = min($this->scaled(62), $this->scaled(70) - $maxY);
            $baselineRange = $baselineMax - $baselineMin;
            if ($baselineRange < 0) {
                throw new RenderingException('Text baseline outside guard.');
            }
            $baselineY = $baselineMin + random_int(0, $baselineRange);

            if (
                $x + $minX < $this->scaled(6)
                || $x + $maxX > $this->scaled(234)
                || $baselineY + $minY < $this->scaled(8)
                || $baselineY + $maxY > $this->scaled(72)
            ) {
                throw new RenderingException('Text bounding box outside guard.');
            }

            if (
                @imagettftext(
                    $textLayer,
                    $fontSize,
                    $angle,
                    $x,
                    $baselineY,
                    $textLayerColor,
                    $font,
                    $character,
                ) === false
            ) {
                throw new RenderingException('Text rendering failed.');
            }

            $x += $this->randomCoordinate(25, 31);
        }

        $gaussian = [
            [1.0, 2.0, 1.0],
            [2.0, 4.0, 2.0],
            [1.0, 2.0, 1.0],
        ];
        $blurPasses = match ($this->options->blur) {
            TextImageBlur::None => 0,
            TextImageBlur::Light => 1,
            TextImageBlur::Standard => 3,
            TextImageBlur::Strong => 4,
        };
        for ($i = 0; $i < $blurPasses; ++$i) {
            if (!@imageconvolution($textLayer, $gaussian, 16.0, 0.0)) {
                throw new RenderingException('Text blur failed.');
            }
        }

        if (!@imagecopy($image, $textLayer, 0, 0, 0, 0, $width, $height)) {
            throw new RenderingException('Text layer compositing failed.');
        }

        $lightNoiseCount = (int) round(190 * $this->options->lightNoisePercent / 100 * ($this->options->scalePercent / 100) ** 2);
        for ($i = 0; $i < $lightNoiseCount; ++$i) {
            $y = random_int(0, 4) === 0
                ? $this->randomCoordinate(8, 71)
                : $this->randomCoordinate(19, 62);

            if (!@imagesetpixel($image, $this->randomCoordinate(6, 233), $y, $lightNoise)) {
                throw new RenderingException('Light noise rendering failed.');
            }
        }

        for ($i = 0; $i < 4; ++$i) {
            if (
                !@imageline(
                    $image,
                    $this->randomCoordinate(4, 40),
                    $this->randomCoordinate(16, 64),
                    $this->randomCoordinate(199, 235),
                    $this->randomCoordinate(16, 64),
                    $line,
                )
            ) {
                throw new RenderingException('Line noise rendering failed.');
            }
        }

        for ($i = 0; $i < 5; ++$i) {
            $arcWidth = $this->randomCoordinate(48, 84);
            $arcHeight = $this->randomCoordinate(20, 36);

            if (
                !@imagearc(
                    $image,
                    $this->randomCoordinate(50, 190),
                    $this->randomCoordinate(30, 53),
                    $arcWidth,
                    $arcHeight,
                    random_int(0, 120),
                    random_int(180, 300),
                    $line,
                )
            ) {
                throw new RenderingException('Curved noise rendering failed.');
            }
        }

        for ($i = 0; $i < 320; ++$i) {
            $y = random_int(0, 11) === 0
                ? $this->randomCoordinate(12, 68)
                : $this->randomCoordinate(23, 58);

            if (!@imagesetpixel($image, $this->randomCoordinate(12, 228), $y, $dot)) {
                throw new RenderingException('Dot noise rendering failed.');
            }
        }

        for ($i = 0; $i < 20; ++$i) {
            $x = $this->randomCoordinate(22, 210);
            $y = $this->randomCoordinate(26, 56);

            if (
                !@imageline(
                    $image,
                    $x,
                    $y,
                    min($this->scaled(228), $x + $this->randomCoordinate(3, 10)),
                    min($this->scaled(64), max($this->scaled(16), $y + $this->randomCoordinate(-3, 3))),
                    $dot,
                )
            ) {
                throw new RenderingException('Short noise rendering failed.');
            }
        }

        if (
            !@imageline(
                $image,
                $this->randomCoordinate(12, 40),
                $this->randomCoordinate(30, 48),
                $this->randomCoordinate(200, 228),
                $this->randomCoordinate(32, 50),
                $foregroundNoise,
            )
        ) {
            throw new RenderingException('Foreground line noise rendering failed.');
        }

        for ($i = 0; $i < 3; ++$i) {
            if (
                !@imagearc(
                    $image,
                    $this->scaled(50 + (70 * $i)) + $this->randomCoordinate(-10, 10),
                    $this->randomCoordinate(30, 51),
                    $this->randomCoordinate(48, 78),
                    $this->randomCoordinate(22, 36),
                    random_int(20, 100),
                    random_int(190, 280),
                    $foregroundNoise,
                )
            ) {
                throw new RenderingException('Foreground curved noise rendering failed.');
            }
        }

        for ($i = 0; $i < 6; ++$i) {
            $x = $this->randomCoordinate(30, 200);
            $y = $this->randomCoordinate(29, 50);

            if (
                !@imageline(
                    $image,
                    $x,
                    $y,
                    min($this->scaled(228), $x + $this->randomCoordinate(5, 14)),
                    min($this->scaled(60), max($this->scaled(20), $y + $this->randomCoordinate(-5, 5))),
                    $foregroundNoise,
                )
            ) {
                throw new RenderingException('Foreground short noise rendering failed.');
            }
        }

        $bufferLevel = ob_get_level();
        if (!@ob_start()) {
            throw new RenderingException('PNG buffer setup failed.');
        }
        try {
            if (!@imagepng($image, null, 6)) {
                throw new RenderingException('PNG encoding failed.');
            }
            $bytes = @ob_get_clean();
            if (!is_string($bytes)) {
                throw new RenderingException('PNG capture failed.');
            }
        } finally {
            while (ob_get_level() > $bufferLevel) {
                if (!@ob_end_clean()) {
                    throw new RenderingException('PNG buffer cleanup failed.');
                }
            }
        }

        if ($bytes === '' || strlen($bytes) > self::MAX_PNG_BYTES) {
            throw new RenderingException('PNG size invalid.');
        }
        if (!str_starts_with($bytes, self::PNG_SIGNATURE)) {
            throw new RenderingException('PNG signature invalid.');
        }
        $metadata = @getimagesizefromstring($bytes);
        if ($metadata === false || $metadata[0] !== $width || $metadata[1] !== $height || $metadata[2] !== IMAGETYPE_PNG || $metadata['mime'] !== 'image/png') {
            throw new RenderingException('PNG metadata invalid.');
        }

        return new ImagePresentation('image/png', $bytes, $width, $height);
    }
}
