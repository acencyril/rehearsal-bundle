# Changelog

All notable changes to Rehearsal.
Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
versioning follows [SemVer](https://semver.org/).

## [Unreleased]

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

[Unreleased]: https://github.com/acencyril/rehearsal-bundle/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/acencyril/rehearsal-bundle/releases/tag/v0.1.0
