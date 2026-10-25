<?php

declare(strict_types=1);

namespace Acencyril\RehearsalBundle\Event;

use Acencyril\RehearsalBundle\Model\Change;
use Acencyril\RehearsalBundle\Model\ChangeSet;
use Symfony\Contracts\EventDispatcher\Event;

/** Changes (or new revisions of changes) appeared in the changes file. */
final class ChangesProposed extends Event
{
    /** @param list<Change> $changes */
    public function __construct(public readonly array $changes, public readonly ChangeSet $set)
    {
    }
}
