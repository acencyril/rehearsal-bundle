<?php

declare(strict_types=1);

namespace Acencyril\RehearsalBundle\Tests;

use Acencyril\RehearsalBundle\Tests\Fixtures\TestKernel;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase as BaseWebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class WebTestCase extends BaseWebTestCase
{
    protected const DIR = '/rehearsal-test';

    protected static function dir(): string
    {
        return sys_get_temp_dir().self::DIR;
    }

    protected function setUp(): void
    {
        exec('rm -rf '.escapeshellarg(self::dir()));
        mkdir(self::dir());
        copy(__DIR__.'/Fixtures/changes.json', self::dir().'/changes.json');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        exec('rm -rf '.escapeshellarg(self::dir()));
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true, $options['rehearsal'] ?? []);
    }

    /** @param array<string, mixed> $rehearsal */
    protected static function admin(array $rehearsal = [], string $user = 'admin@example.test'): KernelBrowser
    {
        self::ensureKernelShutdown();
        $client = static::createClient(['rehearsal' => $rehearsal]);
        $client->loginUser(self::getContainer()->get('security.user.provider.concrete.memory')->loadUserByIdentifier($user));

        return $client;
    }

    /** @return array<string, mixed> */
    protected static function decisions(): array
    {
        $file = self::dir().'/decisions.json';

        return is_file($file) ? json_decode((string) file_get_contents($file), true) : ['decisions' => []];
    }
}
