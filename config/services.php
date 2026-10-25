<?php

declare(strict_types=1);

use Acencyril\RehearsalBundle\Command\StatusCommand;
use Acencyril\RehearsalBundle\Command\SyncCommand;
use Acencyril\RehearsalBundle\Controller\RehearsalController;
use Acencyril\RehearsalBundle\Mail\Notifier;
use Acencyril\RehearsalBundle\Rehearsal;
use Acencyril\RehearsalBundle\Storage\ChangesReader;
use Acencyril\RehearsalBundle\Storage\DecisionStore;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $c): void {
    $services = $c->services()->defaults()->autoconfigure(false)->autowire(false);

    // Paths, validation, mailer and role are set by RehearsalExtension.
    $services->set(ChangesReader::class)->args(['']);
    $services->set(DecisionStore::class)->args(['']);

    $services->set(Notifier::class)->args([
        service('mailer')->nullOnInvalid(),
        service('twig'),
        service('translator'),
        service('router')->nullOnInvalid(),
        [],
    ]);

    $services->set(Rehearsal::class)->args([
        service(ChangesReader::class),
        service(DecisionStore::class),
        service('event_dispatcher')->nullOnInvalid(),
        service(Notifier::class),
        'manual',
        service('clock')->nullOnInvalid(),
    ])->public();
    $services->alias('rehearsal', Rehearsal::class)->public();

    $services->set(RehearsalController::class)->args([
        service(Rehearsal::class),
        service('twig'),
        service('security.authorization_checker'),
        service('security.csrf.token_manager'),
        service('router'),
        service('security.token_storage')->nullOnInvalid(),
        'ROLE_ADMIN',
        '@Rehearsal/base.html.twig',
    ])->public();

    $services->set(SyncCommand::class)->args([service(Rehearsal::class)])->tag('console.command', ['command' => 'rehearsal:sync']);
    $services->set(StatusCommand::class)->args([service(Rehearsal::class)])->tag('console.command', ['command' => 'rehearsal:status']);
};
