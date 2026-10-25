<?php

declare(strict_types=1);

namespace Acencyril\RehearsalBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Rehearsal — try a change in the site's admin before production: the proposed
 * changes with their notes and test results, "Validate" or "Reject with a comment",
 * and an event the application hooks to merge and deploy.
 */
class RehearsalBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
