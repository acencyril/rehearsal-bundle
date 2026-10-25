<?php

declare(strict_types=1);

namespace Acencyril\RehearsalBundle\Model;

/** A "validated" or "rejected" on one revision of a change, with who, when and why. */
final class Decision
{
    public const VALIDATED = 'validated';
    public const REJECTED = 'rejected';

    public function __construct(
        public readonly string $change,
        public readonly string $revision,
        public readonly string $decision,
        public readonly string $comment = '',
        public readonly string $by = '',
        public readonly string $at = '',
        public readonly bool $auto = false,
    ) {
        if (!\in_array($decision, [self::VALIDATED, self::REJECTED], true)) {
            throw new \InvalidArgumentException(sprintf('A decision is "validated" or "rejected", "%s" given.', $decision));
        }
    }

    public static function fromArray(mixed $d): ?self
    {
        if (!\is_array($d) || !isset($d['change'], $d['decision']) || !\in_array($d['decision'], [self::VALIDATED, self::REJECTED], true)) {
            return null;
        }

        return new self((string) $d['change'], (string) ($d['revision'] ?? ''), (string) $d['decision'], (string) ($d['comment'] ?? ''), (string) ($d['by'] ?? ''), (string) ($d['at'] ?? ''), (bool) ($d['auto'] ?? false));
    }

    public function validated(): bool
    {
        return self::VALIDATED === $this->decision;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['change' => $this->change, 'revision' => $this->revision, 'decision' => $this->decision, 'comment' => $this->comment, 'by' => $this->by, 'at' => $this->at, 'auto' => $this->auto];
    }
}
