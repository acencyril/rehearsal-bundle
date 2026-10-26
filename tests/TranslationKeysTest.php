<?php

declare(strict_types=1);

namespace Acencyril\RehearsalBundle\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class TranslationKeysTest extends TestCase
{
    /** @return list<string> */
    private static function keys(string $locale): array
    {
        $flat = [];
        $walk = static function (array $node, string $prefix) use (&$walk, &$flat): void {
            foreach ($node as $k => $v) {
                \is_array($v) ? $walk($v, $prefix.$k.'.') : $flat[] = $prefix.$k;
            }
        };
        $walk(Yaml::parseFile(__DIR__.'/../translations/rehearsal.'.$locale.'.yaml'), '');
        sort($flat);

        return $flat;
    }

    public function testFrenchAndEnglishHaveTheSameKeys(): void
    {
        self::assertSame(self::keys('en'), self::keys('fr'));
    }

    public function testEveryKeyUsedExists(): void
    {
        $keys = self::keys('en');
        $used = [];
        foreach (array_merge(glob(__DIR__.'/../templates/*.twig') ?: [], glob(__DIR__.'/../templates/*/*.twig') ?: []) as $file) {
            preg_match_all("/'([a-z_]+(?:\\.[a-z_]+)+)'\\|trans/", (string) file_get_contents($file), $m);
            array_push($used, ...$m[1]);
        }
        foreach (glob(__DIR__.'/../src/{,*/}*.php', \GLOB_BRACE) ?: [] as $file) {
            preg_match_all("/'((?:error|flash|mail)\\.[a-z_.]+[a-z])'/", (string) file_get_contents($file), $m);
            array_push($used, ...$m[1]);
        }
        // keys built from a prefix and a state
        foreach (['state.', 'tests.', 'flash.', 'mail.decided.subject.', 'mail.decided.title.'] as $prefix) {
            foreach (['pending', 'validated', 'rejected', 'passed', 'failed', 'not_run'] as $s) {
                if (\in_array($prefix.$s, $keys, true)) {
                    $used[] = $prefix.$s;
                }
            }
        }
        foreach (['summary.status.' => ['passed', 'failed', 'not_run'], 'summary.state.' => ['pending', 'validated', 'rejected'], 'summary.adoption.' => \Acencyril\RehearsalBundle\Model\Summary::ADOPTION_STATUSES, 'summary.tests_' => ['before', 'after']] as $prefix => $suffixes) {
            foreach ($suffixes as $suffix) {
                $used[] = $prefix.$suffix;
            }
        }
        array_push($used, 'validation.manual', 'validation.auto', 'validation.manual_help', 'validation.auto_help', 'decision.auto', 'decision.by');
        self::assertNotEmpty($used);
        self::assertSame([], array_values(array_diff(array_unique($used), $keys)));
    }
}
