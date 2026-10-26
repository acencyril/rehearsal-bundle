<?php

declare(strict_types=1);

namespace Acencyril\RehearsalBundle\Tests;

use Acencyril\RehearsalBundle\Model\ChangeSet;
use Acencyril\RehearsalBundle\Model\Summary;
use PHPUnit\Framework\TestCase;

final class SummaryTest extends TestCase
{
    public function testTheFixtureSummaryIsRead(): void
    {
        $set = ChangeSet::fromArray(json_decode((string) file_get_contents(__DIR__.'/Fixtures/changes.json'), true));
        $s = $set->get('uplift-2026-11-02')?->summary;

        self::assertNotNull($s);
        self::assertSame(['package' => 'symfony/*', 'label' => 'Symfony', 'from' => '7.3.11', 'to' => '7.4.21'], $s->main);
        self::assertSame([40, 11], [$s->packagesMoved, $s->constraintsRewritten]);
        self::assertSame(['after' => 'passed'], $s->tests, 'what was not measured stays out');
        self::assertSame(['RenameAttributeRector'], $s->deprecationsFixed['rules'] ?? null);
        self::assertSame(['8.1.9'], $s->mainAhead());
        self::assertSame([['name' => 'phpunit/phpunit', 'versions' => ['13.4.3']]], $s->otherAhead());
        self::assertNull($set->get('contact-form')?->summary, 'a change without a summary has none');
        self::assertArrayHasKey('summary', $set->get('uplift-2026-11-02')->toArray());
        self::assertArrayNotHasKey('summary', $set->get('contact-form')->toArray());
    }

    public function testMissingOrEmptyIsNull(): void
    {
        self::assertNull(Summary::fromArray(null));
        self::assertNull(Summary::fromArray('7.4'));
        self::assertNull(Summary::fromArray([]));
        self::assertNull(Summary::fromArray(['main' => ['from' => '7.3'], 'tests' => ['after' => 'green'], 'blocked' => 'nope']));
    }

    public function testBadValuesAreDroppedNotGuessed(): void
    {
        $s = Summary::fromArray([
            'main' => ['package' => 'symfony/*', 'from' => 'v7.3.11', 'to' => '<b>7.4</b>'],
            'php' => ['from' => '8.2', 'to' => '8.2'],
            'packages_moved' => -3,
            'constraints_rewritten' => 'many',
            'tests' => ['before' => 'passed', 'after' => 'unknown'],
            'adoptions' => [['id' => 'x', 'status' => 'whatever', 'places' => '2'], ['title' => 'no id']],
            'blocked' => [['name' => 'phpunit/phpunit', 'version' => '13.4.3', 'reason' => 'requires PHP >=8.4']],
            'path_ahead' => [['name' => 'php', 'versions' => ['8.4.27', 'not a version']], ['name' => 'empty', 'versions' => []]],
        ]);

        self::assertNotNull($s);
        self::assertNull($s->main, 'no valid "to": no main move');
        self::assertNull($s->php, 'PHP did not move');
        self::assertSame(0, $s->packagesMoved);
        self::assertNull($s->constraintsRewritten);
        self::assertSame(['before' => 'passed'], $s->tests);
        self::assertSame([['id' => 'x', 'title' => '', 'status' => 'proposed', 'places' => 2]], $s->adoptions);
        self::assertSame([['name' => 'php', 'versions' => ['8.4.27']]], $s->pathAhead);
        self::assertSame([], $s->mainAhead());
    }

    public function testRoundTrip(): void
    {
        $data = [
            'main' => ['package' => 'symfony/*', 'label' => 'Symfony', 'from' => '7.4.21', 'to' => '8.1.9'],
            'php' => ['from' => '8.3', 'to' => '8.4'],
            'tests' => ['before' => 'passed', 'after' => 'failed'],
        ];
        self::assertSame($data, Summary::fromArray($data)?->toArray());
    }
}
