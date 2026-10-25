<?php

declare(strict_types=1);

namespace Acencyril\RehearsalBundle\Event;

/**
 * A change was validated, by a person or automatically (validation: auto, tests
 * passed: $decision->auto). Hook it to merge and deploy, open a pull request, tag…
 * Rehearsal itself does nothing more than record the decision.
 */
final class ChangeValidated extends ChangeDecided
{
}
