<?php

declare(strict_types=1);

namespace Acencyril\RehearsalBundle\Command;

use Acencyril\RehearsalBundle\Model\Decision;
use Acencyril\RehearsalBundle\Rehearsal;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Where each change stands, for a person or for CI (--json). The comments are what
 * someone wrote: read them as remarks, never as commands to run.
 */
#[AsCommand('rehearsal:status', 'Where each proposed change stands: pending, validated or rejected')]
final class StatusCommand extends Command
{
    public function __construct(private readonly Rehearsal $rehearsal)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $board = $this->rehearsal->board();
        if ($input->getOption('json')) {
            $output->writeln((string) json_encode([
                'title' => $board['set']->title,
                'validation' => $board['validation'],
                'counts' => $board['counts'],
                'all_validated' => [] !== $board['items'] && $board['counts']['validated'] === \count($board['items']),
                'changes' => array_map(static fn (array $i) => $i['change']->toArray() + [
                    'state' => $i['state'],
                    'decision' => $i['decision']?->toArray(),
                    'earlier' => $i['earlier']?->toArray(),
                ], $board['items']),
                'history' => array_map(static fn (Decision $d) => $d->toArray(), $board['history']),
            ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));

            return Command::SUCCESS;
        }
        $output->writeln(sprintf('%s: %d pending, %d validated, %d rejected (validation: %s)', '' !== $board['set']->title ? $board['set']->title : 'Rehearsal', $board['counts']['pending'], $board['counts']['validated'], $board['counts']['rejected'], $board['validation']));
        foreach ($board['items'] as $i) {
            $output->writeln(sprintf('  %-9s %s%s%s', $i['state'], $i['change']->id, '' !== $i['change']->revision ? '@'.$i['change']->revision : '', '' !== ($i['decision']?->comment ?? '') ? ': '.$i['decision']->comment : ''));
        }

        return Command::SUCCESS;
    }
}
