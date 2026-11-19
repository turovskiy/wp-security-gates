---
name: wp-security-gates
description: "Security gates for an agent writing or changing WordPress code - plugins, themes, REST routes, admin-ajax and admin-post handlers, forms, $wpdb queries. Escaping, nonces, capabilities, prepared SQL, permission tests first, PHPCS gate. Applies alongside ub-mode or any TDD skill, and even for a one-line WP change."
---

# WP Security Gates

**Corrected bias:** green shape gates - tests, CRAP, duplication, mutation score - read as proof that WordPress code is safe, and data already inside the system (post meta, options, own tables) reads as trusted at output time. Both are false. Mutation testing mutates code that exists; a capability check the agent never wrote leaves nothing to mutate, and every gate stays green. So security here is explicit rules plus a deterministic gate plus permission tests written before the code.

## Rules

### 1. Input: validate, then sanitize

- Every `$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`, `$_SERVER` value: `wp_unslash()`, then the sanitizer for its type - `sanitize_text_field`, `sanitize_textarea_field`, `sanitize_email`, `sanitize_key`, `absint`, `esc_url_raw`, `wp_kses_post` for allowed HTML.
- REST arguments go through `args` with `validate_callback` and `sanitize_callback` (or a JSON-schema `type`).
- User input never reaches `unserialize()` / `maybe_unserialize()`; use JSON.

### 2. Output: escape late, always

- ub-mode's "Check the data where it enters. Inside, trust it." covers the domain, not output. Escaping depends on the output context, not on how much you trust the data.
- `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`; data for JavaScript goes through `wp_json_encode()` (keep `esc_js()` for inline event attributes). This includes values from `get_post_meta()`, options and your own tables.
- Escape at the `echo` / template line, not when assigning to a variable.

### 3. Every entry point: capability + nonce

- REST: `permission_callback` is required. `'__return_true'` only for an endpoint that is public by design, with a comment saying why.
- admin-ajax / admin-post / forms: `check_ajax_referer()` or `check_admin_referer()` / `wp_verify_nonce()`.
- Plus `current_user_can()` with the specific capability for the action. `is_admin()` only says the request targets an admin page; it checks no permission.
- Redirects built from input go through `wp_safe_redirect()`.

### 4. SQL only through prepare

- Any query that contains data: `$wpdb->prepare()` with `%s`, `%d`, `%f`, and `%i` for identifiers.
- `LIKE`: `$wpdb->esc_like()` inside `prepare()`.

### 5. Secrets

- API keys come from environment variables or constants in `wp-config.php`, not from code or autoloaded options.

## Tests first

For every new REST route, admin-ajax handler or admin action, write these before the check exists, and see each one fail for the expected reason:

1. Unauthenticated request -> 401 (REST) or `-1` / `0` (admin-ajax).
2. Logged-in user without the capability -> 403.
3. Request without a valid nonce -> rejected.
4. Then the happy path.

Needs the WordPress test suite (`wp scaffold plugin-tests`). Copy a template, change the constants at the top, run it:

- REST route: [`templates/test-rest-permissions.php`](templates/test-rest-permissions.php). It also fails any route in your namespace that has no `permission_callback`.
- admin-ajax handler: [`templates/test-ajax-permissions.php`](templates/test-ajax-permissions.php). It assumes the handler calls `check_ajax_referer( NONCE_ACTION )` first, then `current_user_can()`, answering `wp_send_json_error( null, 403 )` on denial and `wp_send_json_success()` on success.

REST facts the templates rely on:

- When `permission_callback` returns `false`, core answers 401 to an anonymous user and 403 to a logged-in one (`rest_authorization_required_code()`). A returned `WP_Error` takes its status from `data.status`.
- For a route with parameters, the `get_routes()` key is the regex pattern; `WP_REST_Request` takes a concrete path (`/myplugin/v1/items/123`).
- `dispatch()` skips the REST cookie nonce. Over HTTP, a cookie request without `X-WP-Nonce` is treated as anonymous (the route's own 401), and a request with an invalid nonce gets 403 `rest_cookie_invalid_nonce`. Test the nonce over HTTP, e.g. Playwright.

admin-ajax facts:

- Create the nonce after `wp_set_current_user()`: nonces are bound to the user, otherwise the test fails on the nonce, not on the capability.
- A failed `check_ajax_referer()` calls `wp_die( -1, 403 )`; in tests that is `WPAjaxDieStopException` with message `-1`. `wp_send_json_*` produces `WPAjaxDieContinueException`, and the JSON sits in `$this->_last_response`.
- `_handleAjax()` fires only `wp_ajax_<action>` and never simulates an anonymous visitor. Assert `has_action( 'wp_ajax_nopriv_' . ACTION )` is false, or check over HTTP: an anonymous request to an action with no `nopriv` hook gets `0` with status 400.

## Deterministic gate

PHPCS with WordPress Coding Standards 3.x, ruleset [`templates/phpcs.xml.dist`](templates/phpcs.xml.dist), runner [`templates/security-gate.sh`](templates/security-gate.sh). The runner exits non-zero while any sniff is red. The task is done, and the branch can be pushed, only when it exits 0 and the permission tests pass.

One-time setup in the project:

```bash
composer config allow-plugins.dealerdirect/phpcodesniffer-composer-installer true
composer require --dev wp-coding-standards/wpcs:^3 dealerdirect/phpcodesniffer-composer-installer
```

The `allow-plugins` line comes first: without it Composer 2.2+ refuses the installer plugin and the `WordPress` standard never registers.

## What the human reads

Gates replace reading where a mistake is cheap. In the final report, list the changed files in these categories separately so a human reads them:

- `permission_callback`, `current_user_can`, nonce checks;
- database migrations and `dbDelta`;
- anything that writes `wp_options`, user meta holding roles or capabilities, or touches `wp_users`;
- new public endpoints (`__return_true`, `wp_ajax_nopriv_*`).

## Order of work

1. Before code: list every entry point of the task (REST, admin-ajax, admin-post, form, cron, WP-CLI).
2. For each one, the tests from "Tests first", red for the expected reason.
3. Code under rules 1-5.
4. `security-gate.sh` exits 0; permission tests green.
5. Report: what changed, plus the list of files for human reading. The work is done when every entry point from step 1 has its tests and appears in the report.
