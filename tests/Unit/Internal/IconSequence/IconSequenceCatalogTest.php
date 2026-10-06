<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Unit\Internal\IconSequence;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\HumanGate\Internal\IconSequence\IconSequenceCatalog;

#[TestDox('Каталог содержит ровно 32 лицензированных PNG с уникальными изображениями')]
final class IconSequenceCatalogTest extends TestCase
{
    public function testCatalogAssetsAndProvenance(): void
    {
        $names = [
            'anchor',
            'apple',
            'ball-football',
            'bell',
            'bike',
            'bulb',
            'camera',
            'car',
            'clock',
            'coffee',
            'crown',
            'diamond',
            'eyeglass',
            'fish',
            'gift',
            'heart',
            'home',
            'key',
            'leaf',
            'moon',
            'music',
            'paw',
            'phone',
            'plane',
            'rocket',
            'scissors',
            'ship',
            'snowflake',
            'star',
            'sun',
            'tree',
            'umbrella',
        ];
        self::assertSame($names, IconSequenceCatalog::names());
        $directory = dirname(__DIR__, 4) . '/resources/icons/tabler/';
        $source = file_get_contents($directory . 'SOURCE.txt');
        self::assertIsString($source);
        $metadata = json_decode($source, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($metadata);
        self::assertSame('https://github.com/tabler/tabler-icons', $metadata['upstream']);
        self::assertSame('v3.48.0', $metadata['tag']);
        self::assertSame('MIT', $metadata['license']);
        $license = file_get_contents($directory . 'LICENSE.txt');
        self::assertIsString($license);
        self::assertStringContainsString('Copyright (c) 2020-2026 Paweł Kuna', $license);
        self::assertStringContainsString('Permission is hereby granted', $license);
        self::assertSame(hash('sha256', $license), $metadata['license_sha256']);
        self::assertIsString($metadata['tool']);
        self::assertStringContainsString('ImageMagick 6.9.12-98', $metadata['tool']);
        self::assertIsString($metadata['command']);
        self::assertStringContainsString('MSVG:', $metadata['command']);
        self::assertIsArray($metadata['assets']);
        self::assertCount(32, $metadata['assets']);
        $hashes = [];
        foreach ($names as $index => $name) {
            $image = IconSequenceCatalog::image($name);
            self::assertTrue(is_readable($directory . $name . '.png'));
            $bytes = file_get_contents($directory . $name . '.png');
            self::assertIsString($bytes);
            self::assertSame($bytes, $image->bytes);
            self::assertLessThanOrEqual(131072, strlen($image->bytes));
            $info = getimagesizefromstring($image->bytes);
            self::assertIsArray($info);
            self::assertSame([96, 96, IMAGETYPE_PNG], array_slice($info, 0, 3));
            self::assertSame('image/png', $image->mimeType);
            self::assertSame(96, $image->width);
            self::assertSame(96, $image->height);
            $decoded = imagecreatefromstring($image->bytes);
            self::assertNotFalse($decoded);
            foreach ([[0, 0], [95, 0], [0, 95], [95, 95]] as [$x, $y]) {
                self::assertSame(127, (imagecolorat($decoded, $x, $y) >> 24) & 127);
            }
            $hash = hash('sha256', $image->bytes);
            self::assertNotContains($hash, $hashes);
            $hashes[] = $hash;
            $record = $metadata['assets'][$index];
            self::assertIsArray($record);
            self::assertSame('icons/outline/' . $name . '.svg', $record['source']);
            self::assertSame($name . '.png', $record['png']);
            self::assertSame($hash, $record['png_sha256']);
            self::assertSame(96, $record['width']);
            self::assertSame(96, $record['height']);
            self::assertIsString($record['source_sha256']);
            self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $record['source_sha256']);
        }
    }

    public function testUnknownNamesCannotBecomePaths(): void
    {
        foreach (['../anchor', '/tmp/anchor', 'anchor.png', 'ANCHOR', ''] as $name) {
            try {
                IconSequenceCatalog::image($name);
                self::fail('Unknown name accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Unknown package icon.', $exception->getMessage());
            }
        }
    }
}
