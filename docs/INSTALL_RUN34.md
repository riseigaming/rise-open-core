# Local installation runbook

Extract the exact published revision into a new, empty `git archive` export. Run `python3 scripts/verify-source-manifest.py SOURCE_SHA256.json` before creating `.env`, vendor files, or runtime files. Stop if the manifest does not match. The [wizard guide](INSTALL_WIZARD.md) explains source selection and the browser workflow.

This guide applies only to a new, isolated MariaDB instance. Run PHP, Composer, and the HTTP server as a normal OS user. The OS administrator separately creates the root-owned enrollment record. Do not use another installation's database, credentials, dumps, or runtime files.

## 1. Environment and source

- PHP `^8.4` is required, with a Composer lock minimum of **8.4.1**. You also need Composer 2, Python 3.8+, and native MariaDB. Dependency versions are pinned in `composer.lock`. Prepare POSIX tools on macOS/Linux. Do not assume these steps work as written on Windows or Oracle MySQL.
- PHP extensions include dom, exif, fileinfo, gd, iconv, intl, json, libxml, mbstring, openssl, pcre, and pdo_mysql, plus POSIX `posix_geteuid` for enrollment checks. The base extension list is in `scripts/b1-boot-contract.php`. The instance guard requires POSIX.
- Use native `mariadb` and `mariadb-dump`. Independent enrollment requires an OS administrator and Python 3.8+. Run PHP, HTTP, and Composer as a normal user. Keep the administrator password out of the application and reports.
- The existing frontend files and their licenses are included in the source. **No Node build is required.** Browser use requires neither Node nor Playwright. No queue worker, scheduler, Redis, external SMTP, or provider is required.
- Verify the source export against the manifest before installing. Do not copy old `.env`, vendor, cache, uploads, dumps, or operator data. If another service owns the local ports, do not connect to it.

In a POSIX shell, use only task-specific variables. The `php` on PATH may be version 8.3.

```sh
RISE_PHP=/absolute/path/to/php
RISE_COMPOSER=/absolute/path/to/composer
"$RISE_PHP" --version
"$RISE_PHP" -r 'exit(function_exists("posix_geteuid") && posix_geteuid() !== 0 ? 0 : 1);'
"$RISE_PHP" "$RISE_COMPOSER" --version
"$RISE_PHP" "$RISE_COMPOSER" validate --no-check-publish --no-plugins --no-scripts
"$RISE_PHP" "$RISE_COMPOSER" install --no-plugins --no-scripts --prefer-dist --no-interaction
"$RISE_PHP" "$RISE_COMPOSER" check-platform-reqs --lock --no-plugins --no-scripts
```

Do not relax the lock or platform checks. `composer audit --locked` uses network access to check package advisories.

## 2. New instance and private environment

Create a new, empty MariaDB data directory for a loopback-only instance. Select the intended datadir and server_id in advance, together with the OS process owner, PID, startup arguments, and listener port. Do not automatically trust whichever target `.env` later points to. Create an empty `rise_b1_candidate` and, if testing is needed, an empty `rise_b1_test` in the same instance. Use new database users with access limited to these schemas. The example below shows how to provision MariaDB. Choose your own absolute paths. Use a separate instance and avoid the system service and existing data directories. Keep the general SQL log disabled while creating credentials.

```sh
RISE_DATA=/absolute/path/to/new/rise-db
RISE_SOCKET=/absolute/path/to/new/rise-db.sock
RISE_PID=/absolute/path/to/new/rise-db.pid
RISE_DB_PORT=17990
RISE_DB_SERVER_ID=10934
mariadb-install-db --no-defaults --datadir="$RISE_DATA" --auth-root-authentication-method=socket
mariadbd --no-defaults --datadir="$RISE_DATA" --socket="$RISE_SOCKET" \
  --pid-file="$RISE_PID" --bind-address=127.0.0.1 --port="$RISE_DB_PORT" \
  --server-id="$RISE_DB_SERVER_ID" --general-log=0 --skip-log-bin
```

Keep the foreground MariaDB process running in the **first terminal**. The **second terminal** starts a new shell and does not inherit variables from the first. Use `cd` to go to the source root. Redefine all paths below using the actual values you independently selected in the first terminal. An empty `RISE_SOCKET` does not fall back to the default socket.

Before the first CREATE, prepare and review the `rise-local-instance-v2` manifest in section 3. Include the physical source root, the preselected datadir and server ID, and scopes only for the new `rise_b1_candidate` and optional `rise_b1_test`. Pin the hash after independent review. The schemas **must not yet exist**. The OS administrator creates the enrollment trust record separately in section 3. Preflight does not replace that record.

In the second terminal, run the following function as a single block. Replace the password placeholder with a new private password. SQL history and the general log are disabled. The socket-authenticated administrator is the same normal OS user who ran `mariadb-install-db` in the first terminal. If you use a different authentication setup, do not force this example to work. Verify the administrator identity before running SQL.

```sh
rise_provision_new_instance() (
  set -eu
  RISE_SOCKET=/absolute/path/to/new/rise-db.sock
  RISE_DATA=/absolute/path/to/new/rise-db
  RISE_PID=/absolute/path/to/new/rise-db.pid
  RISE_DB_PORT=17990
  RISE_DB_SERVER_ID=10934
  RISE_MANIFEST=/absolute/path/to/reviewed-manifest.json
  RISE_MANIFEST_SHA256=REVIEWED_SHA256
  : "${RISE_SOCKET:?Explicit nonempty socket required}"
  test -S "$RISE_SOCKET"
  RISE_MARIADB_PID=$(cat "$RISE_PID")
  python3 scripts/enroll-local-instance.py --preflight \
    --manifest "$RISE_MANIFEST" --sha256 "$RISE_MANIFEST_SHA256" \
    --pid "$RISE_MARIADB_PID" --port "$RISE_DB_PORT" \
    --socket "$RISE_SOCKET" --pid-file "$RISE_PID" --owner "$(id -un)"
  # Open this client only if preflight exits with 0. Use the same pinned socket and owner.
  MYSQL_HISTFILE=/dev/null mariadb --no-defaults --protocol=SOCKET \
    --socket="$RISE_SOCKET" --user="$(id -un)" <<'RISE_SQL'
CREATE DATABASE rise_b1_candidate;
CREATE DATABASE rise_b1_test;
CREATE USER 'rise_local'@'127.0.0.1' IDENTIFIED BY '<new-private-password>';
GRANT ALL ON rise_b1_candidate.* TO 'rise_local'@'127.0.0.1';
GRANT ALL ON rise_b1_test.* TO 'rise_local'@'127.0.0.1';
RISE_SQL
)
rise_provision_new_instance
```

Preflight runs only SELECT/SHOW. Against the same reviewed manifest, it verifies the actual socket and PID file, OS process UID and startup arguments, datadir ownership, loopback binding, port, server ID, SQL identity, socket-authenticated administrator privileges, and absence of the target schemas. A wrong instance or owner, a missing or empty socket, or an existing target schema prevents the function from reaching any mutating SQL, including CREATE/ALTER/DROP/DELETE. MariaDB has no schema-owner field, so the sequence is **verify absence → create as the verified instance administrator → explicitly GRANT access to the schema only to the new runtime account**. Do not take ownership of an existing schema. Existing database installations are outside this fresh provisioning step.

The second schema and GRANT are required only for testing. Keep the secure administrator account separate from the application runtime account. Connect over TCP with the runtime account and verify the two permitted schemas and the intended instance identity. Use an interactive password prompt.


```sh
umask 077
cp .env.example .env
chmod 600 .env
```

In a private editor, set `APP_ENV=local`, `APP_DEBUG=false`, Global, `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, the independently verified `DB_PORT`, `DB_DATABASE=rise_b1_candidate`, new database credentials, an empty `DB_SOCKET`, and the appropriate `APP_URL`. Preserve the file session/cache, database queue, and local egress defaults. Generate `APP_KEY` only once for a new installation.

```sh
"$RISE_PHP" artisan key:generate --no-interaction
```

If you need a test profile, use a separate `.env.testing` with `APP_ENV=testing`, `DB_DATABASE=rise_b1_test`, and the same attested instance settings. This file also requires mode 0600. Explicitly set a separate APP_KEY for the test profile or reuse the new installation key. Switching profiles does not automatically extend trust to another instance.

## 3. Independent enrollment before the first application mutation

The OS administrator verifies the intended process's PID, startup arguments, datadir, port, and server_id. Use an interactive password to run read-only SQL against the same target and compare `DATABASE(), @@datadir, @@port, @@server_id, @@foreign_key_checks` with the OS evidence. Separately verify that the schemas are empty. Do not pass the database user's password as a command argument.

Use the following manifest as a review example, with the actual physical root and previously verified values.

```json
{
  "schema": "rise-local-instance-v2",
  "application_root": "/physical/path/to/extracted-rise",
  "runtime_datadir": "/independently/verified/mariadb-data/",
  "runtime_server_id": "10934",
  "scopes": [
    {"environment":"local","connection":"mysql","database":"rise_b1_candidate"},
    {"environment":"testing","connection":"mysql","database":"rise_b1_test"}
  ]
}
```

Both scopes refer to the same attested instance. Omit the testing line if you need only local use. Wildcard and cross-product scopes are unavailable. Pin the independently reviewed manifest hash. Enrollment is never performed automatically from `.env`.

```sh
sudo /usr/bin/python3 scripts/enroll-local-instance.py \
  --manifest /absolute/path/to/reviewed-manifest.json \
  --sha256 REVIEWED_SHA256 --pid VERIFIED_MARIADB_PID --port VERIFIED_PORT
```

The provisioner writes a new record only to a fixed root-owned directory that group members and other users cannot write to. It does not overwrite records, relax permissions, or run PHP, SQL, or migrations. The directory is `/Library/Application Support/RiseOpenCore/instance-manifests` on macOS and `/etc/rise-open-core/instance-manifests` on Linux. The filename is the SHA256 of the physical application root. Environment and configuration settings cannot select the path.

Before each protected CLI mutation, the application verifies the current PDO connection and TCP probe against this record. It rejects missing, invalid, or writable records, running the application as root, and different instances. A service restart with the same root, datadir, and server_id requires no new enrollment. If the source root or instance moves, renew the record through fresh OS verification. Do not change permissions or guards to bypass it. This guard prevents accidental targeting errors. It provides no cryptographic attestation against root, a database administrator, or modified PHP code.

## 4. Real snapshot, 72 migrations, and first admin

Take a real dump of the intended empty schema with `mariadb-dump --single-transaction --skip-lock-tables`. Use an interactive password or a native client option file with mode 0600. Set `DB_BACKUPS` to 0700 and the dump to 0600. Do not overwrite an existing file. Record the dump exit status, byte count, hash, instance, and schema. A marker file containing a comment does not qualify as a snapshot. This product has no registered `app:db:snapshot` command.

Run this native dump example from the candidate source root. The schema and port must match the independently verified target.

```sh
RISE_DB_PORT=17990
RISE_DB_USER=your_new_database_user
RISE_DB_NAME=rise_b1_candidate
mkdir -p DB_BACKUPS
chmod 700 DB_BACKUPS
RISE_DUMP="DB_BACKUPS/${RISE_DB_NAME}-$(date -u +%Y%m%dT%H%M%SZ).sql"
(umask 077; set -C; mariadb-dump --protocol=TCP --host=127.0.0.1 \
  --port="$RISE_DB_PORT" --user="$RISE_DB_USER" --password \
  --single-transaction --skip-lock-tables "$RISE_DB_NAME" > "$RISE_DUMP")
# Continue only on exit status 0. A failed or partial dump is unacceptable.
shasum -a 256 "$RISE_DUMP"
wc -c "$RISE_DUMP"
```

The existing snapshot gate checks the file extension and age. It does not automatically verify that the dump belongs to this schema or can be restored. The operator must retain the exit status, target, and hash recorded above. Assess separately whether the dump can be restored.

```sh
"$RISE_PHP" artisan migrate --no-interaction
"$RISE_PHP" artisan migrate:status
"$RISE_PHP" artisan app:admin-bootstrap
"$RISE_PHP" artisan app:install:check
"$RISE_PHP" artisan storage:link
"$RISE_PHP" artisan route:list --json
RISE_HTTP_PORT=18990 # Choose a free port matching APP_URL.
"$RISE_PHP" artisan serve --host=127.0.0.1 --port="$RISE_HTTP_PORT" --no-reload
```

Expect **72 migrations**, the first admin, EN/TR/ES enabled with English as default and UTC system time, Global, registration initially disabled, and exit status 0 from the neutral checker. Enter the installation country ISO2 code and name, a supported fiat currency (TRY, USD or EUR), and new admin credentials interactively. There is no fixed or default password. A second bootstrap is rejected, and bootstrap is subject to the enrollment guard. Match the HTTP port to APP_URL. The normal application user must be able to write to `storage` and `bootstrap/cache`.

<a id="5-bağımsız-kişinin-tamamlayacağı-cms-işi"></a>

## 5. First CMS use

1. Verify that a guest visiting `/cms/admin` is redirected to sign-in and that the actual login form, CSRF, and cookie allow admin access.
2. Save the site title, description, and logo. In a new guest session, verify the Global DOM and decoded image and check them again after refreshing.
3. Save the sidebar and hero menu with TR/EN destinations. Verify that the visible link opens the correct local page.
4. Upload, order, and edit two sliders. Verify the first image and title in Global.
5. Save promotion content in TR/EN. Verify the updated content in the modal and on the detail page.
6. Save a new language name and code. Read them back from the list and database, and verify that TR remains the default. Adding language metadata does not automatically translate the interface.
7. Enable registration in the CMS and create a new synthetic member. As the member, open a canonical TRY wallet from the wallet screen. As admin, create a promotion code through `/cms/admin/finance?customer_id=<actual_id>`, then redeem it as the member. The second redemption must add no credit. Verify the transaction and ledger entries and read back the balance. Use only synthetic sample data in this workflow.

8. In the CMS admin's **Content Pages** section, create a synthetic draft with the slug `kurulum-ornek-rehber`. Enter different titles and body text for TR/EN/ES, for example “Kurulum rehberi”, “Setup guide”, and “Guía de instalación”, with a `<strong>` example in the body. On reopening, the fields should persist and the draft URL should return 404. Publish the page and save `/cms/page/kurulum-ornek-rehber` as the destination for all three languages in the existing sidebar menu. Select the languages in separate guest sessions and open the page through the menu. Verify the correct title and formatting. After unpublishing, the page should return 404 and the menu link should be hidden, while the admin menu record should persist.
9. In site settings, select **Mavi** or **Nane** as the Global accent color. Refresh the settings page to verify the selection, and check Global in a separate guest session. To restore the default, choose the existing **Varsayılan** preset.

`app:install:check` applies to the initial default installation. After changing the title, logo, language, or registration settings, review the changed defaults separately and do not conceal schema, route, or runtime errors. Check content persistence by saving it, reopening it, and viewing it in a separate guest session.

## 6. Game, connector, and Ops Radar checks

After recording the neutral check, run `"$RISE_PHP" artisan db:seed --class=InternalDiceDemoSeeder` to add a Dice catalog fixture **only in the enrolled, isolated candidate** and with a current, verified snapshot. Seeders for other families are in `database/seeders`. The user opens a TRY wallet through /cms/wallet. After applying synthetic promotion credit, follow /casino → Dice → member launch → a single roll. Replay the same request key and compare the canonical transaction and ledger records. Guest demo creates no bets or virtual balance. [Internal Game Extension](developer/INTERNAL_GAME_EXTENSION.md)

- Configure External Games connectors with an authorized endpoint, origin, credentials, provider alias, and currency. Verify with local fixture tests first on a clean default installation. [External Game Adapter](developer/EXTERNAL_GAME_ADAPTER.md)
- ChessBet requires private `CHESS_*` configuration and an authorized hosted partner. It is disabled on a clean installation. Start with signed fixture tests. [Hosted Iframe Game](developer/HOSTED_IFRAME_GAME.md)
- Nova is disabled on a clean installation. Using local fixtures grants no commercial service access. Use `nova:connection-check` only when a real, authorized account is available. [Payment Connector](developer/PAYMENT_CONNECTOR.md)
- Open Ops Radar from the admin menu. Verify that it loads and that filtering by provider and local transaction reference works. Compare results with the actual records and the transaction and ledger. [Ops Radar read model](OPS_RADAR_READ_CONTRACT.md)

<a id="7-teşhis-ve-tarihsel-sınırlı-recovery"></a>

## 7. Troubleshooting and recovery

First check PHP binary and extension compatibility and the Composer lock, then check `app:install:check` and route registration. If the instance is rejected, inspect the physical app root, datadir, server ID, port, environment scope, and ownership of the root record. Do not try to fix it with a second admin bootstrap, a new APP_KEY, or a migration reset.

Run `storage:link` only from your own installation root. For asset issues, check directory permissions and APP_URL. Moving the source root or instance requires fresh OS verification and enrollment.

Downgrading or performing a general restore on a populated schema is outside this fresh installation process. Assess the recovery plan separately using the target revision, real dump, and data state. Do not use `migrate:fresh/reset`. See the [updating guide](../UPDATING.md).

<a id="8-test-profili-ve-bağımsız-kabul-kaydı"></a>

## 8. Isolated test profile

Enroll the `.env.testing` scope beforehand. Apply the same snapshot → 72 migrations → admin bootstrap sequence to a blank test schema with `APP_ENV=testing`. PHPUnit requires a prepared baseline and does not silently prepare a blank schema.

```sh
TEST_DB_PORT=VERIFIED_PORT TEST_DB_DATABASE=rise_b1_test \
TEST_ADMIN_CREDENTIALS_FILE=/private/path/to/new-test-admin.json \
"$RISE_PHP" vendor/bin/phpunit -c phpunit.xml.dist
```

The private JSON contains `username`, `email`, and `password` and must be kept at mode 0600. Keep credentials, database dumps, uploads, sessions, mail, cache, and runtime files out of the source package. If you need isolated sample game content, use the existing `database/seeders/Internal*Seeder.php` seeders. Sample content and balances do not automatically become production data. Existing local fixture and gate boundaries remain in effect. Installation requires no external provider.

When reporting an issue, record the revision, manifest hash, runtime versions, relevant step, and error details with secrets removed. After installation, continue with the [first-use guide](FIRST_HUMAN_TEST.md).
