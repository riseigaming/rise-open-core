# Updates and fork verification

Open Core users keep their code customizations. Receiving an update means **reviewing and merging** source changes—not overwriting the application, database or custom theme.

## Upstream clone without custom commits

```sh
git fetch origin --tags
git diff HEAD..origin/main --stat
git merge --ff-only origin/main
```

This works only when your local branch can fast-forward.

## Your own fork with custom commits

```sh
git remote add upstream https://github.com/riseigaming/rise-open-core.git
git fetch upstream --tags
git switch -c review/rise-upstream-update
git merge --no-ff upstream/main
```

If `upstream` already exists, do not add it again. Resolve conflicts by examining both sides. Validate your local feature/customization before merging the review branch into your production branch.

## Evidence before any migration

Pin the downloaded revision, compare changed files and lock dependencies, back up the correct enrolled database, then run focused tests and browser/read-model smoke against that exact candidate. A Git merge does not prove schema compatibility and is not authorization to run `migrate:fresh`.

[UPDATING.md source contract](https://github.com/riseigaming/rise-open-core/blob/main/UPDATING.md).
