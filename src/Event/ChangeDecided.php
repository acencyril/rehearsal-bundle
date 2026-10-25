<?php

declare(strict_types=1);

namespace Acencyril\RehearsalBundle\Event;

use Acencyril\RehearsalBundle\Model\Change;
use Acencyril\RehearsalBundle\Model\ChangeSet;
use Acencyril\RehearsalBundle\Model\Decision;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * A change was validated or rejected. Dispatched as ChangeValidated or ChangeRejected,
 * under that class name: listen to those.
 */
abstract class ChangeDecided extends Event
{
    public function __construct(public readonly Change $change, public readonly Decision $decision, public readonly ChangeSet $set)
    {
    }
}
