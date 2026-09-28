<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate;

use InvalidArgumentException;
use LogicException;
use Random\RandomException;
use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\IssuedChallenge;
use Yaleksandr\HumanGate\Challenge\IssueResult;
use Yaleksandr\HumanGate\Challenge\Policy;
use Yaleksandr\HumanGate\Challenge\PreparedChallenge;
use Yaleksandr\HumanGate\Challenge\Purpose;
use Yaleksandr\HumanGate\Challenge\VerificationCode;
use Yaleksandr\HumanGate\Exception\UnsupportedChallengeKindException;
use Yaleksandr\HumanGate\Internal\ChallengeLifecycle;
use Yaleksandr\HumanGate\Port\ChallengeStore;
use Yaleksandr\HumanGate\Port\ChallengeStrategy;
use Yaleksandr\HumanGate\Port\Clock;
use Yaleksandr\HumanGate\State\ChallengeBucket;
use Yaleksandr\HumanGate\State\LifecycleCode;
use Yaleksandr\HumanGate\State\LifecycleResult;

final class ChallengeService
{
    private ChallengeLifecycle $lifecycle;

    /** @var array<string, ChallengeStrategy> */
    private array $strategies = [];

    public function __construct(private ChallengeStore $store, Policy $policy, Clock $clock, ChallengeStrategy ...$strategies)
    {
        $this->lifecycle = new ChallengeLifecycle($policy, $clock);
        foreach ($strategies as $strategy) {
            $kind = $strategy->kind()->value;
            if (isset($this->strategies[$kind])) {
                throw new InvalidArgumentException('Duplicate challenge strategy.');
            }
            $this->strategies[$kind] = $strategy;
        }
    }

    /** @throws RandomException */
    public function issue(Purpose $purpose, ChallengeKind $kind = ChallengeKind::TextImage): IssueResult
    {
        $strategy = $this->strategy($kind);
        $id = ChallengeId::generate();
        $prepared = $strategy->prepare($id, $purpose);

        return $this->store->atomic(function (ChallengeBucket $bucket) use ($id, $purpose, $kind, $prepared): IssueResult {
            $result = $this->lifecycle->issue($bucket, $id, $purpose, $kind, $prepared->proof);

            return $this->issueResult($result, $prepared);
        });
    }

    /** @throws RandomException */
    public function replace(ChallengeId $oldId, Purpose $purpose, ChallengeKind $kind = ChallengeKind::TextImage): IssueResult
    {
        $strategy = $this->strategy($kind);
        $newId = ChallengeId::generate();
        $prepared = $strategy->prepare($newId, $purpose);

        return $this->store->atomic(function (ChallengeBucket $bucket) use ($oldId, $purpose, $newId, $kind, $prepared): IssueResult {
            $result = $this->lifecycle->replace($bucket, $oldId, $purpose, $newId, $kind, $prepared->proof);

            return $this->issueResult($result, $prepared);
        });
    }

    public function verify(ChallengeId $id, Purpose $purpose, string $submittedAnswer): VerificationCode
    {
        return $this->store->atomic(function (ChallengeBucket $bucket) use ($id, $purpose, $submittedAnswer): VerificationCode {
            $lookup = $this->lifecycle->lookup($bucket, $id, $purpose);
            if ($lookup->code !== LifecycleCode::Active) {
                return self::verificationCode($lookup->code);
            }
            $challenge = $lookup->challenge;
            if ($challenge === null) {
                throw new LogicException('Active challenge is missing.');
            }
            $strategy = $this->strategy($challenge->kind);
            $result = $strategy->verify($challenge, $submittedAnswer)
                ? $this->lifecycle->consume($bucket, $id, $purpose)
                : $this->lifecycle->registerWrongAttempt($bucket, $id, $purpose);

            return self::verificationCode($result->code);
        });
    }

    private function strategy(ChallengeKind $kind): ChallengeStrategy
    {
        return $this->strategies[$kind->value] ?? throw new UnsupportedChallengeKindException('Unsupported challenge kind: ' . $kind->value);
    }

    private function issueResult(LifecycleResult $result, PreparedChallenge $prepared): IssueResult
    {
        $challenge = $result->challenge;
        if ($challenge === null) {
            return new IssueResult($result->code, null);
        }

        return new IssueResult($result->code, new IssuedChallenge($challenge->id, $challenge->kind, $challenge->expiresAt, $prepared->presentation));
    }

    private static function verificationCode(LifecycleCode $code): VerificationCode
    {
        return match ($code) {
            LifecycleCode::Consumed => VerificationCode::Accepted,
            LifecycleCode::WrongAttempt => VerificationCode::Incorrect,
            LifecycleCode::AttemptsExhausted => VerificationCode::AttemptsExhausted,
            LifecycleCode::NotFound => VerificationCode::NotFound,
            LifecycleCode::PurposeMismatch => VerificationCode::PurposeMismatch,
            LifecycleCode::Expired => VerificationCode::Expired,
            LifecycleCode::AlreadyConsumed => VerificationCode::AlreadyConsumed,
            LifecycleCode::Replaced => VerificationCode::Replaced,
            default => throw new LogicException('Unexpected final verification state.'),
        };
    }
}
