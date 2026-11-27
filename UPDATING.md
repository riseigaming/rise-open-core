# Updating and maintaining a Rise Open Core fork

Before applying a version that defaults to UTC, pin `APP_TIMEZONE` to the previous application timezone (historically `Europe/Istanbul`) if existing timestamps were written in local time. Changing the application timezone does not convert stored timestamps.

The open-source repo is the upstream source. Your changes and local configuration
remain yours. Do not copy another installation's .env, runtime keys, DB or backup.

For a clone on the upstream main branch, fetch and review before integrating:
```sh
git fetch origin
git diff HEAD..origin/main --stat
git merge --ff-only origin/main
```

For a fork, set the official repo as a separate upstream once:
```sh
git remote add upstream https://github.com/riseigaming/rise-open-core.git
git fetch upstream
git diff HEAD..upstream/main --stat
```
Then merge the specific reviewed upstream commit into your own feature branch,
resolve customizations and run project validation before applying any migration.
A fork with extra commits cannot generally fast-forward via the upstream command.

On a fresh export, verify the matching SOURCE_SHA256.json against its files
before running Composer or generating runtime data. Follow docs/INSTALL_RUN34.md.
Back up and independently verify the *specific* local database before additive
migrations. Never run migrate:fresh/reset or bypass root-owned enrollment.
Update compatibility across versions is not implicitly guaranteed by Git alone.

Additional developer guidance: [GitBook update instructions](docs/gitbook/updating.md) and [Changelog](CHANGELOG.md).
