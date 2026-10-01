<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Unit\TextImage;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Throwable;
use Yaleksandr\HumanGate\Internal\TextImage\TextImageAnswer;
use Yaleksandr\HumanGate\Presentation\ImagePresentation;
use Yaleksandr\HumanGate\TextImage\GdTextImageRenderer;
use Yaleksandr\HumanGate\TextImage\TextImageBlur;
use Yaleksandr\HumanGate\TextImage\TextImageRenderOptions;

#[TestDox('GD-рендерер текстового изображения соблюдает настраиваемый профиль')]
final class GdTextImageRendererTest extends TestCase
{
    #[TestDox('Шрифт и лицензия имеют проверенное происхождение')]
    public function testFontProvenance(): void
    {
        $directory = dirname(__DIR__, 3) . '/resources/fonts';
        $font = $directory . '/NotoSans-Regular.ttf';
        $license = $directory . '/OFL.txt';
        $source = $directory . '/SOURCE.txt';

        self::assertFileExists($font);
        self::assertIsReadable($font);
        self::assertSame(621572, filesize($font));
        self::assertSame('478c558ea716033cd60c03438f628dfa75694dcf6b5f6d505a2f05fd2b4f3823', hash_file('sha256', $font));
        self::assertFileExists($license);
        self::assertIsReadable($license);
        self::assertSame('cee9892f9f0cc8fe882c9e9537ee6a89621d86ee7ceaf70b02e2b2b1c25c061a', hash_file('sha256', $license));
        self::assertFileExists($source);
        self::assertSame(<<<'SOURCE'
            Font: Noto Sans Regular
            License: OFL-1.1
            Upstream: https://github.com/notofonts/latin-greek-cyrillic
            Release: NotoSans-v2.015
            Commit: c4a321e123e4d4ff315f57f4e0adf294fe3a95be
            Archive: https://github.com/notofonts/latin-greek-cyrillic/releases/download/NotoSans-v2.015/NotoSans-v2.015.zip
            Archive size: 117491253 bytes
            Archive SHA-256: 0c34df072a3fa7efbb7cbf34950e1f971a4447cffe365d3a359e2d4089b958f5
            Archive member: NotoSans/hinted/ttf/NotoSans-Regular.ttf
            Font size: 621572 bytes
            Font SHA-256: 478c558ea716033cd60c03438f628dfa75694dcf6b5f6d505a2f05fd2b4f3823
            OFL SHA-256: cee9892f9f0cc8fe882c9e9537ee6a89621d86ee7ceaf70b02e2b2b1c25c061a
            SOURCE . "\n", file_get_contents($source));
    }

    #[TestDox('Канонические ответы дают ограниченный PNG размером 240 на 80')]
    public function testCanonicalAnswersRenderAsPng(): void
    {
        $renderer = new GdTextImageRenderer();
        foreach (['234567', 'ACDEFG', 'HJKMNP', 'QRTUVW', 'XY2345', 'adefhm', 'nrtA29'] as $answer) {
            $this->assertPng($renderer->render($answer));
        }
        foreach (str_split(TextImageAnswer::RENDERABLE_ALPHABET) as $character) {
            $this->assertPng($renderer->render(str_repeat($character, 6)));
        }
    }

    #[TestDox('Все масштабы и пресеты размытия дают ограниченный PNG ожидаемого размера')]
    public function testSupportedScalesAndBlurPresets(): void
    {
        foreach ([100, 105, 110, 115, 120] as $scale) {
            foreach (TextImageBlur::cases() as $blur) {
                $renderer = new GdTextImageRenderer(new TextImageRenderOptions(scalePercent: $scale, blur: $blur));
                $this->assertPng($renderer->render('WMQXY2'), (int) (240 * $scale / 100), (int) (80 * $scale / 100));
            }
        }
    }

    #[TestDox('Весь канонический алфавит помещается на границах шума и поворота при каждом масштабе')]
    public function testCanonicalAlphabetAtSupportedBoundaries(): void
    {
        foreach ([100, 105, 110, 115, 120] as $scale) {
            foreach ([0, 28] as $rotation) {
                foreach ([0, 150] as $noise) {
                    $renderer = new GdTextImageRenderer(new TextImageRenderOptions(
                        scalePercent: $scale,
                        blur: TextImageBlur::Strong,
                        lightNoisePercent: $noise,
                        maxRotationDegrees: $rotation,
                    ));
                    foreach (str_split(TextImageAnswer::RENDERABLE_ALPHABET) as $character) {
                        $this->assertPng($renderer->render(str_repeat($character, 6)), (int) (240 * $scale / 100), (int) (80 * $scale / 100));
                    }
                }
            }
        }
    }

    #[TestDox('Все глифы при каждом допустимом угле имеют безопасный интервал базовой линии и шестисимвольное размещение')]
    public function testDeterministicCanonicalGlyphGeometry(): void
    {
        $font = dirname(__DIR__, 3) . '/resources/fonts/NotoSans-Regular.ttf';
        foreach ([100, 105, 110, 115, 120] as $scale) {
            $scaled = static fn(int $coordinate): int => (int) round($coordinate * $scale / 100);
            $envelopes = [];
            $mostNegativeBearing = 0;
            foreach (str_split(TextImageAnswer::RENDERABLE_ALPHABET) as $character) {
                // Every smaller public rotation limit selects a subset of these integer angles.
                foreach (range(-28, 28) as $angle) {
                    $context = "scale=$scale glyph=$character angle=$angle";
                    $bbox = imagettfbbox(28 * $scale / 100, $angle, $font, $character);
                    self::assertIsArray($bbox, $context);
                    self::assertCount(8, $bbox, $context);
                    self::assertContainsOnlyInt($bbox, $context);
                    $minX = min($bbox[0], $bbox[2], $bbox[4], $bbox[6]);
                    $maxX = max($bbox[0], $bbox[2], $bbox[4], $bbox[6]);
                    $minY = min($bbox[1], $bbox[3], $bbox[5], $bbox[7]);
                    $maxY = max($bbox[1], $bbox[3], $bbox[5], $bbox[7]);
                    $baselineMin = max($scaled(41), $scaled(10) - $minY);
                    $baselineMax = min($scaled(62), $scaled(70) - $maxY);

                    self::assertGreaterThan($baselineMin, $baselineMax, $context);
                    self::assertGreaterThanOrEqual($scaled(8), $baselineMin + $minY, $context);
                    self::assertLessThanOrEqual($scaled(72), $baselineMax + $maxY, $context);
                    $correctedMinX = max($scaled(18), $scaled(6) - $minX);
                    self::assertGreaterThanOrEqual($scaled(6), $correctedMinX + $minX, $context);

                    $mostNegativeBearing = min($mostNegativeBearing, $minX);
                    $envelopes[] = [$maxX, $context];
                }
            }

            // This bound covers any first glyph and every later left-bearing correction:
            // advances are positive, and no correction can exceed the same global threshold.
            $worstInitialX = max($scaled(22), $scaled(6) - $mostNegativeBearing);
            $worstSixthX = $worstInitialX + 5 * $scaled(31);
            foreach ($envelopes as [$maxX, $context]) {
                self::assertLessThanOrEqual($scaled(234), $worstSixthX + $maxX, $context);
            }
        }
    }

    #[TestDox('Масштабирование всех случайных координат сохраняет непустые диапазоны с различными границами')]
    public function testScaledGeometryRandomRangesDoNotCollapse(): void
    {
        // Canonical coordinate ranges used for glyph placement, noise bands, lines, arcs and marks.
        $ranges = [
            [18, 22], [25, 31], [8, 71], [19, 62], [6, 233], [4, 40],
            [16, 64], [199, 235], [48, 84], [20, 36], [50, 190], [30, 53],
            [12, 68], [23, 58], [12, 228], [22, 210], [26, 56], [3, 10],
            [-3, 3], [12, 40], [30, 48], [200, 228], [32, 50], [-10, 10],
            [30, 51], [48, 78], [22, 36], [30, 200], [29, 50], [5, 14], [-5, 5],
        ];
        foreach ([100, 105, 110, 115, 120] as $scale) {
            foreach ($ranges as [$min, $max]) {
                $scaledMin = (int) round($min * $scale / 100);
                $scaledMax = (int) round($max * $scale / 100);
                self::assertGreaterThan($scaledMin, $scaledMax, "scale=$scale range=$min..$max");
            }
        }
    }

    #[TestDox('Неканонический ввод отклоняется без раскрытия ответа')]
    public function testInvalidDirectInput(): void
    {
        $renderer = new GdTextImageRenderer();
        $invalidAnswers = ['23456', '2345678', ' 23456', '23456 ', "23\t567", 'IIIIII', 'BBBBBB', 'А23456', "\xFF23456", "\0" . '23456'];
        foreach (str_split('gcsvxzpubqilojkwy') as $character) {
            $invalidAnswers[] = str_repeat($character, 6);
        }
        foreach ($invalidAnswers as $answer) {
            try {
                $renderer->render($answer);
                self::fail('Noncanonical answer was accepted.');
            } catch (Throwable $exception) {
                self::assertInstanceOf(InvalidArgumentException::class, $exception);
                self::assertStringNotContainsString($answer, $exception->getMessage());
            }
        }
    }

    #[TestDox('Буфер вызывающей стороны сохраняется без вывода PNG')]
    public function testCallerOutputBufferIsPreserved(): void
    {
        $originalLevel = ob_get_level();
        self::assertTrue(ob_start());
        $callerLevel = ob_get_level();
        try {
            echo 'caller content';
            $presentation = new GdTextImageRenderer(new TextImageRenderOptions(
                scalePercent: 120,
                blur: TextImageBlur::Strong,
                lightNoisePercent: 150,
                maxRotationDegrees: 28,
            ))->render('234567');
            self::assertSame($originalLevel + 1, $callerLevel);
            self::assertSame($callerLevel, ob_get_level());
            self::assertSame('caller content', ob_get_contents());
            self::assertNotEmpty($presentation->bytes);
        } finally {
            if (ob_get_level() === $callerLevel) {
                ob_end_clean();
            }
        }
    }

    #[TestDox('Отсутствие FreeType приводит к закрытому отказу')]
    public function testMissingFreeTypeCapabilityFailsClosed(): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('Subprocesses are unavailable in this PHP environment.');
        }

        $autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
        $script = 'require ' . var_export($autoload, true) . '; '
            . 'try { (new \\Yaleksandr\\HumanGate\\TextImage\\GdTextImageRenderer())->render("234567"); exit(2); } '
            . 'catch (\\Yaleksandr\\HumanGate\\Exception\\RenderingException $e) { echo $e->getMessage(); exit(0); }';
        $process = proc_open(
            [PHP_BINARY, '-d', 'disable_functions=imagettfbbox', '-r', $script],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        if (!is_resource($process)) {
            self::markTestSkipped('The PHP subprocess could not be started.');
        }
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), (string) $errors);
        self::assertSame('Required GD or FreeType capability unavailable.', $output);
    }

    #[TestDox('Рендерер не использует файлы для PNG и устаревшее освобождение GD')]
    public function testRendererSourceUsesMemoryAndNoDeprecatedDestroy(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/src/TextImage/GdTextImageRenderer.php');
        self::assertIsString($source);
        self::assertStringContainsString('imagepng($image, null, 6)', $source);
        self::assertStringNotContainsString('imagedestroy(', $source);
    }

    private function assertPng(ImagePresentation $presentation, int $width = 240, int $height = 80): void
    {
        self::assertSame('image/png', $presentation->mimeType);
        self::assertSame($width, $presentation->width);
        self::assertSame($height, $presentation->height);
        self::assertNotSame('', $presentation->bytes);
        self::assertLessThanOrEqual(131072, strlen($presentation->bytes));
        self::assertStringStartsWith(
            "\x89PNG\r\n\x1a\n",
            $presentation->bytes,
        );
        $metadata = getimagesizefromstring($presentation->bytes);
        self::assertIsArray($metadata);
        self::assertSame($width, $metadata[0]);
        self::assertSame($height, $metadata[1]);
        self::assertSame(IMAGETYPE_PNG, $metadata[2]);
        self::assertSame('image/png', $metadata['mime']);
    }
}
