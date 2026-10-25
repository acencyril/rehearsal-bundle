<?php

declare(strict_types=1);

namespace Acencyril\RehearsalBundle\Event;

/** A change was rejected; $decision->comment says why. */
final class ChangeRejected extends ChangeDecided
{
}
