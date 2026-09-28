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

#[TestDox('GD-рендерер текстового изображения соблюдает фиксированный профиль')]
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
        foreach (['234567', 'ACDEFG', 'HJKMNP', 'QRTUVW', 'XY2345'] as $answer) {
            $this->assertPng($renderer->render($answer));
        }
        foreach (str_split(TextImageAnswer::ALPHABET) as $character) {
            $this->assertPng($renderer->render(str_repeat($character, 6)));
        }
    }

    #[TestDox('Неканонический ввод отклоняется без раскрытия ответа')]
    public function testInvalidDirectInput(): void
    {
        $renderer = new GdTextImageRenderer();
        foreach (['abcdef', '23456', '2345678', ' 23456', '23456 ', "23\t567", 'IIIIII', 'BBBBBB', 'А23456', "\xFF23456", "\0" . '23456'] as $answer) {
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
            $presentation = new GdTextImageRenderer()->render('234567');
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

    private function assertPng(ImagePresentation $presentation): void
    {
        self::assertSame('image/png', $presentation->mimeType);
        self::assertSame(240, $presentation->width);
        self::assertSame(80, $presentation->height);
        self::assertNotSame('', $presentation->bytes);
        self::assertLessThanOrEqual(131072, strlen($presentation->bytes));
        self::assertStringStartsWith(
            "\x89PNG\r\n\x1a\n",
            $presentation->bytes,
        );
        $metadata = getimagesizefromstring($presentation->bytes);
        self::assertIsArray($metadata);
        self::assertSame(240, $metadata[0]);
        self::assertSame(80, $metadata[1]);
        self::assertSame(IMAGETYPE_PNG, $metadata[2]);
        self::assertSame('image/png', $metadata['mime']);
    }
}
