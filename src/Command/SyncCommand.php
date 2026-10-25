<?php

declare(strict_types=1);

namespace Acencyril\RehearsalBundle\Command;

use Acencyril\RehearsalBundle\Rehearsal;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('rehearsal:sync', 'Announces new changes (recap email) and, in auto mode, validates those whose tests passed')]
final class SyncCommand extends Command
{
    public function __construct(private readonly Rehearsal $rehearsal)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->rehearsal->sync();
        foreach ($result['new'] as $c) {
            $output->writeln(sprintf('new: %s (%s)', $c->id, $c->title));
        }
        foreach ($result['auto'] as $d) {
            $output->writeln(sprintf('validated automatically: %s', $d->change));
        }
        if ([] === $result['new'] && [] === $result['auto']) {
            $output->writeln('nothing new');
        }

        return Command::SUCCESS;
    }
}
