<?php

declare(strict_types=1);

namespace Acencyril\RehearsalBundle\Tests\Fixtures;

use Acencyril\RehearsalBundle\Event\ChangeRejected;
use Acencyril\RehearsalBundle\Event\ChangesProposed;
use Acencyril\RehearsalBundle\Event\ChangeValidated;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\Event;

/** What a host application would hook: here, every Rehearsal event is written down. */
final class EventLog implements EventSubscriberInterface
{
    /** @var list<Event> */
    public array $events = [];

    public static function getSubscribedEvents(): array
    {
        return [ChangeValidated::class => 'log', ChangeRejected::class => 'log', ChangesProposed::class => 'log'];
    }

    public function log(Event $event): void
    {
        $this->events[] = $event;
    }
}
