# Rehearsal

**Try a change in your Symfony admin before production.**

Rehearsal adds a section to your admin that lists the changes proposed for the site
(a dependency upgrade, a fix, a new page), each with its notes, what to check and its
test results. For each one: **Validate** (with an optional comment) or **Reject with a
comment**. A validation dispatches an event your application hooks to merge and deploy;
Rehearsal itself only records the decision.

It is meant for a copy of the site built from a branch (a staging or sandbox
instance): you open its admin, try the change for real, and decide there.

*Documentation française : [README.fr.md](README.fr.md)*

```bash
composer require acencyril/rehearsal-bundle
```

---

## How it works

1. Something writes the proposed changes to `.rehearsal/changes.json` on the branch:
   a person, a script, or [Uplift](https://github.com/acencyril/uplift-bundle)
   (`uplift:apply --rehearsal=.rehearsal/changes.json`).
2. The branch is deployed to a staging copy. `rehearsal:sync` (or the first visit of
   the section) notices the new changes, sends the recap email if enabled and, in
   `auto` mode, validates those whose tests passed.
3. You open the section, try each change and decide. Each decision is written to the
   decisions file and dispatches `ChangeValidated` or `ChangeRejected`.
4. Your listener, or CI reading `rehearsal:status --json`, does the rest: merge,
   deploy, tag, or send the rejection back to whoever proposed the change.

A decision holds for one **revision** of a change. When the change comes back with a
new revision (fixed after a rejection, or tested again), it is pending again, with the
earlier decision and its comment shown.

## The changes file

```json
{
    "title": "November maintenance",
    "summary": "Dependency upgrades and one fix.",
    "link": "https://github.com/example/site/pull/12",
    "revision": "abc1234",
    "tests": {"status": "passed", "summary": "355 journeys"},
    "changes": [
        {
            "id": "contact-form",
            "title": "Shorter contact form",
            "detail": "Two fields instead of five.",
            "replaced": "The five-field form",
            "check": "Send a message from the contact page.",
            "url": "/en/contact",
            "source": "manual",
            "revision": "abc1234",
            "tests": {"status": "failed", "summary": "1 failure", "failures": ["ContactTest::testSend"], "output": "…"}
        }
    ]
}
```

| Field | |
|---|---|
| `id` | `a-z 0-9 . _ -`, 64 at most, the same across revisions of a change |
| `title`, `detail`, `replaced`, `check` | text; line breaks are kept, HTML is escaped |
| `url` | a path of this site to open; anything else (another site, `javascript:`) is dropped |
| `source` | who proposed it (`uplift`, `manual`…) |
| `revision` | the code the change was tested on; defaults to the file's `revision` |
| `tests` | `status`: `passed`, `failed` or `not_run`; `summary`, `failures`, `output`; defaults to the file's `tests` |
| `summary` | optional facts of an upgrade, shown as a card (below) |
| `link` (file) | where to read more, `https://` only (`pr` is read too) |

Entries without a valid `id` or a `title` are ignored; a missing or broken file is an
empty list. The older test form `{"ok": 350, "ko": 2, "failures": [...]}` (automatic
journeys) is read as well.

### The summary card (optional)

A change may carry a `summary`: the facts of an upgrade. Rehearsal then shows a card
at the top of the change, with the main move in large type, the counts, the tests, the
deprecations fixed, the new features adopted, what the policy refused and the way
ahead, and the decision state. Without `summary` the change looks as before.
[Uplift](https://github.com/acencyril/uplift-bundle) fills it; any tool can. Every field
is optional: leave out what was not measured, the card shows only what is there.

```json
"summary": {
    "main": {"package": "symfony/*", "label": "Symfony", "from": "7.3.11", "to": "7.4.21"},
    "php": {"from": "8.2", "to": "8.3"},
    "packages_moved": 40,
    "constraints_rewritten": 11,
    "tests": {"before": "passed", "after": "passed"},
    "deprecations_fixed": {"rules": ["RenameAttributeRector"], "files": ["src/Controller/HomeController.php"]},
    "adoptions": [{"id": "as-command", "title": "Name commands with #[AsCommand]", "status": "applied", "places": 2}],
    "blocked": [{"name": "doctrine/doctrine-bundle", "version": "3.3.3", "reason": "requires PHP ^8.4, the project is on 8.2"}],
    "path_ahead": [{"name": "symfony/*", "versions": ["8.1.9"]}]
}
```

| Field | |
|---|---|
| `main` | the main move: `package`, `label` (shown; defaults to `package`), `from`, `to` (versions) |
| `php` | `from`, `to`: shown only when they differ |
| `packages_moved`, `constraints_rewritten` | counts (packages whose locked version changed; composer.json constraints rewritten) |
| `tests` | `before`, `after`: `passed`, `failed` or `not_run` each, both optional |
| `deprecations_fixed` | `rules`, `files`: what was rewritten for deprecations (Rector rules, files) |
| `adoptions` | `id`, `title`, `status` (`applied`, `untested`, `proposed`, `reverted`, `rector_failed`, `unchanged`), `places` |
| `blocked` | moves refused by the policy: `name`, `version`, `reason` |
| `path_ahead` | the steps left for later runs: `name`, `versions`; the main package's line reads "Then 8.1.9 in later runs" |

Bad values are dropped, never guessed (a version that is not one, an unknown status).
The card's colours are CSS variables falling back to the page's: set `--rh-sum-bg`,
`--rh-sum-tile`, `--rh-sum-line`, `--rh-sum-ink`, `--rh-sum-muted`, `--rh-sum-accent`,
`--rh-sum-ok`, `--rh-sum-ko`, `--rh-sum-wait` (or the page's `--rh-*`) on your admin to
match it. Light and dark follow `prefers-color-scheme`, or `data-theme="light"` /
`data-theme="dark"` on `<html>` when your admin sets it.

## Validation: manual or auto

```yaml
rehearsal:
    validation: manual
```

- `manual` (default): nothing goes further until someone clicks **Validate**.
- `auto`: a pending change is validated as soon as its tests passed (`by: "auto"`,
  `auto: true`). A change without passing tests (failed, not run, none) still waits for
  someone. Set it per site: `auto` where the tests are trusted, `manual` elsewhere.

## Hooking the decision

```php
use Acencyril\RehearsalBundle\Event\ChangeValidated;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class DeployValidatedChange
{
    public function __invoke(ChangeValidated $event): void
    {
        // $event->change: id, title, revision, source, tests…
        // $event->decision: comment, by, at, auto
        // $event->set: the whole changes file
        // e.g. write a file your CI watches, call your deploy webhook…
    }
}
```

`ChangeRejected` carries the comment; `ChangesProposed` fires once per new change
revision. Events are dispatched under their class name: listen to each one you need.

Comments are what someone typed: treat them as remarks to read, never as commands to
run.

## Installation

With Symfony Flex the recipe registers the bundle and writes
`config/packages/rehearsal.yaml` and `config/routes/rehearsal.yaml`. Without Flex:

```php
// config/bundles.php
Acencyril\RehearsalBundle\RehearsalBundle::class => ['all' => true],
```

```yaml
# config/routes/rehearsal.yaml
rehearsal:
    resource: '@RehearsalBundle/config/routes.php'
    prefix: '/{_locale}/admin/rehearsal'
    trailing_slash_on_root: false
    requirements:
        _locale: 'en|fr'
```

The prefix is yours: put the section where your admin lives. The controller checks
`rehearsal.role` itself (403 otherwise); keep the path behind your firewall so that
visitors are asked to sign in. The bundle needs SecurityBundle, the translator, Twig
and CSRF protection (`framework.csrf_protection`).

## Configuration

```yaml
rehearsal:
    changes_file: '%kernel.project_dir%/.rehearsal/changes.json'
    decisions_file: '%kernel.project_dir%/var/rehearsal/decisions.json'
    validation: manual                  # or auto
    role: ROLE_ADMIN
    parent_template: 'admin/layout.html.twig'
    mailer:
        enabled: true
        from: 'no-reply@example.com'
        to: ['you@example.com']
        subject_prefix: '[Rehearsal]'
        locale: fr
```

### The page

The section extends `parent_template`, your admin layout, which must define the
`title` and `body` blocks (default: a bare page). To change the markup, override
`templates/bundles/RehearsalBundle/index.html.twig`, or only its `rehearsal_content`
or `rehearsal_styles` block. Texts are translation keys (domain `rehearsal`, English
and French).

### The emails

With `mailer.enabled` and Symfony Mailer installed: one recap when new changes appear
(with the ones validated automatically), and one email per decision, with a link to
the section. Set `default_uri` (`framework.router.default_uri`) for the link to be
right from the console.

## Commands

| Command | |
|---|---|
| `rehearsal:sync` | notices new changes (recap email, `ChangesProposed`) and auto-validates in `auto` mode; run it after a deploy or from cron |
| `rehearsal:status` | where each change stands; `--json` for CI (`counts`, `all_validated`, each change with its decision) |

## Tests

```bash
vendor/bin/phpunit
```

## License

MIT
