<?php

declare(strict_types=1);

namespace Acencyril\RehearsalBundle\Tests;

use Acencyril\RehearsalBundle\Model\ChangeSet;
use Acencyril\RehearsalBundle\Model\Decision;
use Acencyril\RehearsalBundle\Model\Tests;
use PHPUnit\Framework\TestCase;

final class ModelTest extends TestCase
{
    private static function fixture(): ChangeSet
    {
        return ChangeSet::fromArray(json_decode((string) file_get_contents(__DIR__.'/Fixtures/changes.json'), true));
    }

    public function testTheChangesFileIsRead(): void
    {
        $set = self::fixture();
        self::assertSame('November maintenance', $set->title);
        self::assertSame('https://github.com/example/site/pull/12', $set->link, '"pr" is read as "link"');
        self::assertSame(['uplift-2026-11-02', 'contact-form', 'legal-pages'], array_map(static fn ($c) => $c->id, $set->changes), 'entries without a valid id are dropped');
        self::assertSame('abc1234', $set->get('contact-form')?->revision, 'the set revision is inherited');
        self::assertSame('uplift', $set->get('uplift-2026-11-02')?->source);
        self::assertTrue($set->get('uplift-2026-11-02')?->tests?->passed());
        self::assertSame(['ContactTest::testSend'], $set->get('contact-form')?->tests?->failures);
    }

    public function testOnlyPathsOfTheSiteAreLinked(): void
    {
        $set = self::fixture();
        self::assertSame('/en', $set->get('uplift-2026-11-02')?->url);
        self::assertSame('', $set->get('contact-form')?->url, 'protocol-relative URL dropped');
        self::assertSame('', $set->get('legal-pages')?->url, 'javascript: dropped');
        self::assertSame('', ChangeSet::fromArray(['link' => 'http://example.test/pr/1'])->link, 'https only');
    }

    public function testBrokenInputIsAnEmptySet(): void
    {
        self::assertSame([], ChangeSet::fromArray(null)->changes);
        self::assertSame([], ChangeSet::fromArray(['changes' => 'nope'])->changes);
        $dup = ChangeSet::fromArray(['changes' => [['id' => 'a', 'title' => 'First'], ['id' => 'a', 'title' => 'Second']]]);
        self::assertSame(['First'], array_map(static fn ($c) => $c->title, $dup->changes));
    }

    public function testTheJourneysFormIsReadToo(): void
    {
        self::assertSame('failed', Tests::fromArray(['ok' => 350, 'ko' => 2, 'failures' => ['journey 12']])?->status);
        self::assertSame('passed', Tests::fromArray(['ok' => 355, 'ko' => 0])?->status);
        self::assertSame('355/355', Tests::fromArray(['ok' => 355, 'ko' => 0])?->summary);
        self::assertSame('not_run', Tests::fromArray(['status' => 'green'])?->status, 'an unknown status is not a pass');
        $set = ChangeSet::fromArray(['tests' => ['ok' => 3, 'ko' => 0], 'changes' => [['id' => 'a', 'title' => 'A']]]);
        self::assertTrue($set->get('a')?->tests?->passed(), 'the set results apply to changes without their own');
    }

    public function testADecisionIsOneOfTwo(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Decision('a', '', 'maybe');
    }
}
