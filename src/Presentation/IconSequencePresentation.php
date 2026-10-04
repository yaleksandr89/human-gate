<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Presentation;

use InvalidArgumentException;

final readonly class IconSequencePresentation implements Presentation
{
    /** @var list<IconSequenceChoice> */
    public array $choices;

    /**
     * EN: Validates a list of unique choices without exposing target membership or order as text.
     * RU: Проверяет список уникальных вариантов, не раскрывая текстом состав или порядок целевой последовательности.
     *
     * @param array<array-key, mixed> $choices
     */
    public function __construct(public ImagePresentation $target, public int $requiredSelections, array $choices)
    {
        if (
            $target->mimeType !== 'image/png'
            || $requiredSelections < 3
            || $requiredSelections > 6
            || !array_is_list($choices)
            || count($choices) < $requiredSelections + 2
            || count($choices) > 12
        ) {
            throw new InvalidArgumentException('Invalid icon sequence presentation.');
        }
        $tokens = [];
        $labels = [];
        $images = [];
        $validated = [];
        foreach ($choices as $choice) {
            if (!$choice instanceof IconSequenceChoice) {
                throw new InvalidArgumentException('Invalid icon sequence choice.');
            }
            if (
                in_array($choice->token, $tokens, true)
                || in_array($choice->label, $labels, true)
                || in_array($choice->image->bytes, $images, true)
            ) {
                throw new InvalidArgumentException('Duplicate icon sequence choice.');
            }
            $tokens[] = $choice->token;
            $labels[] = $choice->label;
            $images[] = $choice->image->bytes;
            $validated[] = $choice;
        }
        $this->choices = $validated;
    }
}
