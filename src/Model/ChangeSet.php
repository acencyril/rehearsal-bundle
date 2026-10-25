<?php

declare(strict_types=1);

namespace Acencyril\RehearsalBundle\Model;

/**
 * The changes file: {title, summary, link, revision, tests, changes: [..]}. "link" is
 * where to read more (a pull request), https only; "pr" is read as "link" too.
 */
final class ChangeSet
{
    /** @param list<Change> $changes */
    public function __construct(
        public readonly string $title = '',
        public readonly string $summary = '',
        public readonly string $link = '',
        public readonly string $revision = '',
        public readonly ?Tests $tests = null,
        public readonly array $changes = [],
    ) {
    }

    public static function fromArray(mixed $data): self
    {
        if (!\is_array($data)) {
            return new self();
        }
        $link = (string) ($data['link'] ?? $data['pr'] ?? '');
        $revision = (string) ($data['revision'] ?? '');
        $tests = Tests::fromArray($data['tests'] ?? null);
        $changes = [];
        $seen = [];
        foreach (\is_array($data['changes'] ?? null) ? $data['changes'] : [] as $c) {
            $change = Change::fromArray($c, $revision, $tests);
            if (null !== $change && !isset($seen[$change->id])) {
                $seen[$change->id] = true;
                $changes[] = $change;
            }
        }

        return new self(
            mb_substr((string) ($data['title'] ?? ''), 0, 300),
            mb_substr((string) ($data['summary'] ?? ''), 0, 20000),
            preg_match('#^https://[^\s"<>]+$#', $link) ? $link : '',
            preg_match('/^[A-Za-z0-9._-]{1,64}$/', $revision) ? $revision : '',
            $tests,
            $changes,
        );
    }

    public function get(string $id): ?Change
    {
        foreach ($this->changes as $c) {
            if ($c->id === $id) {
                return $c;
            }
        }

        return null;
    }
}
