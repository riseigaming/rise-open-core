# Security and licences

Rise Open Core is distributed under **Apache License 2.0**. Retain original copyright/license notices, and review third-party package and asset obligations. Private Rise Full modules, Nova/ChessBet hosted engines and operator credentials do not become public simply because the connector is open.

## Essential safeguards

- Validate exact source hashes from a clean export **before** runtime files are generated.
- Keep the documented root-owned MariaDB instance enrollment, correct datadir/port/server ID and real pre-migration snapshot.
- Do not bypass disabled providers, real-money payments, withdrawals, maintenance or RBAC gates.
- Protect API keys, cookies, session identifiers, customer data and operator backups.
- Treat unknown payment state and provider timeout as **reconcile**, not as authority to make another credit or payout.
- Run composer security audit and the focused relevant tests for each update.

Security reporting should be private; use [SECURITY.md](https://github.com/riseigaming/rise-open-core/blob/main/SECURITY.md) rather than posting exploitation details in public issues.

[License](https://github.com/riseigaming/rise-open-core/blob/main/LICENSE) ·
[Third-party notices](https://github.com/riseigaming/rise-open-core/blob/main/docs/THIRD_PARTY_NOTICES.md) ·
[Distribution scope](https://github.com/riseigaming/rise-open-core/blob/main/DISTRIBUTION.md).
