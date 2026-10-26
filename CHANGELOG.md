# Changelog

All notable changes to Rehearsal.
Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
versioning follows [SemVer](https://semver.org/).

## [Unreleased]

## [0.2.1] — 2026-10-27

### Changed
- **The section blends into any admin.** Its colours are `--rehearsal-*` custom properties
  (documented in the README, "The section's look"), whose defaults derive from the page's text
  colour: it reads on a light and on a dark admin alike, in the admin's font.
- It renders in a `<div>` instead of a second `<main>`, with no padding of its own
  (`--rehearsal-padding`): the host layout owns the page's frame. The bare default page keeps
  its spacing.

### Fixed
- The colours no longer follow the visitor's system theme on their own: on a light-only admin
  viewed from a dark system (or a dark admin from a light one), the text was nearly invisible.
  `data-theme="dark"` on `<html>` still brightens the tones.
- The Validate button keeps white text on a solid green in both themes.

## [0.2.0] — 2026-10-26

### Added
- **Upgrade summary card**: a change may carry an optional `summary` (main move, PHP
  move, packages moved, composer.json constraints rewritten, tests before / after,
  deprecations fixed, adoptions, moves refused by the policy, the way ahead). Rehearsal
  then shows a card at the top of the change, with the decision state; changes without
  it look as before. Schema in the README; Uplift 0.3 fills it.
- The card's colours are CSS variables (`--rh-sum-*`) falling back to the page's
  `--rh-*`; real headings, and every status said in words, not only in colour.
- Light and dark also follow `data-theme="light"` / `data-theme="dark"` on `<html>`,
  for admins that set their theme themselves.

## [0.1.0] — 2026-10-25

First release.

### Added
- An admin section (route prefix of your choice, `rehearsal.role`, default
  `ROLE_ADMIN`) listing the proposed changes of a JSON file, each with its notes,
  what it replaces, what to check, a link inside the site and its test results.
- **Validate** (optional comment) and **Reject** (comment required) per change,
  CSRF-protected; decisions written to a JSON file under an exclusive lock, per
  revision of a change: a new revision is pending again, with the earlier decision
  shown.
- Events: `ChangeValidated`, `ChangeRejected` (sharing `ChangeDecided`) and
  `ChangesProposed`, for the application to merge, deploy or report back.
- Validation per site: `manual` (wait for the click) or `auto` (validate when the
  tests passed; anything else still waits).
- Optional recap emails through Symfony Mailer: new changes, and each decision.
- `rehearsal:sync` and `rehearsal:status` (`--json` for CI).
- The changes file accepts the earlier sandbox form (`pr`, journeys as
  `{ok, ko, failures}`); links leaving the site are dropped.
- Overridable template extending your admin layout (`parent_template`), English and
  French translations, light and dark styles.
- Flex recipe: the bundle, `config/packages/rehearsal.yaml`,
  `config/routes/rehearsal.yaml`.

[Unreleased]: https://github.com/acencyril/rehearsal-bundle/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/acencyril/rehearsal-bundle/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/acencyril/rehearsal-bundle/releases/tag/v0.1.0
