<?php

declare(strict_types=1);

namespace Acencyril\RehearsalBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class CommandTest extends WebTestCase
{
    /** @param array<string, mixed> $rehearsal */
    private static function command(string $name, array $rehearsal = []): CommandTester
    {
        self::ensureKernelShutdown();

        return new CommandTester((new Application(self::bootKernel(['rehearsal' => $rehearsal])))->find($name));
    }

    public function testSyncAnnouncesThenSaysNothingNew(): void
    {
        $tester = self::command('rehearsal:sync', ['validation' => 'auto']);
        $tester->execute([]);
        self::assertStringContainsString('new: contact-form (Shorter contact form)', $tester->getDisplay());
        self::assertStringContainsString('validated automatically: uplift-2026-11-02', $tester->getDisplay());
        $tester->execute([]);
        self::assertSame("nothing new\n", $tester->getDisplay());
    }

    public function testStatusForPeopleAndForCi(): void
    {
        self::command('rehearsal:sync', ['validation' => 'auto'])->execute([]);
        $tester = self::command('rehearsal:status', ['validation' => 'auto']);
        $tester->execute([]);
        self::assertStringContainsString('November maintenance: 2 pending, 1 validated, 0 rejected (validation: auto)', $tester->getDisplay());

        $tester->execute(['--json' => true]);
        $data = json_decode($tester->getDisplay(), true);
        self::assertSame(['pending' => 2, 'validated' => 1, 'rejected' => 0], $data['counts']);
        self::assertFalse($data['all_validated']);
        self::assertSame('validated', $data['changes'][0]['state']);
        self::assertTrue($data['changes'][0]['decision']['auto']);
        self::assertSame('passed', $data['changes'][0]['tests']['status']);
    }
}
