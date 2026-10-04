<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Unit\IconSequence;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\HumanGate\Exception\RenderingException;
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
            $target = new IconSequenceRenderer()->render($sequence);
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
                self::assertLessThan(6500, $ink);
            }
            self::assertSame($background, imagecolorat($decoded, 12, 0));
            foreach ($sequence as $image) {
                self::assertNotSame($image->bytes, $target->bytes);
            }
        }
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
