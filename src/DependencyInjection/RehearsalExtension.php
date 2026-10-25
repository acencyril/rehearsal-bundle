<?php

declare(strict_types=1);

namespace Acencyril\RehearsalBundle\DependencyInjection;

use Acencyril\RehearsalBundle\Controller\RehearsalController;
use Acencyril\RehearsalBundle\Mail\Notifier;
use Acencyril\RehearsalBundle\Rehearsal;
use Acencyril\RehearsalBundle\Storage\ChangesReader;
use Acencyril\RehearsalBundle\Storage\DecisionStore;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class RehearsalExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);
        (new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 2).'/config')))->load('services.php');

        $container->getDefinition(ChangesReader::class)->setArgument(0, $config['changes_file']);
        $container->getDefinition(DecisionStore::class)->setArgument(0, $config['decisions_file']);
        $container->getDefinition(Rehearsal::class)->setArgument(4, $config['validation']);
        $container->getDefinition(Notifier::class)->setArgument(4, $config['mailer']);
        $container->getDefinition(RehearsalController::class)->setArgument(6, $config['role'])->setArgument(7, $config['parent_template']);
    }

    public function getAlias(): string
    {
        return 'rehearsal';
    }
}
