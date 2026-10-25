<?php

declare(strict_types=1);

namespace Acencyril\RehearsalBundle\Storage;

use Acencyril\RehearsalBundle\Model\Decision;

/**
 * The decisions file: every decision ever taken, newest last, and the change revisions
 * already announced (so the recap email goes once per new change). Writes hold an
 * exclusive lock: two clicks at the same time both land.
 *
 *   {"decisions": [{change, revision, decision, comment, by, at, auto}], "seen": ["id@revision"]}
 */
final class DecisionStore
{
    public function __construct(private readonly string $file)
    {
    }

    /** @return list<Decision> */
    public function all(): array
    {
        return $this->load()['decisions'];
    }

    /** The latest decision on a change, whatever its revision. */
    public function latest(string $change): ?Decision
    {
        $found = null;
        foreach ($this->all() as $d) {
            if ($d->change === $change) {
                $found = $d;
            }
        }

        return $found;
    }

    public function add(Decision $decision): void
    {
        $this->update(static function (array $data) use ($decision): array {
            $data['decisions'][] = $decision;

            return $data;
        });
    }

    /**
     * Marks these keys as announced and returns those that were not yet.
     *
     * @param list<string> $keys
     *
     * @return list<string>
     */
    public function markSeen(array $keys): array
    {
        $new = [];
        $this->update(static function (array $data) use ($keys, &$new): array {
            $new = array_values(array_diff($keys, $data['seen']));
            $data['seen'] = array_values(array_unique(array_merge($data['seen'], $new)));

            return $data;
        });

        return $new;
    }

    public function file(): string
    {
        return $this->file;
    }

    /** @param callable(array{decisions: list<Decision>, seen: list<string>}): array{decisions: list<Decision>, seen: list<string>} $change */
    private function update(callable $change): void
    {
        $dir = \dirname($this->file);
        if (!is_dir($dir) && !mkdir($dir, 0o775, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Cannot create %s.', $dir));
        }
        $h = fopen($this->file, 'c+');
        if (false === $h) {
            throw new \RuntimeException(sprintf('Cannot open %s.', $this->file));
        }
        try {
            flock($h, \LOCK_EX);
            $data = $change(self::decode((string) stream_get_contents($h)));
            ftruncate($h, 0);
            rewind($h);
            fwrite($h, json_encode([
                'decisions' => array_map(static fn (Decision $d) => $d->toArray(), $data['decisions']),
                'seen' => $data['seen'],
            ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE)."\n");
            fflush($h);
            flock($h, \LOCK_UN);
        } finally {
            fclose($h);
        }
    }

    /** @return array{decisions: list<Decision>, seen: list<string>} */
    private function load(): array
    {
        return is_file($this->file) ? self::decode((string) file_get_contents($this->file)) : ['decisions' => [], 'seen' => []];
    }

    /** @return array{decisions: list<Decision>, seen: list<string>} */
    private static function decode(string $json): array
    {
        $raw = json_decode($json, true);
        $decisions = [];
        foreach (\is_array($raw['decisions'] ?? null) ? $raw['decisions'] : [] as $d) {
            if (null !== ($decision = Decision::fromArray($d))) {
                $decisions[] = $decision;
            }
        }

        return ['decisions' => $decisions, 'seen' => array_values(array_filter(\is_array($raw['seen'] ?? null) ? $raw['seen'] : [], 'is_string'))];
    }
}
