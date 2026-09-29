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

        foreach (['imagecreatetruecolor', 'imagecolorallocate', 'imagefill', 'imagesetpixel', 'imageline', 'imagearc', 'imagettfbbox', 'imagettftext', 'imagepng', 'getimagesizefromstring'] as $function) {
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

        $background = @imagecolorallocate($image, 213, 219, 227);
        $text = @imagecolorallocate($image, 49, 61, 79);
        $line = @imagecolorallocate($image, 143, 156, 174);
        $dot = @imagecolorallocate($image, 113, 130, 151);
        $foregroundNoise = @imagecolorallocate($image, 95, 114, 137);

        if (
            $background === false
            || $text === false
            || $line === false
            || $dot === false
            || $foregroundNoise === false
        ) {
            throw new RenderingException('Color allocation failed.');
        }

        if (!@imagefill($image, 0, 0, $background)) {
            throw new RenderingException('Background fill failed.');
        }

        for ($i = 0; $i < 3; ++$i) {
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

        for ($i = 0; $i < 4; ++$i) {
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

        for ($i = 0; $i < 248; ++$i) {
            $y = random_int(0, 9) === 0
                ? random_int(12, 68)
                : random_int(23, 58);

            if (!@imagesetpixel($image, random_int(12, 228), $y, $dot)) {
                throw new RenderingException('Dot noise rendering failed.');
            }
        }

        for ($i = 0; $i < 14; ++$i) {
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

        for ($i = 0; $i < 6; ++$i) {
            $x = 22 + (34 * $i) + random_int(-2, 2);
            $baselineY = random_int(51, 53);
            $angle = random_int(-16, 16);
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

                $absoluteX = $x + $bbox[$corner];
                $absoluteY = $baselineY + $bbox[$corner + 1];

                if (
                    $absoluteX - 1 < 8
                    || $absoluteX + 1 > 235
                    || $absoluteY - 1 < 12
                    || $absoluteY + 1 > 68
                ) {
                    throw new RenderingException('Text bounding box outside guard.');
                }
            }

            $offsetX = random_int(0, 1) === 0 ? -1 : 1;
            $offsetY = random_int(0, 1) === 0 ? -1 : 1;

            foreach ([[$offsetX, $offsetY, $line], [-$offsetX, -$offsetY, $dot], [0, 0, $text]] as [$dx, $dy, $color]) {
                if (
                    @imagettftext(
                        $image,
                        28,
                        $angle,
                        $x + $dx,
                        $baselineY + $dy,
                        $color,
                        $font,
                        $character,
                    ) === false
                ) {
                    throw new RenderingException('Text rendering failed.');
                }
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

        for ($i = 0; $i < 2; ++$i) {
            if (
                !@imagearc(
                    $image,
                    65 + (100 * $i) + random_int(-10, 10),
                    random_int(32, 49),
                    random_int(48, 76),
                    random_int(22, 34),
                    random_int(20, 100),
                    random_int(190, 280),
                    $foregroundNoise,
                )
            ) {
                throw new RenderingException('Foreground curved noise rendering failed.');
            }
        }

        for ($i = 0; $i < 4; ++$i) {
            $x = random_int(30, 200);
            $y = random_int(30, 48);

            if (
                !@imageline(
                    $image,
                    $x,
                    $y,
                    min(228, $x + random_int(5, 12)),
                    min(58, max(22, $y + random_int(-4, 4))),
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
