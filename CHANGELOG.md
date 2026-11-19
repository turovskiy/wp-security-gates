# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-11-19

### Added
- `wp-security-gates` skill: input, output, entry-point, SQL and secrets rules for an agent writing WordPress code, as a companion to ub-mode.
- PHPUnit templates for REST routes (401, 403, allowed role, `permission_callback` on every route in the namespace) and admin-ajax handlers (no `nopriv` hook, invalid nonce, missing capability, allowed role).
- PHPCS ruleset (`WordPress.Security`, `WordPress.DB.PreparedSQL*`, `WordPress.WP.Capabilities`) and `security-gate.sh`, which exits non-zero while any sniff is red.
- `tests/run.sh`: Docker harness that runs the templates against a fixture plugin in secure, insecure and no-`permission_callback` modes, and the gate against a bad and a good file.
