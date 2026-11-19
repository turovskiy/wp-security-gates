#!/usr/bin/env bash
# Proves the skill templates work: permission tests go red on vulnerable code and green on safe code,
# and the PHPCS gate goes red on bad.php and green on good.php. Needs docker, git, python3.
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo="$(dirname "$here")"
cache="$here/.cache"
net=wpsg-net
db=wpsg-db
php_image=wordpress:cli-php8.2
uid="$(id -u):$(id -g)"
fail=0

cleanup() { docker rm -f "$db" >/dev/null 2>&1 || true; docker network rm "$net" >/dev/null 2>&1 || true; }
trap cleanup EXIT

mkdir -p "$cache"
if [[ ! -d "$cache/wpdev/src" ]]; then
  echo "==> cloning wordpress-develop (sparse, shallow)"
  git clone -q --depth 1 --filter=blob:none --sparse https://github.com/WordPress/wordpress-develop.git "$cache/wpdev"
  git -C "$cache/wpdev" sparse-checkout set src tests/phpunit/includes tests/phpunit/data/plugins
fi
cp "$here/wp-tests-config.php" "$cache/wpdev/wp-tests-config.php"
echo "==> WordPress $(grep -oP "wp_version = '\K[^']+" "$cache/wpdev/src/wp-includes/version.php")"

echo "==> composer install"
docker run --rm -u "$uid" -v "$here:/app" -w /app -e COMPOSER_HOME=/tmp/c composer:2 \
  install --no-interaction --no-progress -q

echo "==> starting MariaDB"
docker network create "$net" >/dev/null
docker run -d --rm --name "$db" --network "$net" -e MARIADB_ROOT_PASSWORD=root -e MARIADB_DATABASE=wptests mariadb:11 >/dev/null
for _ in $(seq 1 60); do
  docker exec "$db" mariadb -uroot -proot -e 'select 1' >/dev/null 2>&1 && break
  sleep 1
done

# run_mode <mode> <expected failing tests, space separated> [contains]
run_mode() {
  local mode="$1" expected="$2" match="${3:-exact}" junit="$cache/junit-$1.xml"
  docker run --rm --network "$net" -u "$uid" -v "$cache/wpdev:/wp" -v "$repo:/repo" -w /repo/tests \
    -e GATES_MODE="$mode" -e WP_TESTS_DIR=/wp/tests/phpunit \
    -e WP_TESTS_PHPUNIT_POLYFILLS_PATH=/repo/tests/vendor/yoast/phpunit-polyfills \
    --entrypoint php "$php_image" vendor/bin/phpunit --log-junit "/repo/tests/.cache/junit-$mode.xml" >/dev/null 2>&1 || true
  local got
  got="$(python3 -I -c '
import sys, xml.etree.ElementTree as ET
t = ET.parse(sys.argv[1])
print(" ".join(sorted(c.get("name") for c in t.iter("testcase") if c.find("failure") is not None or c.find("error") is not None)))
' "$junit")"
  local total
  total="$(python3 -I -c 'import sys, xml.etree.ElementTree as ET; print(sum(1 for _ in ET.parse(sys.argv[1]).iter("testcase")))' "$junit")"
  local want
  want="$(tr ' ' '\n' <<<"$expected" | sed '/^$/d' | sort | tr '\n' ' ' | sed 's/ $//')"
  local ok=0
  if [[ "$match" == exact ]]; then
    [[ "$got" == "$want" ]] && ok=1
  else
    ok=1; for t in $want; do [[ " $got " == *" $t "* ]] || ok=0; done
  fi
  if (( ok )); then
    echo "PASS  phpunit mode=$mode: $total tests, failing as expected: ${got:-none}"
  else
    echo "FAIL  phpunit mode=$mode: expected failing [$want] ($match), got [$got]"; fail=1
  fi
}

run_mode secure ""
run_mode insecure "test_anonymous_gets_401 test_user_without_capability_gets_403 test_invalid_nonce_dies_with_minus_one test_user_without_capability_is_rejected"
run_mode no-permission-callback "test_every_route_in_namespace_declares_permission_callback" contains

# gate <dir> <expected exit> <sniffs that must appear>
gate() {
  local dir="$1" want_exit="$2" sniffs="$3" work="$cache/gate-$1" out code=0
  mkdir -p "$work"
  cp "$here/fixture/$dir/"*.php "$repo/skills/wp-security-gates/templates/phpcs.xml.dist" \
     "$repo/skills/wp-security-gates/templates/security-gate.sh" "$work/"
  ln -sfn ../../vendor "$work/vendor"
  out="$(docker run --rm -u "$uid" -v "$repo:/repo" -w "/repo/tests/.cache/gate-$dir" \
    --entrypoint bash "$php_image" security-gate.sh --no-colors 2>&1)" || code=$?
  local ok=1
  if [[ "$want_exit" == nonzero ]]; then (( code != 0 )) || ok=0; else [[ "$code" == "$want_exit" ]] || ok=0; fi
  for s in $sniffs; do [[ "$out" == *"$s"* ]] || ok=0; done
  if (( ok )); then
    echo "PASS  gate $dir: exit $code${sniffs:+, reported $sniffs}"
  else
    echo "FAIL  gate $dir: exit $code (want $want_exit)"; echo "$out"; fail=1
  fi
}

gate gate-red nonzero "WordPress.Security.EscapeOutput WordPress.Security.NonceVerification WordPress.Security.ValidatedSanitizedInput WordPress.DB.PreparedSQL"
gate gate-green 0 ""

exit "$fail"
