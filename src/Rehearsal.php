<?php

declare(strict_types=1);

namespace Acencyril\RehearsalBundle;

use Acencyril\RehearsalBundle\Event\ChangeRejected;
use Acencyril\RehearsalBundle\Event\ChangesProposed;
use Acencyril\RehearsalBundle\Event\ChangeValidated;
use Acencyril\RehearsalBundle\Mail\Notifier;
use Acencyril\RehearsalBundle\Model\Change;
use Acencyril\RehearsalBundle\Model\Decision;
use Acencyril\RehearsalBundle\Storage\ChangesReader;
use Acencyril\RehearsalBundle\Storage\DecisionStore;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Proposed changes and the decisions on them.
 *
 * A decision holds for one revision of a change: when the change comes back with a new
 * revision (fixed after a rejection, or tested again), it is pending again.
 *
 * validation: "manual" waits for a person's click; "auto" validates a pending change as
 * soon as its tests passed (a change without passing tests still waits for a person).
 */
final class Rehearsal
{
    public const MANUAL = 'manual';
    public const AUTO = 'auto';

    public function __construct(
        private readonly ChangesReader $changes,
        private readonly DecisionStore $decisions,
        private readonly ?EventDispatcherInterface $dispatcher,
        private readonly Notifier $notifier,
        private readonly string $validation = self::MANUAL,
        private readonly ?ClockInterface $clock = null,
    ) {
        if (!\in_array($validation, [self::MANUAL, self::AUTO], true)) {
            throw new \InvalidArgumentException(sprintf('rehearsal.validation is "manual" or "auto", "%s" given.', $validation));
        }
    }

    public function validation(): string
    {
        return $this->validation;
    }

    /**
     * Where everything stands, for the admin page, the status command and CI.
     *
     * @return array{set: Model\ChangeSet, validation: string, items: list<array{change: Change, state: string, decision: ?Decision, earlier: ?Decision}>, counts: array<string, int>, history: list<Decision>}
     */
    public function board(): array
    {
        $set = $this->changes->read();
        $all = $this->decisions->all();
        $items = [];
        $counts = ['pending' => 0, 'validated' => 0, 'rejected' => 0];
        foreach ($set->changes as $change) {
            $current = $earlier = null;
            foreach ($all as $d) {
                if ($d->change !== $change->id) {
                    continue;
                }
                if ($d->revision === $change->revision) {
                    $current = $d;
                } else {
                    $earlier = $d;
                }
            }
            $state = null === $current ? 'pending' : $current->decision;
            ++$counts[$state];
            $items[] = ['change' => $change, 'state' => $state, 'decision' => $current, 'earlier' => null === $current ? $earlier : null];
        }

        return ['set' => $set, 'validation' => $this->validation, 'items' => $items, 'counts' => $counts, 'history' => \array_slice(array_reverse($all), 0, 200)];
    }

    /**
     * Records a decision on the current revision of a change and dispatches
     * ChangeValidated or ChangeRejected.
     *
     * @throws \DomainException unknown change, or a rejection without a comment
     */
    public function decide(string $id, string $decision, string $comment = '', string $by = '', bool $auto = false, bool $notify = true): Decision
    {
        $set = $this->changes->read();
        $change = $set->get($id) ?? throw new \DomainException('error.unknown');
        $comment = trim(mb_substr($comment, 0, 4000));
        if (Decision::REJECTED === $decision && '' === $comment) {
            throw new \DomainException('error.comment_required');
        }
        $d = new Decision($change->id, $change->revision, $decision, $comment, mb_substr($by, 0, 180), $this->now(), $auto);
        $this->decisions->add($d);
        $this->dispatcher?->dispatch($d->validated() ? new ChangeValidated($change, $d, $set) : new ChangeRejected($change, $d, $set));
        if ($notify) {
            $this->notifier->decided($change, $d);
        }

        return $d;
    }

    /**
     * Notices new changes (one recap email, ChangesProposed) and, in auto mode,
     * validates the pending changes whose tests passed. Idempotent: run it from cron,
     * after building a sandbox, or let the admin page run it.
     *
     * @return array{new: list<Change>, auto: list<Decision>}
     */
    public function sync(): array
    {
        $set = $this->changes->read();
        $keys = array_map(static fn (Change $c) => $c->key(), $set->changes);
        $newKeys = [] === $keys ? [] : $this->decisions->markSeen($keys);
        $new = array_values(array_filter($set->changes, static fn (Change $c) => \in_array($c->key(), $newKeys, true)));

        $auto = [];
        if (self::AUTO === $this->validation) {
            foreach ($this->board()['items'] as $item) {
                if ('pending' === $item['state'] && true === $item['change']->tests?->passed()) {
                    $isNew = \in_array($item['change']->key(), $newKeys, true);
                    $auto[] = $this->decide($item['change']->id, Decision::VALIDATED, '', 'auto', true, !$isNew);
                }
            }
        }
        if ([] !== $new) {
            $this->dispatcher?->dispatch(new ChangesProposed($new, $set));
            $this->notifier->proposed($new, $set, $auto);
        }

        return ['new' => $new, 'auto' => $auto];
    }

    private function now(): string
    {
        return ($this->clock?->now() ?? new \DateTimeImmutable())->format(\DATE_ATOM);
    }
}
