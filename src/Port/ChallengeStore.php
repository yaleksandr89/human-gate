<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Port;

use Closure;
use Yaleksandr\HumanGate\State\ChallengeBucket;

interface ChallengeStore
{
    /**
     * EN: Atomically transforms one bucket in the adapter's configured session scope.
     *
     * Exclusive ownership/serialization must begin BEFORE reading current state.
     * The callback receives the authoritative current bucket in an isolated scope.
     * Mutations must be committed before its result may be returned. Commit failure
     * must throw, never expose a successful callback result. Callback failure must
     * leave persisted state unchanged, with no partial commit. Neither a retained
     * callback bucket nor a returned reference may mutate committed state after the operation.
     *
     * The callback must not perform irreversible external side effects or retain
     * the mutable bucket for later use. Nested atomic calls are unsupported.
     * Guarantees apply only to the concrete adapter's supported storage/locking
     * environment; this port does not promise distributed consensus.
     *
     * RU: Атомарно преобразует один контейнер состояния в настроенной для адаптера области сессии.
     *
     * Исключительное владение/сериализация доступа должны устанавливаться ДО чтения текущего состояния.
     * Обработчик получает актуальный контейнер состояния, являющийся источником истины, в изолированной области.
     * Изменения должны быть зафиксированы до возврата результата обработчика. При ошибке фиксации
     * должно быть выброшено исключение; успешный результат обработчика не должен возвращаться.
     * При сбое обработчика сохранённое состояние должно оставаться неизменным, без частичной фиксации.
     * Ни сохранённый контейнер состояния обработчика, ни возвращённая ссылка не должны позволять
     * менять зафиксированное состояние после завершения операции.
     *
     * Обработчик не должен выполнять необратимые внешние побочные действия или сохранять
     * изменяемый контейнер состояния для последующего использования. Вложенные атомарные вызовы не поддерживаются.
     * Гарантии действуют только в среде хранения/блокировок, поддерживаемой конкретным адаптером;
     * этот порт не обещает распределённого консенсуса.
     *
     * @template T
     * @param Closure(ChallengeBucket): T $transition
     * @return T
     */
    public function atomic(Closure $transition): mixed;
}
