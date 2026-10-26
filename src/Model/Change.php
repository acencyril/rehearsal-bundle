<?php

declare(strict_types=1);

namespace Acencyril\RehearsalBundle\Model;

/**
 * One proposed change, as written in the changes file:
 *
 *   id        a-z0-9 . _ - (64 at most), stable across revisions of the same change
 *   title     what it is
 *   detail    the notes: what changed and why
 *   replaced  what it replaces, if anything
 *   check     what to look at to say yes
 *   url       a path of this site to open (anything else is dropped)
 *   source    who proposed it ("uplift", "manual"…)
 *   revision  the code it was tested on; a decision holds for one revision
 *   tests     {status: passed|failed|not_run, summary, output}
 *   summary   optional facts of an upgrade, shown as a card (see Summary)
 */
final class Change
{
    public const ID = '/^[a-z0-9][a-z0-9._-]{0,63}$/';

    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $detail = '',
        public readonly string $replaced = '',
        public readonly string $check = '',
        public readonly string $url = '',
        public readonly string $source = '',
        public readonly string $revision = '',
        public readonly ?Tests $tests = null,
        public readonly string $proposedAt = '',
        public readonly ?Summary $summary = null,
    ) {
    }

    /** Null when the entry is not a change (no id, no title, a bad id). */
    public static function fromArray(mixed $c, string $setRevision = '', ?Tests $setTests = null): ?self
    {
        if (!\is_array($c) || !isset($c['id'], $c['title']) || !\is_string($c['id']) || !preg_match(self::ID, $c['id'])) {
            return null;
        }
        $url = (string) ($c['url'] ?? '');
        $revision = (string) ($c['revision'] ?? '');

        return new self(
            $c['id'],
            mb_substr((string) $c['title'], 0, 300),
            mb_substr((string) ($c['detail'] ?? ''), 0, 20000),
            mb_substr((string) ($c['replaced'] ?? ''), 0, 4000),
            mb_substr((string) ($c['check'] ?? ''), 0, 4000),
            // only a path of this site: the file never sends anyone elsewhere
            preg_match('#^/(?!/)[A-Za-z0-9/_.\-?=&%~]*$#', $url) ? $url : '',
            preg_match('/^[a-z0-9_.-]{1,40}$/i', (string) ($c['source'] ?? '')) ? (string) $c['source'] : '',
            preg_match('/^[A-Za-z0-9._-]{1,64}$/', $revision) ? $revision : (preg_match('/^[A-Za-z0-9._-]{1,64}$/', $setRevision) ? $setRevision : ''),
            Tests::fromArray($c['tests'] ?? null) ?? $setTests,
            mb_substr((string) ($c['proposed_at'] ?? ''), 0, 40),
            Summary::fromArray($c['summary'] ?? null),
        );
    }

    /** The key a decision is attached to. */
    public function key(): string
    {
        return $this->id.'@'.$this->revision;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'title' => $this->title, 'detail' => $this->detail, 'replaced' => $this->replaced,
            'check' => $this->check, 'url' => $this->url, 'source' => $this->source, 'revision' => $this->revision,
            'tests' => $this->tests?->toArray(), 'proposed_at' => $this->proposedAt,
        ] + (null !== $this->summary ? ['summary' => $this->summary->toArray()] : []);
    }
}
