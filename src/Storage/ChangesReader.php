<?php

declare(strict_types=1);

namespace Acencyril\RehearsalBundle\Storage;

use Acencyril\RehearsalBundle\Model\ChangeSet;

/** Reads the changes file. A missing or broken file is an empty set, never an error page. */
final class ChangesReader
{
    public function __construct(private readonly string $file)
    {
    }

    public function read(): ChangeSet
    {
        if (!is_file($this->file)) {
            return new ChangeSet();
        }

        return ChangeSet::fromArray(json_decode((string) file_get_contents($this->file), true));
    }

    public function file(): string
    {
        return $this->file;
    }
}
