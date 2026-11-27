# Rise Open Core — contributor / agent contract (public source)

Read README.md, DISTRIBUTION.md, docs/START_HERE.md,
docs/RISE_PRODUCT_DIRECTION_CURRENT.md, docs/REVIEW_RIGHTS_AND_LIMITS.md and
the exact source/tests before changing code. The public source is distributed under Apache-2.0, with upstream licenses
and attribution retained.

## Product boundary
- Two Rise distribution tiers: Open Core and separately commercial Full.
- Nova Gateway and ChessBet are optional separately provided services and
  connectors, not a third Rise distribution tier. Their private hosted engines,
  secrets, operator data and commercial Full modules are not part of this repo.
- Keep a single canonical user/owner/wallet/transaction/ledger authority.
  Use configuration/adapters instead of a permanent customer-specific fork.
- A local/synthetic demo, provider fake or read-model is not real-money acceptance.
  Do not enable unsupported gateway, withdrawal or provider write gates.
- AI is an interpretation or evidence layer, not independent permission, money,
  gambling-result or final financial authority.

## Working method
Narrow source-first audit; minimal, reversible patch; focused tests and local
read-model/browser smoke; report the exact evidence, changed files,
tests and remaining risks. Preserve other worktrees and unrelated changes.
Do not change live/production services or authorise money movement automatically.

## Safe local installation
Use an exact published revision and its SOURCE_SHA256.json on a clean git archive
before dependency/runtime creation. Follow docs/INSTALL_RUN34.md. Root-owned
instance enrollment, isolated MariaDB, real snapshot and install guard remain
mandatory where documented. Never bypass an instance mismatch, inspect or reuse
another operator's DB, import secrets, or apply migrate:fresh/reset.

## Contributions and security
Review the exact version, include relevant negative tests, preserve upstream
attribution and avoid secrets. See SECURITY.md for private vulnerability reports.
Updating a fork is a reviewed Git merge, not an automatic overwrite.
