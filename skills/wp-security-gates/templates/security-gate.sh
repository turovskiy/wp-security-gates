#!/usr/bin/env bash
# Security gate: exits non-zero while any WordPress security sniff is red.
# Copy to scripts/security-gate.sh, run from the project root.
set -euo pipefail

if [[ ! -x vendor/bin/phpcs ]]; then
  echo "security-gate: vendor/bin/phpcs not found. One-time setup:" >&2
  echo "  composer config allow-plugins.dealerdirect/phpcodesniffer-composer-installer true" >&2
  echo "  composer require --dev wp-coding-standards/wpcs:^3 dealerdirect/phpcodesniffer-composer-installer" >&2
  exit 2
fi

ruleset="phpcs.xml.dist"
[[ -f phpcs.xml ]] && ruleset="phpcs.xml"

vendor/bin/phpcs --standard="$ruleset" "$@"
