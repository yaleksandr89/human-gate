<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Unit\IconSequence;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\HumanGate\Exception\RenderingException;
use Yaleksandr\HumanGate\IconSequence\IconSequenceBlur;
use Yaleksandr\HumanGate\IconSequence\IconSequenceRenderOptions;
use Yaleksandr\HumanGate\Internal\IconSequence\IconSequenceCatalog;
use Yaleksandr\HumanGate\Internal\IconSequence\IconSequenceRenderer;
use Yaleksandr\HumanGate\Presentation\ImagePresentation;

#[TestDox('Рендерер создаёт читаемую PNG-композицию и отклоняет повреждённые ресурсы')]
final class IconSequenceRendererTest extends TestCase
{
    public function testCompositeBoundsAndVisibleCells(): void
    {
        foreach ([3, 6] as $count) {
            $sequence = array_map(IconSequenceCatalog::image(...), array_slice(IconSequenceCatalog::names(), 0, $count));
            $target = new IconSequenceRenderer(new IconSequenceRenderOptions(
                minIconSize: 96,
                maxIconSize: 96,
                blur: IconSequenceBlur::None,
                noisePercent: 0,
                maxRotationDegrees: 0,
            ))->render($sequence);
            self::assertSame('image/png', $target->mimeType);
            self::assertSame($count * 112 + 24, $target->width);
            self::assertSame(128, $target->height);
            self::assertLessThanOrEqual(131072, strlen($target->bytes));
            $decoded = imagecreatefromstring($target->bytes);
            self::assertNotFalse($decoded);
            $background = imagecolorat($decoded, 0, 0);
            foreach (range(0, $count - 1) as $index) {
                $ink = 0;
                for ($x = 12 + $index * 112; $x < 12 + ($index + 1) * 112; ++$x) {
                    for ($y = 0; $y < 128; ++$y) {
                        if (imagecolorat($decoded, $x, $y) !== $background) {
                            ++$ink;
                        }
                    }
                }
                self::assertGreaterThan(150, $ink);
                self::assertLessThan(96 * 96, $ink);
            }
            self::assertSame($background, imagecolorat($decoded, 12, 0));
            foreach ($sequence as $image) {
                self::assertNotSame($image->bytes, $target->bytes);
            }
        }
    }

    #[TestDox('Все пресеты и граничные профили сохраняют размеры и лимит PNG')]
    public function testRenderProfiles(): void
    {
        $profiles = array_map(
            static fn(IconSequenceBlur $blur): IconSequenceRenderOptions => new IconSequenceRenderOptions(blur: $blur),
            IconSequenceBlur::cases(),
        );
        $profiles[] = new IconSequenceRenderOptions(96, 96, IconSequenceBlur::None, 0, 0);
        $profiles[] = new IconSequenceRenderOptions(72, 96, IconSequenceBlur::Strong, 150, 15);
        $profiles[] = new IconSequenceRenderOptions(72, 92, IconSequenceBlur::Strong, 150, 30);
        $profiles[] = new IconSequenceRenderOptions(88, 96, IconSequenceBlur::Strong, 150, 25);
        $profiles[] = new IconSequenceRenderOptions(80, 88, IconSequenceBlur::Strong, 150, 45);
        $profiles[] = new IconSequenceRenderOptions(76, 84, IconSequenceBlur::Strong, 150, 60);
        $profiles[] = new IconSequenceRenderOptions(72, 80, IconSequenceBlur::Strong, 150, 90);
        $profiles[] = new IconSequenceRenderOptions(90, 90, IconSequenceBlur::Strong, 150, 90);
        $profiles[] = new IconSequenceRenderOptions(noisePercent: 0);
        $profiles[] = new IconSequenceRenderOptions(maxRotationDegrees: 0);
        foreach ($profiles as $options) {
            foreach ([3, 4, 6] as $count) {
                $sequence = array_map(IconSequenceCatalog::image(...), array_slice(IconSequenceCatalog::names(), 0, $count));
                $target = new IconSequenceRenderer($options)->render($sequence);
                self::assertSame('image/png', $target->mimeType);
                self::assertSame($count * 112 + 24, $target->width);
                self::assertSame(128, $target->height);
                self::assertLessThanOrEqual(131072, strlen($target->bytes));
                $metadata = getimagesizefromstring($target->bytes);
                self::assertIsArray($metadata);
                self::assertSame($target->width, $metadata[0]);
                self::assertSame($target->height, $metadata[1]);
                self::assertSame(IMAGETYPE_PNG, $metadata[2]);
                self::assertNotFalse(imagecreatefromstring($target->bytes));
            }
        }
    }

    #[TestDox('Все целые углы и крайние адаптивные смещения помещают источник в цель без повторного масштабирования')]
    public function testAdaptivePlacementAtRotationAndJitterExtremes(): void
    {
        $script = <<<'PHP'
            namespace Yaleksandr\HumanGate\Internal\IconSequence;

            function random_int(int $min, int $max): int {
                $GLOBALS['ranges'][] = [$min, $max];
                if (count($GLOBALS['ranges']) === 2) {
                    return $GLOBALS['angle'];
                }
                return $GLOBALS['upper'] ? $max : $min;
            }

            function imagecopy(
                \GdImage $destination, \GdImage $source,
                int $x, int $y, int $sourceX, int $sourceY, int $width, int $height,
            ): bool {
                $centerY = intdiv(128 - imagesy($source), 2);
                $jitter = min(4, $centerY);
                \PHPUnit\Framework\TestCase::assertSame([
                    [$GLOBALS['size'], $GLOBALS['size']],
                    [-$GLOBALS['rotation'], $GLOBALS['rotation']],
                    [-$jitter, $jitter],
                ], $GLOBALS['ranges']);
                $GLOBALS['ranges'] = [];
                $GLOBALS['jitters'][$jitter] = true;
                \PHPUnit\Framework\TestCase::assertSame($centerY + ($GLOBALS['upper'] ? $jitter : -$jitter), $y);
                \PHPUnit\Framework\TestCase::assertGreaterThanOrEqual(0, $x);
                \PHPUnit\Framework\TestCase::assertGreaterThanOrEqual(0, $y);
                \PHPUnit\Framework\TestCase::assertLessThanOrEqual(imagesx($destination), $x + $width);
                \PHPUnit\Framework\TestCase::assertLessThanOrEqual(imagesy($destination), $y + $height);
                \PHPUnit\Framework\TestCase::assertSame([0, 0, imagesx($source), imagesy($source)], [
                    $sourceX, $sourceY, $width, $height,
                ]);
                return \imagecopy($destination, $source, $x, $y, $sourceX, $sourceY, $width, $height);
            }

            PHP;
        $script .= 'require ' . var_export(dirname(__DIR__, 3) . '/vendor/autoload.php', true) . '; '
            . <<<'PHP'
                $jitters = [];
                foreach ([[72, 7], [92, 25], [92, 30], [96, 25], [88, 45], [84, 60], [80, 90], [90, 90]] as [$size, $rotation]) {
                    $options = new \Yaleksandr\HumanGate\IconSequence\IconSequenceRenderOptions(
                        $size, $size, \Yaleksandr\HumanGate\IconSequence\IconSequenceBlur::None, 0, $rotation,
                    );
                    foreach (range(-$rotation, $rotation) as $angle) {
                        foreach ([false, true] as $upper) {
                            foreach ([3, 6] as $count) {
                                $ranges = [];
                                $sequence = array_map(IconSequenceCatalog::image(...), array_slice(IconSequenceCatalog::names(), 0, $count));
                                new IconSequenceRenderer($options)->render($sequence);
                                \PHPUnit\Framework\TestCase::assertSame([], $ranges);
                            }
                        }
                    }
                }
                $limits = array_keys($jitters);
                sort($limits);
                echo json_encode($limits, JSON_THROW_ON_ERROR);
                PHP;
        $process = proc_open([PHP_BINARY, '-r', $script], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), (string) $errors);
        self::assertSame('[0,1,2,3,4]', $output);
    }

    #[TestDox('Все 32 значка помещаются в профиль по умолчанию на крайних размерах, углах и смещениях')]
    public function testEntireCatalogFitsDefaultProfile(): void
    {
        $script = <<<'PHP'
            namespace Yaleksandr\HumanGate\Internal\IconSequence;

            function random_int(int $min, int $max): int {
                if ($min === 72 && $max === 80) {
                    return $GLOBALS['size'];
                }
                if ($min === -90 && $max === 90) {
                    return $GLOBALS['angle'];
                }
                return $GLOBALS['upper'] ? $max : $min;
            }

            function imagecopy(
                \GdImage $destination, \GdImage $source,
                int $x, int $y, int $sourceX, int $sourceY, int $width, int $height,
            ): bool {
                \PHPUnit\Framework\TestCase::assertGreaterThanOrEqual(0, $x);
                \PHPUnit\Framework\TestCase::assertGreaterThanOrEqual(0, $y);
                \PHPUnit\Framework\TestCase::assertLessThanOrEqual(imagesx($destination), $x + $width);
                \PHPUnit\Framework\TestCase::assertLessThanOrEqual(imagesy($destination), $y + $height);
                \PHPUnit\Framework\TestCase::assertSame([0, 0, imagesx($source), imagesy($source)], [
                    $sourceX, $sourceY, $width, $height,
                ]);
                ++$GLOBALS['copies'];
                return \imagecopy($destination, $source, $x, $y, $sourceX, $sourceY, $width, $height);
            }

            PHP;
        $script .= 'require ' . var_export(dirname(__DIR__, 3) . '/vendor/autoload.php', true) . '; '
            . <<<'PHP'
                $copies = 0;
                $renderer = new IconSequenceRenderer(new \Yaleksandr\HumanGate\IconSequence\IconSequenceRenderOptions());
                foreach (array_chunk(IconSequenceCatalog::names(), 4) as $names) {
                    $sequence = array_map(IconSequenceCatalog::image(...), $names);
                    foreach ([72, 80] as $size) {
                        foreach ([-90, -45, 0, 45, 90] as $angle) {
                            foreach ([false, true] as $upper) {
                                $target = $renderer->render($sequence);
                                \PHPUnit\Framework\TestCase::assertSame([472, 128], [$target->width, $target->height]);
                                \PHPUnit\Framework\TestCase::assertLessThanOrEqual(131072, strlen($target->bytes));
                                \PHPUnit\Framework\TestCase::assertNotFalse(imagecreatefromstring($target->bytes));
                            }
                        }
                    }
                }
                echo $copies;
                PHP;
        $process = proc_open([PHP_BINARY, '-r', $script], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), (string) $errors);
        self::assertSame('640', $output);
    }

    #[TestDox('Пресеты применяют 0, 1, 3 и 7 проходов неизменного гауссова ядра')]
    public function testGaussianBlurPassMapping(): void
    {
        $script = <<<'PHP'
            namespace Yaleksandr\HumanGate\Internal\IconSequence;

            function imageconvolution(\GdImage $image, array $matrix, float $divisor, float $offset): bool {
                \PHPUnit\Framework\TestCase::assertSame([
                    [1.0, 2.0, 1.0],
                    [2.0, 4.0, 2.0],
                    [1.0, 2.0, 1.0],
                ], $matrix);
                \PHPUnit\Framework\TestCase::assertSame(16.0, $divisor);
                \PHPUnit\Framework\TestCase::assertSame(0.0, $offset);
                ++$GLOBALS['passes'];
                return \imageconvolution($image, $matrix, $divisor, $offset);
            }

            PHP;
        $script .= 'require ' . var_export(dirname(__DIR__, 3) . '/vendor/autoload.php', true) . '; '
            . <<<'PHP'
                $counts = [];
                $sequence = array_map(IconSequenceCatalog::image(...), ['fish', 'anchor', 'car', 'plane']);
                foreach (\Yaleksandr\HumanGate\IconSequence\IconSequenceBlur::cases() as $blur) {
                    $passes = 0;
                    $options = new \Yaleksandr\HumanGate\IconSequence\IconSequenceRenderOptions(
                        blur: $blur, noisePercent: 0, maxRotationDegrees: 0,
                    );
                    new IconSequenceRenderer($options)->render($sequence);
                    $counts[$blur->name] = $passes;
                }
                echo json_encode($counts, JSON_THROW_ON_ERROR);
                PHP;
        $process = proc_open([PHP_BINARY, '-r', $script], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), (string) $errors);
        self::assertSame('{"None":0,"Light":1,"Standard":3,"Strong":7}', $output);
    }

    public function testInvalidSequences(): void
    {
        $images = array_map(IconSequenceCatalog::image(...), array_slice(IconSequenceCatalog::names(), 0, 7));
        foreach ([[], array_slice($images, 0, 2), $images, [1 => $images[0], 2 => $images[1], 3 => $images[2]], [$images[0], $images[0], $images[1]]] as $invalid) {
            try {
                new IconSequenceRenderer()->render($invalid);
                self::fail('Invalid sequence accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function testCorruptPngAndMetadataFailExplicitly(): void
    {
        $valid = IconSequenceCatalog::image('anchor');
        foreach ([
            new ImagePresentation('image/png', 'broken', 96, 96),
            new ImagePresentation('image/jpeg', $valid->bytes, 96, 96),
            new ImagePresentation('image/png', $valid->bytes, 95, 96),
            new ImagePresentation('image/png', substr($valid->bytes, 0, 33), 96, 96),
            new ImagePresentation('image/png', "\x89PNG\r\n\x1a\n" . str_repeat('x', 24), 96, 96),
        ] as $invalid) {
            try {
                new IconSequenceRenderer()->render([$valid, IconSequenceCatalog::image('apple'), $invalid]);
                self::fail('Corrupt image accepted.');
            } catch (RenderingException $exception) {
                self::assertStringContainsString('PNG', $exception->getMessage());
            }
        }
    }
}
