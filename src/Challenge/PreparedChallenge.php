<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Challenge;

use Yaleksandr\HumanGate\Presentation\Presentation;
use Yaleksandr\HumanGate\State\AnswerProof;

final readonly class PreparedChallenge
{
    public function __construct(public AnswerProof $proof, public Presentation $presentation) {}
}
