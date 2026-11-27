# Installation and safety

**This is a guided, manual installation**—not a one-click hosted provisioning tool. Follow the exact [local installation runbook](https://github.com/riseigaming/rise-open-core/blob/main/docs/INSTALL_RUN34.md) for all database, OS ownership and administrative steps.

## 1. Verify the source

Use a clean clone and **export before Composer, .env or runtime files are created**:

```sh
git clone https://github.com/riseigaming/rise-open-core.git
cd rise-open-core
git rev-parse HEAD
mkdir ../rise-local
git archive HEAD | tar -xf - -C ../rise-local
cd ../rise-local
python3 scripts/verify-source-manifest.py SOURCE_SHA256.json
./rise doctor --json
./rise install
```

The verification must pass for the **same commit**. Never repair a mismatch by generating your own acceptance manifest from untrusted files.

## 2. Guided browser flow

```sh
./rise web-setup --port 18734
```

The guide binds to local loopback. It can inspect the environment and, after explicit consent, prepare permitted local storage. **It does not create a database, enrol an OS instance, install dependencies, migrate, bootstrap admin or guarantee readiness.** The wizard is not a source of payment or production authorization.

## 3. Human-controlled gates

The authoritative manual runbook covers:

1. Independently verify a **new empty** local MariaDB instance: physical datadir, port, server ID and process owner.
2. Create the reviewed instance manifest and immutable root-owned enrollment using an OS administrator.
3. Take a real database dump and verify its identity, bytes and SHA-256.
4. Install locked Composer dependencies; prepare private `.env` and keys.
5. Apply the expected schema (**72 migrations** in this revision), bootstrap the first admin with a real country and a supported fiat code (TRY, USD or EUR). English is the default language and UTC is the default timezone. Run `php artisan app:install:check`.
6. Verify CSRF login, a CMS save→DB→new-session readback, and a synthetic member/wallet operation.

**Never** run `migrate:fresh`, reset an existing operator DB, bypass root-owned trust checks or import credentials from another installation.

Source of truth: [Install Wizard](https://github.com/riseigaming/rise-open-core/blob/main/docs/INSTALL_WIZARD.md) and [First admin walkthrough](https://github.com/riseigaming/rise-open-core/blob/main/docs/FIRST_HUMAN_TEST.md).
