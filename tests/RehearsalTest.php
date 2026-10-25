<?php

declare(strict_types=1);

namespace Acencyril\RehearsalBundle\Tests;

use Acencyril\RehearsalBundle\Event\ChangeRejected;
use Acencyril\RehearsalBundle\Event\ChangesProposed;
use Acencyril\RehearsalBundle\Event\ChangeValidated;
use Acencyril\RehearsalBundle\Mail\Notifier;
use Acencyril\RehearsalBundle\Model\Decision;
use Acencyril\RehearsalBundle\Rehearsal;
use Acencyril\RehearsalBundle\Storage\ChangesReader;
use Acencyril\RehearsalBundle\Storage\DecisionStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class RehearsalTest extends TestCase
{
    private string $dir;
    /** @var list<object> */
    private array $events = [];

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/rehearsal-unit-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
        copy(__DIR__.'/Fixtures/changes.json', $this->dir.'/changes.json');
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->dir));
    }

    private function rehearsal(string $validation = 'manual'): Rehearsal
    {
        $dispatcher = new EventDispatcher();
        foreach ([ChangeValidated::class, ChangeRejected::class, ChangesProposed::class] as $e) {
            $dispatcher->addListener($e, function (object $event): void { $this->events[] = $event; });
        }
        $notifier = new Notifier(null, new Environment(new ArrayLoader()), new Translator('en'), null, ['enabled' => true, 'from' => null, 'to' => ['a@example.test'], 'subject_prefix' => '', 'locale' => 'en']);

        return new Rehearsal(new ChangesReader($this->dir.'/changes.json'), new DecisionStore($this->dir.'/var/decisions.json'), $dispatcher, $notifier, $validation);
    }

    public function testEverythingStartsPending(): void
    {
        $board = $this->rehearsal()->board();
        self::assertSame(['pending' => 3, 'validated' => 0, 'rejected' => 0], $board['counts']);
        self::assertSame([], $board['history']);
    }

    public function testValidateRecordsAndDispatches(): void
    {
        $r = $this->rehearsal();
        $d = $r->decide('uplift-2026-11-02', Decision::VALIDATED, ' Looks good ', 'admin@example.test');

        self::assertSame('Looks good', $d->comment);
        self::assertSame('abc1234', $d->revision);
        self::assertSame('validated', $r->board()['items'][0]['state']);
        self::assertCount(1, $this->events);
        self::assertInstanceOf(ChangeValidated::class, $this->events[0]);
        self::assertSame('uplift-2026-11-02', $this->events[0]->change->id);
        self::assertSame('November maintenance', $this->events[0]->set->title);
        $file = json_decode((string) file_get_contents($this->dir.'/var/decisions.json'), true);
        self::assertSame('admin@example.test', $file['decisions'][0]['by']);
    }

    public function testRejectNeedsAComment(): void
    {
        $r = $this->rehearsal();
        try {
            $r->decide('contact-form', Decision::REJECTED, '  ');
            self::fail('no comment');
        } catch (\DomainException $e) {
            self::assertSame('error.comment_required', $e->getMessage());
        }
        $r->decide('contact-form', Decision::REJECTED, 'The phone field is gone.');
        self::assertSame('rejected', $r->board()['items'][1]['state']);
        self::assertInstanceOf(ChangeRejected::class, $this->events[0]);
    }

    public function testAnUnknownChangeIsRefused(): void
    {
        $this->expectExceptionMessage('error.unknown');
        $this->rehearsal()->decide('nope', Decision::VALIDATED);
    }

    public function testANewRevisionIsPendingAgainWithTheEarlierDecisionShown(): void
    {
        $r = $this->rehearsal();
        $r->decide('contact-form', Decision::REJECTED, 'The phone field is gone.');
        $data = json_decode((string) file_get_contents($this->dir.'/changes.json'), true);
        $data['changes'][1]['revision'] = 'def5678';
        file_put_contents($this->dir.'/changes.json', json_encode($data));

        $item = $r->board()['items'][1];
        self::assertSame('pending', $item['state']);
        self::assertSame('rejected', $item['earlier']?->decision);
        self::assertSame('The phone field is gone.', $item['earlier']?->comment);
    }

    public function testSyncAnnouncesEachRevisionOnce(): void
    {
        $r = $this->rehearsal();
        self::assertCount(3, $r->sync()['new']);
        self::assertSame([], $r->sync()['new']);
        self::assertInstanceOf(ChangesProposed::class, $this->events[0]);
        self::assertCount(1, $this->events);

        $data = json_decode((string) file_get_contents($this->dir.'/changes.json'), true);
        $data['changes'][2]['revision'] = 'def5678';
        file_put_contents($this->dir.'/changes.json', json_encode($data));
        self::assertSame(['legal-pages'], array_map(static fn ($c) => $c->id, $r->sync()['new']));
    }

    public function testManualNeverValidatesOnItsOwn(): void
    {
        $r = $this->rehearsal('manual');
        self::assertSame([], $r->sync()['auto']);
        self::assertSame(3, $r->board()['counts']['pending']);
    }

    public function testAutoValidatesOnlyWhatPassedItsTests(): void
    {
        $r = $this->rehearsal('auto');
        $auto = $r->sync()['auto'];

        self::assertSame(['uplift-2026-11-02'], array_map(static fn (Decision $d) => $d->change, $auto));
        self::assertTrue($auto[0]->auto);
        self::assertSame('auto', $auto[0]->by);
        self::assertSame(['pending' => 2, 'validated' => 1, 'rejected' => 0], $r->board()['counts']);
        self::assertSame([], $r->sync()['auto'], 'once');
        self::assertInstanceOf(ChangeValidated::class, $this->events[0]);
    }

    public function testAnUnknownValidationModeIsAnError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->rehearsal('sometimes');
    }

    public function testTheStoreSurvivesABrokenFile(): void
    {
        mkdir($this->dir.'/var');
        file_put_contents($this->dir.'/var/decisions.json', '{not json');
        $store = new DecisionStore($this->dir.'/var/decisions.json');
        self::assertSame([], $store->all());
        $store->add(new Decision('a', 'r1', Decision::VALIDATED));
        $store->add(new Decision('a', 'r2', Decision::REJECTED, 'no'));
        self::assertSame('r2', $store->latest('a')?->revision);
        self::assertSame(['x@1'], $store->markSeen(['x@1']));
        self::assertSame(['y@1'], $store->markSeen(['x@1', 'y@1']));
        self::assertCount(2, $store->all(), 'decisions kept when marking seen');
    }
}
