<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Tests\Unit;

use Closure;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yaleksandr\HumanGate\CategorySelection\CategorySelectionCategory;
use Yaleksandr\HumanGate\CategorySelection\CategorySelectionOptions;
use Yaleksandr\HumanGate\CategorySelection\CategorySelectionStrategy;
use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\Policy;
use Yaleksandr\HumanGate\Challenge\PreparedChallenge;
use Yaleksandr\HumanGate\Challenge\Purpose;
use Yaleksandr\HumanGate\Challenge\VerificationCode;
use Yaleksandr\HumanGate\ChallengeService;
use Yaleksandr\HumanGate\Exception\UnsupportedChallengeKindException;
use Yaleksandr\HumanGate\Port\ChallengeStore;
use Yaleksandr\HumanGate\Port\ChallengeStrategy;
use Yaleksandr\HumanGate\Port\Clock;
use Yaleksandr\HumanGate\Port\TextImageRenderer;
use Yaleksandr\HumanGate\Presentation\CategorySelectionPresentation;
use Yaleksandr\HumanGate\Presentation\ImagePresentation;
use Yaleksandr\HumanGate\State\ActiveChallenge;
use Yaleksandr\HumanGate\State\AnswerProof;
use Yaleksandr\HumanGate\State\ChallengeBucket;
use Yaleksandr\HumanGate\State\LifecycleCode;
use Yaleksandr\HumanGate\Tests\Support\FrozenClock;
use Yaleksandr\HumanGate\Tests\Support\InMemoryChallengeStore;
use Yaleksandr\HumanGate\TextImage\TextImageStrategy;

#[TestDox('Сервис выполняет выдачу и проверку атомарно')]
final class ChallengeServiceTest extends TestCase
{
    #[TestDox('Вторая стратегия использует существующие выдачу, замену, попытки и однократное принятие ответа')]
    public function testCategorySelectionAlongsideTextImage(): void
    {
        $categories = [
            new CategorySelectionCategory('Фрукты', ['Яблоко', 'Груша', 'Банан', 'Апельсин']),
            new CategorySelectionCategory('Инструменты', ['Молоток', 'Пила', 'Дрель', 'Рубанок']),
        ];
        $renderer = new class implements TextImageRenderer {
            public function render(string $canonicalAnswer): ImagePresentation
            {
                return new ImagePresentation('image/png', 'bytes', 240, 80);
            }
        };
        $store = new InMemoryChallengeStore();
        $service = new ChallengeService(
            $store,
            new Policy(),
            new FrozenClock(1000),
            new TextImageStrategy($renderer),
            new CategorySelectionStrategy($categories, new CategorySelectionOptions()),
        );
        $purpose = new Purpose('login');
        $text = $service->issue($purpose)->challenge;
        self::assertNotNull($text);
        self::assertSame(ChallengeKind::TextImage, $text->kind);
        self::assertInstanceOf(ImagePresentation::class, $text->presentation);
        $issued = $service->issue($purpose, ChallengeKind::CategorySelection);
        self::assertSame(LifecycleCode::Issued, $issued->code);
        self::assertNotNull($issued->challenge);
        self::assertSame(ChallengeKind::CategorySelection, $issued->challenge->kind);
        self::assertInstanceOf(CategorySelectionPresentation::class, $issued->challenge->presentation);
        $oldId = $issued->challenge->id;
        $replacement = $service->replace($oldId, $purpose, ChallengeKind::CategorySelection);
        self::assertSame(LifecycleCode::Refreshed, $replacement->code);
        self::assertSame(VerificationCode::Replaced, $service->verify($oldId, $purpose, ''));
        self::assertNotNull($replacement->challenge);
        $challenge = $replacement->challenge;
        self::assertSame(ChallengeKind::CategorySelection, $challenge->kind);
        self::assertInstanceOf(CategorySelectionPresentation::class, $challenge->presentation);
        $presentation = $challenge->presentation;
        $targetItems = $presentation->category === $categories[0]->label ? $categories[0]->items : $categories[1]->items;
        $tokens = [];
        foreach ($presentation->cards as $card) {
            if (in_array($card->label, $targetItems, true)) {
                $tokens[] = $card->token;
            }
        }
        self::assertCount(2, $tokens);
        self::assertSame(VerificationCode::Incorrect, $service->verify($challenge->id, $purpose, 'malformed'));
        self::assertSame(1, $store->atomic(static fn(ChallengeBucket $bucket): ?int => $bucket->active($challenge->id)?->wrongAttempts));
        $answer = implode(',', $tokens);
        self::assertSame(VerificationCode::Accepted, $service->verify($challenge->id, $purpose, $answer));
        self::assertNull($store->atomic(static fn(ChallengeBucket $bucket): ?ActiveChallenge => $bucket->active($challenge->id)));
        self::assertSame(VerificationCode::AlreadyConsumed, $service->verify($challenge->id, $purpose, $answer));
    }

    public function testIssueWrongConsumeAndReplay(): void
    {
        $store = new InMemoryChallengeStore();
        $strategy = self::strategy();
        $service = new ChallengeService($store, new Policy(), new FrozenClock(1000), $strategy);
        $purpose = new Purpose('login');
        $issued = $service->issue($purpose);
        self::assertSame(LifecycleCode::Issued, $issued->code);
        self::assertNotNull($issued->challenge);
        self::assertSame(1180, $issued->challenge->expiresAt);
        self::assertSame(['id', 'kind', 'expiresAt', 'presentation'], array_keys(get_object_vars($issued->challenge)));
        $id = $issued->challenge->id;
        self::assertSame(str_repeat('a', 64), $store->atomic(static fn(ChallengeBucket $bucket): ?string => $bucket->active($id)?->proof->digest));
        self::assertSame(VerificationCode::PurposeMismatch, $service->verify($id, new Purpose('signup'), 'ok'));
        self::assertSame(0, $strategy->verifications);
        self::assertSame(VerificationCode::Incorrect, $service->verify($id, $purpose, 'bad'));
        self::assertSame(1, $store->atomic(static fn(ChallengeBucket $bucket): ?int => $bucket->active($id)?->wrongAttempts));
        self::assertSame(VerificationCode::Accepted, $service->verify($id, $purpose, 'ok'));
        self::assertSame(VerificationCode::AlreadyConsumed, $service->verify($id, $purpose, 'ok'));
        self::assertSame(2, $strategy->verifications);
    }

    public function testCapacityReplacementAndAttemptLimit(): void
    {
        $store = new InMemoryChallengeStore();
        $purpose = new Purpose('login');
        $service = new ChallengeService($store, new Policy(maxWrongAttempts: 1, maxActive: 1, maxActivePerPurpose: 1), new FrozenClock(1000), self::strategy());
        $first = $service->issue($purpose)->challenge;
        self::assertNotNull($first);
        $rejected = $service->issue($purpose);
        self::assertSame(LifecycleCode::CapacityExceeded, $rejected->code);
        self::assertNull($rejected->challenge);
        $replacement = $service->replace($first->id, $purpose);
        self::assertSame(LifecycleCode::Refreshed, $replacement->code);
        self::assertNotNull($replacement->challenge);
        self::assertSame(VerificationCode::Replaced, $service->verify($first->id, $purpose, 'ok'));
        self::assertSame(VerificationCode::AttemptsExhausted, $service->verify($replacement->challenge->id, $purpose, 'bad'));
    }

    public function testPreparationFailureLeavesStoreUntouched(): void
    {
        $store = new InMemoryChallengeStore();
        $strategy = self::strategy();
        $service = new ChallengeService($store, new Policy(), new FrozenClock(1000), $strategy);
        $purpose = new Purpose('login');
        $first = $service->issue($purpose)->challenge;
        self::assertNotNull($first);
        $strategy->failPreparation = true;
        try {
            $service->replace($first->id, $purpose);
            self::fail('Preparation failure was swallowed.');
        } catch (RuntimeException) {
            self::assertSame(VerificationCode::Incorrect, $service->verify($first->id, $purpose, 'bad'));
        }
    }

    public function testMissingAndDuplicateStrategies(): void
    {
        $store = new InMemoryChallengeStore();
        $purpose = new Purpose('login');
        $service = new ChallengeService($store, new Policy(), new FrozenClock(1000));
        $this->expectException(UnsupportedChallengeKindException::class);
        $service->issue($purpose);
    }

    public function testDuplicateStrategyRejected(): void
    {
        $strategy = self::strategy();
        $this->expectException(InvalidArgumentException::class);
        new ChallengeService(new InMemoryChallengeStore(), new Policy(), new FrozenClock(1000), $strategy, $strategy);
    }

    public function testStoredKindRequiresItsOwnStrategy(): void
    {
        $store = new InMemoryChallengeStore();
        $id = ChallengeId::fromString(str_repeat('b', 64));
        $purpose = new Purpose('login');
        $store->atomic(static function (ChallengeBucket $bucket) use ($id, $purpose): void {
            $bucket->setActive(new ActiveChallenge($id, $purpose, ChallengeKind::IconSequence, 1000, 1180, new AnswerProof(1, str_repeat('a', 64))));
        });
        $service = new ChallengeService($store, new Policy(), new FrozenClock(1000), self::strategy());
        try {
            $service->verify($id, $purpose, 'ok');
            self::fail('Missing stored strategy was accepted.');
        } catch (UnsupportedChallengeKindException) {
            self::assertSame(0, $store->atomic(static fn(ChallengeBucket $bucket): ?int => $bucket->active($id)?->wrongAttempts));
        }
    }

    public function testFinalTransitionWinsWhenTimeAdvances(): void
    {
        foreach (['ok', 'bad'] as $answer) {
            $clock = new QueueClock();
            $service = new ChallengeService(new InMemoryChallengeStore(), new Policy(), $clock, self::strategy());
            $purpose = new Purpose('login');
            $id = $service->issue($purpose)->challenge?->id;
            self::assertNotNull($id);
            $clock->times = [1179, 1180];
            self::assertSame(VerificationCode::Expired, $service->verify($id, $purpose, $answer));
        }
    }

    public function testCommitFailureCannotReturnAccepted(): void
    {
        $id = ChallengeId::fromString(str_repeat('b', 64));
        $purpose = new Purpose('login');
        $store = new CommitFailureStore(new ActiveChallenge($id, $purpose, ChallengeKind::TextImage, 1000, 1180, new AnswerProof(1, str_repeat('a', 64))));
        $service = new ChallengeService($store, new Policy(), new FrozenClock(1000), self::strategy());
        $this->expectException(RuntimeException::class);
        $service->verify($id, $purpose, 'ok');
    }

    public function testRendererFailurePreventsIssueMutation(): void
    {
        $store = new InMemoryChallengeStore();
        $renderer = new class implements TextImageRenderer {
            public function render(string $canonicalAnswer): ImagePresentation
            {
                throw new RuntimeException('Render failed.');
            }
        };
        $service = new ChallengeService($store, new Policy(), new FrozenClock(1000), new TextImageStrategy($renderer));
        try {
            $service->issue(new Purpose('login'));
            self::fail('Renderer failure was swallowed.');
        } catch (RuntimeException) {
            self::assertSame(0, $store->atomic(static fn(ChallengeBucket $bucket): int => $bucket->activeCount()));
        }
    }

    private static function strategy(): StubChallengeStrategy
    {
        return new StubChallengeStrategy();
    }
}

final class StubChallengeStrategy implements ChallengeStrategy
{
    public int $verifications = 0;
    public bool $failPreparation = false;

    public function kind(): ChallengeKind
    {
        return ChallengeKind::TextImage;
    }

    public function prepare(ChallengeId $id, Purpose $purpose): PreparedChallenge
    {
        if ($this->failPreparation) {
            throw new RuntimeException('Preparation failed.');
        }

        return new PreparedChallenge(new AnswerProof(1, str_repeat('a', 64)), new ImagePresentation('image/png', 'bytes', 240, 80));
    }

    public function verify(ActiveChallenge $challenge, string $submittedAnswer): bool
    {
        ++$this->verifications;

        return $submittedAnswer === 'ok';
    }
}

final class QueueClock implements Clock
{
    /** @var list<int> */
    public array $times = [];

    public function now(): int
    {
        return array_shift($this->times) ?? 1000;
    }
}

final class CommitFailureStore implements ChallengeStore
{
    public function __construct(private ActiveChallenge $active) {}

    public function atomic(Closure $transition): mixed
    {
        $bucket = new ChallengeBucket();
        $bucket->setActive($this->active);
        $transition($bucket);

        throw new RuntimeException('Commit failed.');
    }
}
