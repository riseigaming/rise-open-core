# Local installation wizard

`./rise` shows the installation steps and checks the source and environment. `./rise web-setup` opens a loopback browser guide for the same process. Create the database, perform root-owned enrollment, take a snapshot, install dependencies, run migrations, and set up the first admin in your terminal using the [detailed runbook](INSTALL_RUN34.md).

## Prepare the source

Select the exact published revision and make sure `HEAD` below points to it. Use a new, empty export destination.

```sh
git clone https://github.com/riseigaming/rise-open-core.git
cd rise-open-core
git rev-parse HEAD
git status --short
mkdir ../rise-local
git archive --format=tar HEAD | tar -xf - -C ../rise-local
cd ../rise-local
python3 scripts/verify-source-manifest.py SOURCE_SHA256.json
./rise doctor --json
./rise install
```

Use the verifier only in a clean export, before adding `.git`, vendor files, `.env`, or runtime files. Stop if the manifest is missing or mismatched. Do not generate replacement hashes to bypass a mismatch. The `doctor` report covers preliminary checks only.

## Open the browser guide

```sh
./rise web-setup --port 18734
# Add --php /absolute/path/to/php and
# --composer /absolute/path/to/composer if needed.
```

Open the terminal's `http://127.0.0.1:18734/` address on the same computer. Do not use the `localhost` alias. Keep the terminal open and press Ctrl-C to stop the server. If the port is in use, choose another free port. Run the server as a normal OS user and do not tunnel or share it.

**Displayed installation profile.** The guide shows English as the default language with Turkish and Spanish enabled, UTC, supported TRY/USD/EUR fiat wallet assets, and the Normal/VIP/Riskli user classifications. Choose a real installation country and a supported fiat currency at the terminal prompts. Enter the first admin identity and password there. The profile does not read the live database and grants no country authorization, FX, or provider access.

The steps cover source and environment, database trust, storage and environment settings, lock and snapshot, migrations and admin setup, and first use. **Recheck local state** refreshes file and environment observations without connecting to the database. Stop if the source changes after initial verification. If runtime files exist after a server restart, retain the initial clean-source verification and follow the manual runbook. Do not delete runtime files to obtain a verification result.

## Storage and private settings

The web guide's only write operation is **Prepare private storage**. The clean source must have been verified in the same server session, and the checkbox must be explicitly selected. The existing helper creates missing storage/cache/DB_BACKUPS directories with mode 0700. Existing ownership and permissions are preserved, and symlink redirects are rejected. Check DB_BACKUPS permissions separately as described in the runbook.

In the terminal, use `./rise setup prepare-storage` for a dry run and `./rise setup prepare-storage --apply` to apply it explicitly. This operation grants no database privileges.

Create `.env` with mode 0600 without overwriting an existing file, and edit it in a private editor. Generate APP_KEY only once for a new installation, after the locked vendor dependencies are in place. Never enter a password, key, dump, or OS administrator password in the browser.

## Next steps

Prepare PHP ^8.4 (the lock minimum is 8.4.1), Composer 2, Python 3.8+, native MariaDB, and the required PHP extensions. Before any database operation, follow the [runbook](INSTALL_RUN34.md) requirements for independent instance identity, a new empty schema, root-owned enrollment, and a real snapshot. Do not use another operator's database or bypass guards.

After installation, continue with [first admin and CMS use](FIRST_HUMAN_TEST.md). Installation requires no Nova, ChessBet, or real provider account.
