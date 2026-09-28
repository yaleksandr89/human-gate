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

        foreach (['imagecreatetruecolor', 'imagecolorallocate', 'imagefill', 'imagesetpixel', 'imageline', 'imagettfbbox', 'imagettftext', 'imagepng', 'getimagesizefromstring'] as $function) {
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

        $background = @imagecolorallocate($image, 247, 249, 252);
        $text = @imagecolorallocate($image, 31, 45, 67);
        $line = @imagecolorallocate($image, 194, 203, 215);
        $dot = @imagecolorallocate($image, 170, 184, 200);
        if ($background === false || $text === false || $line === false || $dot === false) {
            throw new RenderingException('Color allocation failed.');
        }
        if (!@imagefill($image, 0, 0, $background)) {
            throw new RenderingException('Background fill failed.');
        }

        for ($i = 0; $i < 2; ++$i) {
            if (!@imageline($image, random_int(4, 40), random_int(16, 64), random_int(199, 235), random_int(16, 64), $line)) {
                throw new RenderingException('Line noise rendering failed.');
            }
        }
        for ($i = 0; $i < 48; ++$i) {
            if (!@imagesetpixel($image, random_int(6, 233), random_int(8, 71), $dot)) {
                throw new RenderingException('Dot noise rendering failed.');
            }
        }

        for ($i = 0; $i < 6; ++$i) {
            $x = 18 + (35 * $i) + random_int(-1, 1);
            $baselineY = random_int(48, 55);
            $angle = random_int(-10, 10);
            $character = $canonicalAnswer[$i];
            $bbox = @imagettfbbox(28, $angle, $font, $character);
            if ($bbox === false) {
                throw new RenderingException('Text bounding box failed.');
            }
            foreach ([0, 2, 4, 6] as $corner) {
                if (!isset($bbox[$corner], $bbox[$corner + 1]) || !is_int($bbox[$corner]) || !is_int($bbox[$corner + 1])) {
                    throw new RenderingException('Text bounding box invalid.');
                }
                $absoluteX = $x + $bbox[$corner];
                $absoluteY = $baselineY + $bbox[$corner + 1];
                if ($absoluteX < 8 || $absoluteX > 235 || $absoluteY < 12 || $absoluteY > 68) {
                    throw new RenderingException('Text bounding box outside guard.');
                }
            }
            if (@imagettftext($image, 28, $angle, $x, $baselineY, $text, $font, $character) === false) {
                throw new RenderingException('Text rendering failed.');
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
