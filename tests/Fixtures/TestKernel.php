<?php

declare(strict_types=1);

namespace Acencyril\RehearsalBundle\Tests\Fixtures;

use Acencyril\RehearsalBundle\RehearsalBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * A small admin: Framework, Twig, Security (an admin and a plain user, HTTP basic),
 * Rehearsal under /{_locale}/admin/rehearsal, a null mailer, and EventLog listening.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /** @param array<string, mixed> $rehearsal */
    public function __construct(string $environment, bool $debug, private readonly array $rehearsal = [])
    {
        parent::__construct($environment, $debug);
    }

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new TwigBundle(), new SecurityBundle(), new RehearsalBundle()];
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return \dirname(__DIR__, 2).'/var/cache/'.$this->environment.'-'.md5(serialize($this->rehearsal));
    }

    public function getLogDir(): string
    {
        return \dirname(__DIR__, 2).'/var/log';
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->loadFromExtension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'csrf_protection' => true,
            'mailer' => ['dsn' => 'null://null'],
            'translator' => ['fallbacks' => ['en']],
            'default_locale' => 'en',
            'router' => ['utf8' => true],
        ]);
        $container->loadFromExtension('twig', ['default_path' => __DIR__.'/templates', 'strict_variables' => true]);
        $container->loadFromExtension('security', [
            'password_hashers' => ['Symfony\Component\Security\Core\User\InMemoryUser' => 'plaintext'],
            'providers' => ['memory' => ['memory' => ['users' => [
                'admin@example.test' => ['password' => 'admin', 'roles' => ['ROLE_ADMIN']],
                'user@example.test' => ['password' => 'user', 'roles' => ['ROLE_USER']],
            ]]]],
            'firewalls' => ['main' => ['lazy' => true, 'provider' => 'memory', 'http_basic' => null]],
        ]);
        $container->register('logger', NullLogger::class);
        $container->register(EventLog::class)->addTag('kernel.event_subscriber')->setPublic(true);

        $dir = sys_get_temp_dir().'/rehearsal-test';
        $container->loadFromExtension('rehearsal', $this->rehearsal + [
            'changes_file' => $dir.'/changes.json',
            'decisions_file' => $dir.'/decisions.json',
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@RehearsalBundle/config/routes.php')->prefix('/{_locale}/admin/rehearsal', false)->requirements(['_locale' => 'en|fr']);
    }
}
