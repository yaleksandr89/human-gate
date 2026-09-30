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
 * EN: Renders the fixed TextImage profile using the package-owned Noto Sans font.
 * RU: Создаёт изображение по фиксированному профилю TextImage со шрифтом Noto Sans из пакета.
 */
final class GdTextImageRenderer implements TextImageRenderer
{
    private const int WIDTH = 240;
    private const int HEIGHT = 80;
    private const int MAX_PNG_BYTES = 131072;
    private const string PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    /**
     * EN: Renders only a canonical six-character answer; random-source failures propagate.
     * RU: Отрисовывает только канонический ответ из шести символов; ошибки источника случайности проходят без замены.
     *
     * @throws RandomException
     */
    public function render(string $canonicalAnswer): ImagePresentation
    {
        if (strlen($canonicalAnswer) !== 6 || strspn($canonicalAnswer, TextImageAnswer::ALPHABET) !== 6) {
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

        $image = @imagecreatetruecolor(self::WIDTH, self::HEIGHT);
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

        $textLayer = @imagecreatetruecolor(self::WIDTH, self::HEIGHT);
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

        $x = random_int(18, 22);
        for ($i = 0; $i < 6; ++$i) {
            $angle = random_int(-28, 28);
            $character = $canonicalAnswer[$i];

            $bbox = @imagettfbbox(28, $angle, $font, $character);
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

            $baselineMin = max(41, 10 - $minY);
            $baselineMax = min(62, 70 - $maxY);
            $baselineRange = $baselineMax - $baselineMin;
            if ($baselineRange < 0) {
                throw new RenderingException('Text baseline outside guard.');
            }
            $baselineY = $baselineMin + random_int(0, $baselineRange);

            if (
                $x + $minX < 6
                || $x + $maxX > 234
                || $baselineY + $minY < 8
                || $baselineY + $maxY > 72
            ) {
                throw new RenderingException('Text bounding box outside guard.');
            }

            if (
                @imagettftext(
                    $textLayer,
                    28,
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

            $x += random_int(25, 31);
        }

        $gaussian = [
            [1.0, 2.0, 1.0],
            [2.0, 4.0, 2.0],
            [1.0, 2.0, 1.0],
        ];
        for ($i = 0; $i < 3; ++$i) {
            if (!@imageconvolution($textLayer, $gaussian, 16.0, 0.0)) {
                throw new RenderingException('Text blur failed.');
            }
        }

        if (!@imagecopy($image, $textLayer, 0, 0, 0, 0, self::WIDTH, self::HEIGHT)) {
            throw new RenderingException('Text layer compositing failed.');
        }

        for ($i = 0; $i < 190; ++$i) {
            $y = random_int(0, 4) === 0
                ? random_int(8, 71)
                : random_int(19, 62);

            if (!@imagesetpixel($image, random_int(6, 233), $y, $lightNoise)) {
                throw new RenderingException('Light noise rendering failed.');
            }
        }

        for ($i = 0; $i < 4; ++$i) {
            if (
                !@imageline(
                    $image,
                    random_int(4, 40),
                    random_int(16, 64),
                    random_int(199, 235),
                    random_int(16, 64),
                    $line,
                )
            ) {
                throw new RenderingException('Line noise rendering failed.');
            }
        }

        for ($i = 0; $i < 5; ++$i) {
            $arcWidth = random_int(48, 84);
            $arcHeight = random_int(20, 36);

            if (
                !@imagearc(
                    $image,
                    random_int(50, 190),
                    random_int(30, 53),
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
                ? random_int(12, 68)
                : random_int(23, 58);

            if (!@imagesetpixel($image, random_int(12, 228), $y, $dot)) {
                throw new RenderingException('Dot noise rendering failed.');
            }
        }

        for ($i = 0; $i < 20; ++$i) {
            $x = random_int(22, 210);
            $y = random_int(26, 56);

            if (
                !@imageline(
                    $image,
                    $x,
                    $y,
                    min(228, $x + random_int(3, 10)),
                    min(64, max(16, $y + random_int(-3, 3))),
                    $dot,
                )
            ) {
                throw new RenderingException('Short noise rendering failed.');
            }
        }

        if (
            !@imageline(
                $image,
                random_int(12, 40),
                random_int(30, 48),
                random_int(200, 228),
                random_int(32, 50),
                $foregroundNoise,
            )
        ) {
            throw new RenderingException('Foreground line noise rendering failed.');
        }

        for ($i = 0; $i < 3; ++$i) {
            if (
                !@imagearc(
                    $image,
                    50 + (70 * $i) + random_int(-10, 10),
                    random_int(30, 51),
                    random_int(48, 78),
                    random_int(22, 36),
                    random_int(20, 100),
                    random_int(190, 280),
                    $foregroundNoise,
                )
            ) {
                throw new RenderingException('Foreground curved noise rendering failed.');
            }
        }

        for ($i = 0; $i < 6; ++$i) {
            $x = random_int(30, 200);
            $y = random_int(29, 50);

            if (
                !@imageline(
                    $image,
                    $x,
                    $y,
                    min(228, $x + random_int(5, 14)),
                    min(60, max(20, $y + random_int(-5, 5))),
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
        if ($metadata === false || $metadata[0] !== self::WIDTH || $metadata[1] !== self::HEIGHT || $metadata[2] !== IMAGETYPE_PNG || $metadata['mime'] !== 'image/png') {
            throw new RenderingException('PNG metadata invalid.');
        }

        return new ImagePresentation('image/png', $bytes, self::WIDTH, self::HEIGHT);
    }
}
