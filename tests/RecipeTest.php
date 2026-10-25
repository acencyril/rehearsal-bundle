<?php

declare(strict_types=1);

namespace Acencyril\RehearsalBundle\Tests;

use Acencyril\RehearsalBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Yaml\Yaml;

final class RecipeTest extends TestCase
{
    private const DIR = __DIR__.'/../recipe';

    public function testTheManifestRegistersTheBundleAndCopiesTheConfig(): void
    {
        $manifest = json_decode((string) file_get_contents(self::DIR.'/manifest.json'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(['all'], $manifest['bundles']['Acencyril\\RehearsalBundle\\RehearsalBundle']);
        self::assertSame('%CONFIG_DIR%/', $manifest['copy-from-recipe']['config/']);
    }

    public function testTheConfigurationIsValidAndSafe(): void
    {
        $yaml = Yaml::parseFile(self::DIR.'/config/packages/rehearsal.yaml');
        $config = (new Processor())->processConfiguration(new Configuration(), [$yaml['rehearsal']]);
        self::assertSame('manual', $config['validation']);
        self::assertSame('ROLE_ADMIN', $config['role']);
        self::assertFalse($config['mailer']['enabled']);
    }

    public function testTheRoutesAreImportedUnderALocalisedPrefix(): void
    {
        self::assertSame(['rehearsal' => [
            'resource' => '@RehearsalBundle/config/routes.php',
            'prefix' => '/{_locale}/admin/rehearsal',
            'trailing_slash_on_root' => false,
            'requirements' => ['_locale' => 'en|fr'],
        ]], Yaml::parseFile(self::DIR.'/config/routes/rehearsal.yaml'));
    }

    public function testRecipeYamlIsIndentedByFourSpaces(): void
    {
        foreach (glob(self::DIR.'/config/*/*.yaml') ?: [] as $file) {
            foreach (file($file, \FILE_IGNORE_NEW_LINES) ?: [] as $n => $line) {
                $indent = \strlen($line) - \strlen(ltrim($line, ' '));
                self::assertSame(0, $indent % 4, basename($file).':'.($n + 1));
                self::assertDoesNotMatchRegularExpression('/^\s*- \S/', $line, basename($file).':'.($n + 1).': a list item goes alone on its line');
            }
        }
    }
}
