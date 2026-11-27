# Contributing to Rise Open Core

We welcome focused, evidence-based contributions to the Apache-2.0 Open Core source. Read [README.md](README.md), [AGENTS.md](AGENTS.md), the [capability map](docs/CAPABILITY_MAP.md), and the relevant developer guide before making changes.

## Development workflow

1. Search existing issues before opening a new one. Describe the problem, affected revision, reproduction steps, and expected behavior.
2. Fork the repository and create a focused branch. Follow the [local installation guide](docs/INSTALL_RUN34.md) using a clean source export, an isolated MariaDB instance, and synthetic data only.
3. Make the smallest reversible change. Preserve existing authorization, access, maintenance, idempotency, audit and canonical wallet/transaction/ledger contracts.
4. Run relevant focused tests, documentation checks, and local smoke tests. Include negative cases for security or financial boundaries. Update affected documentation and the source manifest when needed.
5. Open a pull request with a concise summary, changed scope, test evidence, and remaining risks. Keep unrelated changes separate.

## Product and security boundaries

- Rise Full and private hosted services are outside this source distribution. Do not import their private source, credentials, operator data, or proprietary assets.
- Never bypass enrollment, database isolation, snapshots, write gates or authorization controls. Synthetic tests are not real-money or production acceptance.
- Preserve third-party notices and attribution. Contributions must be compatible with the [Apache-2.0 license](LICENSE).
- Do not report vulnerabilities publicly. Follow the [security policy](SECURITY.md) and never include secrets, customer data, or operational credentials in an issue or pull request.

For technical setup and integration guidance, see [Getting Started](docs/START_HERE.md).
