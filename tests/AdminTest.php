<?php

declare(strict_types=1);

namespace Acencyril\RehearsalBundle\Tests;

use Acencyril\RehearsalBundle\Event\ChangeRejected;
use Acencyril\RehearsalBundle\Event\ChangeValidated;
use Acencyril\RehearsalBundle\Tests\Fixtures\EventLog;

final class AdminTest extends WebTestCase
{
    public function testAnonymousIsAskedToSignIn(): void
    {
        $client = static::createClient();
        $client->request('GET', '/en/admin/rehearsal');
        self::assertResponseStatusCodeSame(401);
    }

    public function testAUserWithoutTheRoleIsRefused(): void
    {
        $client = self::admin([], 'user@example.test');
        $client->request('GET', '/en/admin/rehearsal');
        self::assertResponseStatusCodeSame(403);
        $client->request('POST', '/en/admin/rehearsal/contact-form/decision', ['decision' => 'validate']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame([], self::decisions()['decisions']);
    }

    public function testTheRoleIsConfigurable(): void
    {
        $client = self::admin(['role' => 'ROLE_USER'], 'user@example.test');
        $client->request('GET', '/en/admin/rehearsal');
        self::assertResponseIsSuccessful();
    }

    public function testTheChangesAreListedWithNotesAndTests(): void
    {
        $client = self::admin();
        $crawler = $client->request('GET', '/en/admin/rehearsal');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex');
        self::assertSelectorTextContains('h1', 'Rehearsal');
        self::assertSelectorTextContains('h2', 'November maintenance');
        self::assertCount(3, $crawler->filter('article.rh-change'));
        $first = $crawler->filter('#change-uplift-2026-11-02');
        self::assertStringContainsString('Tests passed', $first->text());
        self::assertStringContainsString('Proposed, not applied: doctrine/orm', $first->text());
        self::assertSame(1, $first->filter('br')->count(), 'line breaks of the notes kept, escaped');
        self::assertSame('/en', $first->filter('a[target=_blank]')->attr('href'));
        self::assertStringContainsString('ContactTest::testSend', $crawler->filter('#change-contact-form')->text());
        self::assertSame(0, $crawler->filter('#change-contact-form a[target=_blank]')->count(), 'no link out of the site');
        self::assertSame(0, $crawler->filter('a[href^="javascript"]')->count());
        self::assertSame('https://github.com/example/site/pull/12', $crawler->filter('.rh-head a')->attr('href'));
        self::assertSelectorTextContains('.rh-head .rh-pill', 'Manual validation');
    }

    public function testTheSummaryCardIsShownOnlyWithASummary(): void
    {
        $client = self::admin();
        $crawler = $client->request('GET', '/en/admin/rehearsal');

        self::assertCount(1, $crawler->filter('.rh-sum'), 'only the change carrying a summary has a card');
        $card = $crawler->filter('#change-uplift-2026-11-02 .rh-sum');
        self::assertSame('Upgrade summary', trim($card->filter('h4')->text()));
        self::assertSame($card->filter('h4')->attr('id'), $card->attr('aria-labelledby'));
        self::assertSame('7.3.11 → to 7.4.21', preg_replace('/\s+/', ' ', trim($card->filter('.rh-sum-move')->text())));
        $text = preg_replace('/\s+/', ' ', $card->text());
        foreach ([
            'Symfony, 40 packages moved, 11 constraints rewritten in composer.json',
            'Then 8.1.9 in later runs',
            'passed after',
            'RenameAttributeRector',
            '1 file rewritten',
            'Name commands with #[AsCommand] applied, tests passed · 2 places',
            'doctrine/doctrine-bundle 3.3.3: requires PHP ^8.4',
            'phpunit/phpunit: then 13.4.3',
            'Waiting for a decision',
        ] as $expected) {
            self::assertStringContainsString($expected, $text);
        }
        self::assertStringNotContainsString('before', $text, 'tests before were not measured: not shown');
        self::assertStringNotContainsString('PHP 8', $text, 'PHP did not move');
        self::assertSame(['Project tests', 'Deprecated code fixed', 'New features', 'Refused by the policy', 'The way ahead', 'Decision'], $card->filter('h5')->each(static fn ($h) => $h->text()));
    }

    public function testTheSummaryCardFollowsTheDecision(): void
    {
        $client = self::admin();
        $crawler = $client->request('GET', '/en/admin/rehearsal');
        $client->submit($crawler->filter('#change-uplift-2026-11-02')->selectButton('Validate')->form());
        $crawler = $client->followRedirect();

        self::assertStringContainsString('Validated, decision recorded', $crawler->filter('#change-uplift-2026-11-02 .rh-sum')->text());
    }

    public function testTheSummaryCardInFrench(): void
    {
        $client = self::admin();
        $crawler = $client->request('GET', '/fr/admin/rehearsal');
        $text = preg_replace('/\s+/', ' ', $crawler->filter('.rh-sum')->text());

        self::assertStringContainsString('Résumé de la montée de version', $text);
        self::assertStringContainsString('40 paquets montés, 11 contraintes réécrites dans composer.json', $text);
        self::assertStringContainsString('Puis 8.1.9 aux prochains passages', $text);
        self::assertStringContainsString('verts après', $text);
        self::assertStringContainsString('Refusé par la politique', $text);
    }

    public function testTheSectionFollowsTheHostThroughRehearsalVariables(): void
    {
        $client = self::admin();
        $client->request('GET', '/en/admin/rehearsal');
        $html = (string) $client->getResponse()->getContent();
        preg_match('#<div class="rh" id="rehearsal">.*?<style>(.*?)</style>#s', $html, $m);
        self::assertStringNotContainsString('<main class="rh"', $html, 'the host layout owns <main>');
        $css = $m[1] ?? '';

        foreach (['--rehearsal-ink', '--rehearsal-muted', '--rehearsal-line', '--rehearsal-card', '--rehearsal-accent', '--rehearsal-ok', '--rehearsal-ko', '--rehearsal-wait', '--rehearsal-font', '--rehearsal-max-width'] as $token) {
            self::assertStringContainsString('var('.$token.',', $css);
            self::assertStringContainsString('`'.$token.'`', (string) file_get_contents(\dirname(__DIR__).'/README.md'), $token.' is documented');
        }
        // The defaults come from the page's text colour, never from the visitor's system theme:
        // a light-only or dark-only admin must not flip to the other palette.
        self::assertStringContainsString('currentColor', $css);
        self::assertStringNotContainsString('prefers-color-scheme', $css);
        self::assertMatchesRegularExpression('/\.rh-btn\.is-validate \{ background: var\(--rh-ok-solid\); color: #fff; \}/', $css);
    }

    public function testTheSummaryCardIsThemedByVariables(): void
    {
        $client = self::admin();
        $client->request('GET', '/en/admin/rehearsal');
        $html = (string) $client->getResponse()->getContent();

        foreach (['--rh-sum-bg', '--rh-sum-tile', '--rh-sum-ink', '--rh-sum-ok', '--rh-sum-ko', ':root[data-theme="dark"] .rh'] as $hook) {
            self::assertStringContainsString($hook, $html);
        }
        preg_match_all('/\.rh-sum[^{]*\{[^}]*\}/', $html, $rules);
        self::assertNotEmpty($rules[0]);
        foreach ($rules[0] as $rule) {
            self::assertDoesNotMatchRegularExpression('/(?<![-\w])(color|background):\s*#/', $rule, 'card colours come from variables: '.$rule);
        }
    }

    public function testValidateWritesTheDecisionAndDispatchesTheEvent(): void
    {
        $client = self::admin();
        $crawler = $client->request('GET', '/en/admin/rehearsal');
        $form = $crawler->filter('#change-uplift-2026-11-02')->selectButton('Validate')->form(['comment' => 'Checked the home page.']);
        $client->submit($form);

        self::assertResponseRedirects('/en/admin/rehearsal#change-uplift-2026-11-02');
        $d = self::decisions()['decisions'][0];
        self::assertSame(['uplift-2026-11-02', 'abc1234', 'validated', 'Checked the home page.', 'admin@example.test', false], [$d['change'], $d['revision'], $d['decision'], $d['comment'], $d['by'], $d['auto']]);
        $events = self::getContainer()->get(EventLog::class)->events;
        $validated = array_values(array_filter($events, static fn ($e) => $e instanceof ChangeValidated));
        self::assertCount(1, $validated);
        self::assertSame('Checked the home page.', $validated[0]->decision->comment);

        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('.rh-flash', 'Validated. The decision is recorded.');
        self::assertStringContainsString('by admin@example.test', $crawler->filter('#change-uplift-2026-11-02 .rh-decision')->text());
        self::assertSelectorTextContains('.rh-history', 'Checked the home page.');
    }

    public function testRejectWithoutACommentIsRefused(): void
    {
        $client = self::admin();
        $crawler = $client->request('GET', '/en/admin/rehearsal');
        $client->submit($crawler->filter('#change-contact-form')->selectButton('Reject')->form());

        self::assertSame([], self::decisions()['decisions']);
        $client->followRedirect();
        self::assertSelectorTextContains('.rh-flash', 'a rejection needs a comment');
    }

    public function testRejectWithAComment(): void
    {
        $client = self::admin();
        $crawler = $client->request('GET', '/en/admin/rehearsal');
        $client->submit($crawler->filter('#change-contact-form')->selectButton('Reject')->form(['comment' => 'The phone field is gone.']));

        $d = self::decisions()['decisions'][0];
        self::assertSame(['contact-form', 'rejected', 'The phone field is gone.'], [$d['change'], $d['decision'], $d['comment']]);
        self::assertCount(1, array_filter(self::getContainer()->get(EventLog::class)->events, static fn ($e) => $e instanceof ChangeRejected));
    }

    public function testAForgedPostIsRefused(): void
    {
        $client = self::admin();
        $client->request('POST', '/en/admin/rehearsal/contact-form/decision', ['decision' => 'validate', '_token' => 'forged']);
        self::assertResponseRedirects('/en/admin/rehearsal');
        self::assertSame([], self::decisions()['decisions']);
        $client->followRedirect();
        self::assertSelectorTextContains('.rh-flash', 'The form expired');
    }

    public function testInFrench(): void
    {
        $client = self::admin();
        $client->request('GET', '/fr/admin/rehearsal');
        self::assertSelectorTextContains('h1', 'Essais avant production');
        self::assertSelectorExists('button[value=validate]:contains("Valider")');
        self::assertSelectorTextContains('#change-uplift-2026-11-02', 'Tests réussis');
    }

    public function testTheSiteLayoutIsTheParent(): void
    {
        $client = self::admin(['parent_template' => 'admin.html.twig']);
        $client->request('GET', '/en/admin/rehearsal');
        self::assertSelectorTextContains('nav', 'Site admin');
        self::assertSelectorTextContains('title', 'Rehearsal · Site admin');
    }

    public function testAutoValidationWhenThePageIsOpened(): void
    {
        $client = self::admin(['validation' => 'auto']);
        $crawler = $client->request('GET', '/en/admin/rehearsal');

        self::assertSelectorTextContains('.rh-head .rh-pill', 'Automatic validation');
        self::assertStringContainsString('automatically, tests passed', $crawler->filter('#change-uplift-2026-11-02 .rh-decision')->text());
        self::assertSame('To decide', $crawler->filter('#change-contact-form .rh-pill.is-pending')->text(), 'failed tests wait for a person');
        self::assertSame(['uplift-2026-11-02'], array_column(self::decisions()['decisions'], 'change'));
    }

    public function testRecapEmailsWhenEnabled(): void
    {
        $client = self::admin(['mailer' => ['enabled' => true, 'from' => 'site@example.test', 'to' => ['owner@example.test'], 'locale' => 'fr']]);
        $client->request('GET', '/en/admin/rehearsal');
        self::assertEmailCount(1);
        $mail = self::getMailerMessage();
        self::assertEmailHeaderSame($mail, 'To', 'owner@example.test');
        self::assertEmailHeaderSame($mail, 'Subject', '[Rehearsal] 3 changement(s) à essayer');
        self::assertEmailHtmlBodyContains($mail, 'http://localhost/fr/admin/rehearsal');
        self::assertEmailTextBodyContains($mail, 'Shorter contact form');

        $crawler = $client->request('GET', '/en/admin/rehearsal');
        self::assertEmailCount(0, null, 'announced once');
        $client->submit($crawler->filter('#change-contact-form')->selectButton('Reject')->form(['comment' => 'Not yet.']));
        self::assertEmailCount(1);
        self::assertEmailHeaderSame(self::getMailerMessage(), 'Subject', '[Rehearsal] Refusé : Shorter contact form');
        self::assertEmailHtmlBodyContains(self::getMailerMessage(), 'Not yet.');
    }

    public function testNoEmailByDefault(): void
    {
        $client = self::admin();
        $client->request('GET', '/en/admin/rehearsal');
        self::assertEmailCount(0);
    }

    public function testNoChangesFile(): void
    {
        unlink(self::dir().'/changes.json');
        $client = self::admin();
        $client->request('GET', '/en/admin/rehearsal');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.rh-card', 'No change to try right now.');
    }
}
