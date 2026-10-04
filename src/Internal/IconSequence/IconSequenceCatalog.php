<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Internal\IconSequence;

use InvalidArgumentException;
use Yaleksandr\HumanGate\Exception\RenderingException;
use Yaleksandr\HumanGate\IconSequence\IconSequenceLanguage;
use Yaleksandr\HumanGate\Presentation\ImagePresentation;

final class IconSequenceCatalog
{
    private const int MAX_PNG_BYTES = 131_072;

    private const array LABELS = [
        'anchor' => ['ru' => 'Якорь', 'en' => 'Anchor'],
        'apple' => ['ru' => 'Яблоко', 'en' => 'Apple'],
        'bell' => ['ru' => 'Колокольчик', 'en' => 'Bell'],
        'camera' => ['ru' => 'Фотоаппарат', 'en' => 'Camera'],
        'car' => ['ru' => 'Автомобиль', 'en' => 'Car'],
        'fish' => ['ru' => 'Рыба', 'en' => 'Fish'],
        'gift' => ['ru' => 'Подарок', 'en' => 'Gift'],
        'home' => ['ru' => 'Дом', 'en' => 'Home'],
        'key' => ['ru' => 'Ключ', 'en' => 'Key'],
        'moon' => ['ru' => 'Луна', 'en' => 'Moon'],
        'plane' => ['ru' => 'Самолёт', 'en' => 'Plane'],
        'rocket' => ['ru' => 'Ракета', 'en' => 'Rocket'],
        'star' => ['ru' => 'Звезда', 'en' => 'Star'],
        'sun' => ['ru' => 'Солнце', 'en' => 'Sun'],
        'tree' => ['ru' => 'Дерево', 'en' => 'Tree'],
        'umbrella' => ['ru' => 'Зонт', 'en' => 'Umbrella'],
    ];

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::LABELS);
    }

    public static function label(string $name, IconSequenceLanguage $language): string
    {
        self::requireKnown($name);

        return self::LABELS[$name][$language->value];
    }

    public static function image(string $name): ImagePresentation
    {
        self::requireKnown($name);
        $path = dirname(__DIR__, 3) . '/resources/icons/tabler/' . $name . '.png';
        if (!is_file($path) || !is_readable($path)) {
            throw new RenderingException('Package icon unavailable.');
        }
        $bytes = @file_get_contents($path, false, null, 0, self::MAX_PNG_BYTES + 1);
        if ($bytes === false || $bytes === '' || strlen($bytes) > self::MAX_PNG_BYTES) {
            throw new RenderingException('Package icon read failed or size invalid.');
        }
        $image = new ImagePresentation('image/png', $bytes, 96, 96);
        IconSequenceRenderer::decode($image);

        return $image;
    }

    private static function requireKnown(string $name): void
    {
        if (!isset(self::LABELS[$name])) {
            throw new InvalidArgumentException('Unknown package icon.');
        }
    }
}
