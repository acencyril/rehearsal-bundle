<?php

declare(strict_types=1);

namespace Acencyril\RehearsalBundle\Model;

/**
 * The test results attached to a change, or to the whole set: status "passed",
 * "failed" or "not_run", a one-line summary, and the end of the output.
 *
 * The older form {ok: 12, ko: 0, failures: [..]} (automatic journeys) is read too.
 */
final class Tests
{
    public const STATUSES = ['passed', 'failed', 'not_run'];

    /** @param list<string> $failures */
    public function __construct(
        public readonly string $status = 'not_run',
        public readonly string $summary = '',
        public readonly string $output = '',
        public readonly array $failures = [],
        public readonly string $at = '',
    ) {
    }

    public static function fromArray(mixed $data): ?self
    {
        if (!\is_array($data)) {
            return null;
        }
        $failures = array_values(array_map(static fn ($f) => mb_substr((string) $f, 0, 500), \array_slice(\is_array($data['failures'] ?? null) ? $data['failures'] : [], 0, 50)));
        if (!isset($data['status']) && isset($data['ok'], $data['ko'])) {
            $ok = (int) $data['ok'];
            $ko = (int) $data['ko'];

            return new self($ko > 0 ? 'failed' : ($ok > 0 ? 'passed' : 'not_run'), sprintf('%d/%d', $ok, $ok + $ko), '', $failures, mb_substr((string) ($data['at'] ?? ''), 0, 40));
        }
        $status = \in_array($data['status'] ?? null, self::STATUSES, true) ? (string) $data['status'] : 'not_run';

        return new self($status, mb_substr((string) ($data['summary'] ?? ''), 0, 500), mb_substr((string) ($data['output'] ?? ''), -8000), $failures, mb_substr((string) ($data['at'] ?? ''), 0, 40));
    }

    public function passed(): bool
    {
        return 'passed' === $this->status;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['status' => $this->status, 'summary' => $this->summary, 'output' => $this->output, 'failures' => $this->failures, 'at' => $this->at];
    }
}
