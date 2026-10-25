<?php

declare(strict_types=1);

namespace Acencyril\RehearsalBundle\Mail;

use Acencyril\RehearsalBundle\Model\Change;
use Acencyril\RehearsalBundle\Model\ChangeSet;
use Acencyril\RehearsalBundle\Model\Decision;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * The optional recap emails: new changes to try, and each decision. Off unless
 * rehearsal.mailer.enabled is true, the mailer exists and someone is to receive them.
 */
final class Notifier
{
    /** @param array{enabled: bool, from: ?string, to: list<string>, subject_prefix: string, locale: string} $config */
    public function __construct(
        private readonly ?MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly TranslatorInterface $translator,
        private readonly ?UrlGeneratorInterface $urls,
        private readonly array $config,
    ) {
    }

    public function enabled(): bool
    {
        return $this->config['enabled'] && null !== $this->mailer && [] !== $this->config['to'];
    }

    /**
     * @param list<Change>   $changes
     * @param list<Decision> $auto    the automatic validations of this round
     */
    public function proposed(array $changes, ChangeSet $set, array $auto = []): void
    {
        if (!$this->enabled() || [] === $changes) {
            return;
        }
        $subject = $this->translator->trans('mail.proposed.subject', ['%count%' => \count($changes), '%title%' => $set->title], 'rehearsal', $this->config['locale']);
        $this->send($subject, '@Rehearsal/email/proposed.html.twig', ['changes' => $changes, 'set' => $set, 'auto' => array_map(static fn (Decision $d) => $d->change, $auto)]);
    }

    public function decided(Change $change, Decision $decision): void
    {
        if (!$this->enabled()) {
            return;
        }
        $subject = $this->translator->trans('mail.decided.subject.'.$decision->decision, ['%title%' => $change->title], 'rehearsal', $this->config['locale']);
        $this->send($subject, '@Rehearsal/email/decided.html.twig', ['change' => $change, 'decision' => $decision]);
    }

    /** @param array<string, mixed> $vars */
    private function send(string $subject, string $template, array $vars): void
    {
        $html = $this->twig->render($template, $vars + ['locale' => $this->config['locale'], 'admin_url' => $this->adminUrl()]);
        $email = (new Email())
            ->subject(trim($this->config['subject_prefix'].' '.$subject))
            ->to(...array_map(static fn (string $a) => new Address($a), $this->config['to']))
            ->html($html)
            ->text(trim(html_entity_decode(strip_tags(preg_replace('#<(br|/p|/li|/h\d)>#i', "\n", $html) ?? $html), \ENT_QUOTES)));
        if (null !== $this->config['from']) {
            $email->from(new Address($this->config['from']));
        }
        $this->mailer?->send($email);
    }

    private function adminUrl(): string
    {
        if (null === $this->urls) {
            return '';
        }
        $params = [];
        if ($this->urls instanceof RouterInterface && str_contains((string) $this->urls->getRouteCollection()->get('rehearsal_index')?->getPath(), '{_locale}')) {
            $params['_locale'] = $this->config['locale'];
        }
        try {
            return $this->urls->generate('rehearsal_index', $params, UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (\Throwable) {
            return '';
        }
    }
}
