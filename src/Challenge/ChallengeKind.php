<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Challenge;

enum ChallengeKind: string
{
    case TextImage = 'text_image';
    case CategorySelection = 'category_selection';
    case IconSequence = 'icon_sequence';
}
