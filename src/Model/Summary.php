<?php

declare(strict_types=1);

namespace Acencyril\RehearsalBundle\Model;

/**
 * The optional "summary" of a change: the facts of an upgrade, shown as a card at the
 * top of the change. Any tool can write it (Uplift does); every field is optional and
 * what a tool did not measure is left out, never guessed:
 *
 *   main                   {package, label, from, to}  the main move ("symfony/*", "Symfony", 7.3.11, 7.4.21)
 *   php                    {from, to}                  when PHP moved
 *   packages_moved         int                         packages whose locked version changed
 *   constraints_rewritten  int                         composer.json constraints rewritten
 *   tests                  {before, after}             passed | failed | not_run
 *   deprecations_fixed     {rules: [..], files: [..]}  what Rector (or another tool) rewrote
 *   adoptions              [{id, title, status, places}]
 *   blocked                [{name, version, reason}]    moves refused by the policy
 *   path_ahead             [{name, versions: [..]}]     the steps left for later runs
 */
final class Summary
{
    public const ADOPTION_STATUSES = ['applied', 'untested', 'proposed', 'reverted', 'rector_failed', 'unchanged'];

    /**
     * @param array{package: string, label: string, from: string, to: string}|null      $main
     * @param array{from: string, to: string}|null                                       $php
     * @param array{before?: string, after?: string}                                     $tests
     * @param array{rules: list<string>, files: list<string>}|null                       $deprecationsFixed
     * @param list<array{id: string, title: string, status: string, places: ?int}>       $adoptions
     * @param list<array{name: string, version: string, reason: string}>                $blocked
     * @param list<array{name: string, versions: list<string>}>                          $pathAhead
     */
    public function __construct(
        public readonly ?array $main = null,
        public readonly ?array $php = null,
        public readonly ?int $packagesMoved = null,
        public readonly ?int $constraintsRewritten = null,
        public readonly array $tests = [],
        public readonly ?array $deprecationsFixed = null,
        public readonly array $adoptions = [],
        public readonly array $blocked = [],
        public readonly array $pathAhead = [],
    ) {
    }

    /** Null when there is no summary, or nothing usable in it. */
    public static function fromArray(mixed $s): ?self
    {
        if (!\is_array($s)) {
            return null;
        }
        $main = null;
        if (\is_array($s['main'] ?? null) && '' !== ($from = self::version($s['main']['from'] ?? null)) && '' !== ($to = self::version($s['main']['to'] ?? null))) {
            $package = self::text($s['main']['package'] ?? '', 100);
            $main = ['package' => $package, 'label' => self::text($s['main']['label'] ?? '', 60) ?: $package, 'from' => $from, 'to' => $to];
        }
        $php = null;
        if (\is_array($s['php'] ?? null) && '' !== ($from = self::version($s['php']['from'] ?? null)) && '' !== ($to = self::version($s['php']['to'] ?? null)) && $from !== $to) {
            $php = ['from' => $from, 'to' => $to];
        }
        $tests = [];
        foreach (['before', 'after'] as $k) {
            if (\in_array($s['tests'][$k] ?? null, Tests::STATUSES, true)) {
                $tests[$k] = $s['tests'][$k];
            }
        }
        $deprecations = null;
        if (\is_array($d = $s['deprecations_fixed'] ?? null)) {
            $rules = self::strings($d['rules'] ?? null, 200);
            $files = self::strings($d['files'] ?? null, 300);
            $deprecations = [] === $rules && [] === $files ? null : ['rules' => $rules, 'files' => $files];
        }
        $adoptions = [];
        foreach (self::list($s['adoptions'] ?? null) as $a) {
            $id = self::text($a['id'] ?? '', 64);
            if ('' === $id) {
                continue;
            }
            $adoptions[] = [
                'id' => $id,
                'title' => self::text($a['title'] ?? '', 200),
                'status' => \in_array($a['status'] ?? null, self::ADOPTION_STATUSES, true) ? $a['status'] : 'proposed',
                'places' => isset($a['places']) && is_numeric($a['places']) ? max(0, (int) $a['places']) : null,
            ];
        }
        $blocked = [];
        foreach (self::list($s['blocked'] ?? null) as $b) {
            if ('' !== ($name = self::text($b['name'] ?? '', 100))) {
                $blocked[] = ['name' => $name, 'version' => self::version($b['version'] ?? null), 'reason' => self::text($b['reason'] ?? '', 500)];
            }
        }
        $path = [];
        foreach (self::list($s['path_ahead'] ?? null) as $p) {
            $versions = array_values(array_filter(array_map(self::version(...), \is_array($p['versions'] ?? null) ? \array_slice($p['versions'], 0, 10) : [])));
            if ('' !== ($name = self::text($p['name'] ?? '', 100)) && [] !== $versions) {
                $path[] = ['name' => $name, 'versions' => $versions];
            }
        }

        $summary = new self(
            $main,
            $php,
            self::count($s['packages_moved'] ?? null),
            self::count($s['constraints_rewritten'] ?? null),
            $tests,
            $deprecations,
            $adoptions,
            $blocked,
            $path,
        );

        return $summary->isEmpty() ? null : $summary;
    }

    public function isEmpty(): bool
    {
        return null === $this->main && null === $this->php && null === $this->packagesMoved && null === $this->constraintsRewritten
            && [] === $this->tests && null === $this->deprecationsFixed && [] === $this->adoptions && [] === $this->blocked && [] === $this->pathAhead;
    }

    /** @return list<string> the later steps of the main package ("then 8.1.9 in later runs") */
    public function mainAhead(): array
    {
        foreach ($this->pathAhead as $p) {
            if (null !== $this->main && $p['name'] === $this->main['package']) {
                return $p['versions'];
            }
        }

        return [];
    }

    /** @return list<array{name: string, versions: list<string>}> the way ahead of the other packages */
    public function otherAhead(): array
    {
        return array_values(array_filter($this->pathAhead, fn (array $p) => null === $this->main || $p['name'] !== $this->main['package']));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'main' => $this->main,
            'php' => $this->php,
            'packages_moved' => $this->packagesMoved,
            'constraints_rewritten' => $this->constraintsRewritten,
            'tests' => $this->tests,
            'deprecations_fixed' => $this->deprecationsFixed,
            'adoptions' => $this->adoptions,
            'blocked' => $this->blocked,
            'path_ahead' => $this->pathAhead,
        ], static fn ($v) => null !== $v && [] !== $v);
    }

    private static function text(mixed $v, int $max): string
    {
        return \is_string($v) || \is_int($v) || \is_float($v) ? mb_substr(trim((string) $v), 0, $max) : '';
    }

    /** "7.4.21", "v8.1.9", "8.3": a version, or "" */
    private static function version(mixed $v): string
    {
        $v = self::text($v, 40);

        return preg_match('/^v?\d+(\.\d+){0,3}([-.+][A-Za-z0-9.]+)?$/', $v) ? ltrim($v, 'v') : '';
    }

    private static function count(mixed $v): ?int
    {
        return \is_int($v) || (\is_string($v) && ctype_digit($v)) ? max(0, (int) $v) : null;
    }

    /** @return list<string> */
    private static function strings(mixed $v, int $max): array
    {
        return array_values(array_filter(array_map(static fn ($x) => self::text($x, $max), \is_array($v) ? \array_slice($v, 0, 50) : [])));
    }

    /** @return list<array<mixed>> */
    private static function list(mixed $v): array
    {
        return array_values(array_filter(\is_array($v) ? \array_slice($v, 0, 50) : [], 'is_array'));
    }
}
