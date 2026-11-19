# wp-security-gates

A skill for AI coding agents that write WordPress code. It adds the security rules, tests and lint gate that quality-gate skills like [ub-mode](https://github.com/unclebob/ub-mode) leave out.

ub-mode replaces line-by-line review with hard gates: test first, CRAP under 10, no duplication above 0.8, no surviving mutants. Those gates measure the shape of the code. They don't see a capability check that was never written: mutation testing mutates code that exists, so a REST route without `current_user_can()` gives it nothing to mutate, and everything stays green. CRAP 9 and an SQL injection fit in the same function.

This skill closes that gap for WordPress:

- **Rules** - sanitize on input, escape at output (even for "trusted" post meta), capability plus nonce on every entry point, SQL only through `$wpdb->prepare()`.
- **Tests first** - PHPUnit templates for REST routes and admin-ajax handlers: anonymous gets 401, wrong role gets 403, bad nonce gets rejected. The agent writes them before the check and watches them fail.
- **Deterministic gate** - PHPCS with WordPress Coding Standards security sniffs. The agent doesn't finish while it's red.
- **Human reading list** - the agent lists the files where a mistake is expensive (permissions, migrations, writes to roles and options, new public endpoints) so a person reads those and only those.

## Install

Claude Code:

```bash
claude plugin marketplace add turovskiy/wp-security-gates
claude plugin install wp-security-gates@wp-security-gates
```

Other agents (Cursor, Codex, Grok and the like): copy `skills/wp-security-gates/` into the agent's skills directory.

Then, in the WordPress project, once:

```bash
composer config allow-plugins.dealerdirect/phpcodesniffer-composer-installer true
composer require --dev wp-coding-standards/wpcs:^3 dealerdirect/phpcodesniffer-composer-installer
cp <skill>/templates/phpcs.xml.dist .
cp <skill>/templates/security-gate.sh scripts/
```

The permission templates need the WordPress test suite (`wp scaffold plugin-tests`).

## What's inside

| File | Purpose |
|---|---|
| `skills/wp-security-gates/SKILL.md` | Rules and order of work for the agent |
| `templates/test-rest-permissions.php` | 401 / 403 / allowed-role tests for one REST route, plus a check that every route in your namespace declares `permission_callback` |
| `templates/test-ajax-permissions.php` | Nonce, capability and no-`nopriv` tests for one admin-ajax action |
| `templates/phpcs.xml.dist` | `WordPress.Security`, `WordPress.DB.PreparedSQL*`, `WordPress.WP.Capabilities` |
| `templates/security-gate.sh` | Runs PHPCS, exits non-zero while any sniff is red |

## Proof that the templates work

`tests/run.sh` runs the templates against a fixture plugin in three modes and the gate against two files, inside Docker (MariaDB plus the current `wordpress-develop` trunk):

| Case | Expected |
|---|---|
| secure plugin | all permission tests pass |
| `permission_callback => '__return_true'`, no nonce, no capability check | exactly the 401, 403, nonce and capability tests fail |
| route without `permission_callback` | the namespace check fails |
| `bad.php` (raw `echo`, `$_GET` in SQL) | gate exits non-zero with EscapeOutput, NonceVerification, ValidatedSanitizedInput, PreparedSQL |
| `good.php` (same work done safely) | gate exits 0 |

```bash
tests/run.sh
```

Needs Docker, git and python3.

## Credits

- [ub-mode](https://github.com/unclebob/ub-mode) by Robert C. Martin, the skill this one extends.
- [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards) for the sniffs.

## License

MIT
