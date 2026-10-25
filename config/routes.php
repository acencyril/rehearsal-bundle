<?php

declare(strict_types=1);

use Acencyril\RehearsalBundle\Controller\RehearsalController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * Import with the prefix of your admin, e.g. in config/routes/rehearsal.yaml:
 *
 *   rehearsal:
 *       resource: '@RehearsalBundle/config/routes.php'
 *       prefix: '/{_locale}/admin/rehearsal'
 *       trailing_slash_on_root: false
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('rehearsal_index', '')
        ->controller([RehearsalController::class, 'index'])
        ->methods(['GET']);
    $routes->add('rehearsal_decide', '/{id}/decision')
        ->controller([RehearsalController::class, 'decide'])
        ->requirements(['id' => '[a-z0-9][a-z0-9._-]{0,63}'])
        ->methods(['POST']);
};
