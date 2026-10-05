<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Unit\Presentation;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\HumanGate\Internal\IconSequence\IconSequenceCatalog;
use Yaleksandr\HumanGate\Presentation\IconSequenceChoice;
use Yaleksandr\HumanGate\Presentation\IconSequencePresentation;
use Yaleksandr\HumanGate\Presentation\ImagePresentation;
use Yaleksandr\HumanGate\Presentation\Presentation;

#[TestDox('Представление раскрывает только PNG, число выборов и варианты с непрозрачными токенами')]
final class IconSequencePresentationTest extends TestCase
{
    public function testPublicSurfaceAndBoundaries(): void
    {
        $image = IconSequenceCatalog::image('anchor');
        foreach ([[3, 5], [6, 12]] as [$length, $count]) {
            $choices = self::choices($count);
            $presentation = new IconSequencePresentation($image, $length, $choices);
            self::assertInstanceOf(Presentation::class, $presentation);
            self::assertSame($choices, $presentation->choices);
            self::assertSame($length, $presentation->requiredSelections);
            self::assertSame($image, $presentation->target);
            self::assertEqualsCanonicalizing(['target', 'requiredSelections', 'choices'], array_keys(get_object_vars($presentation)));
            self::assertSame(['token', 'image'], array_keys(get_object_vars($choices[0])));
            self::assertSame(['mimeType', 'bytes', 'width', 'height'], array_keys(get_object_vars($image)));
        }
    }

    public function testInvalidChoiceFields(): void
    {
        $image = IconSequenceCatalog::image('anchor');
        foreach (['', str_repeat('a', 31), str_repeat('a', 33), str_repeat('A', 32), str_repeat('g', 32), str_repeat('а', 32), str_repeat('a', 32) . "\n"] as $token) {
            try {
                new IconSequenceChoice($token, $image);
                self::fail('Invalid token accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('token', $exception->getMessage());
            }
        }
        $this->expectException(InvalidArgumentException::class);
        new IconSequenceChoice(str_repeat('a', 32), new ImagePresentation('image/jpeg', 'bytes', 96, 96));
    }

    public function testInvalidPresentationCollections(): void
    {
        $choices = self::choices(5);
        $image = IconSequenceCatalog::image('anchor');
        foreach ([
            [], self::choices(4), self::choices(13), [1 => $choices[0], 2 => $choices[1], 3 => $choices[2], 4 => $choices[3], 5 => $choices[4]],
            [...array_slice($choices, 0, 4), 'invalid'],
            [...array_slice($choices, 0, 4), new IconSequenceChoice($choices[0]->token, $choices[4]->image)],
            [...array_slice($choices, 0, 4), new IconSequenceChoice($choices[4]->token, $choices[0]->image)],
        ] as $invalid) {
            try {
                new IconSequencePresentation($image, 3, $invalid);
                self::fail('Invalid presentation accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
        foreach ([2, 7] as $length) {
            try {
                new IconSequencePresentation($image, $length, $choices);
                self::fail('Invalid selection count accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
        $this->expectException(InvalidArgumentException::class);
        new IconSequencePresentation(new ImagePresentation('image/jpeg', 'bytes', 96, 96), 3, $choices);
    }

    /** @return list<IconSequenceChoice> */
    private static function choices(int $count): array
    {
        $names = array_slice(IconSequenceCatalog::names(), 0, $count);

        return array_map(
            static fn(string $name, int $index): IconSequenceChoice => new IconSequenceChoice(str_pad(dechex($index), 32, '0', STR_PAD_LEFT), IconSequenceCatalog::image($name)),
            $names,
            range(0, $count - 1),
        );
    }
}
