# Source rights and operating limits

Rise Open Core is distributed under the [Apache License 2.0](../LICENSE). You may copy, fork, modify, and use the licensed source commercially. The [NOTICE](../NOTICE), [third-party notices](THIRD_PARTY_NOTICES.md), and [distribution terms](../DISTRIBUTION.md) explain the applicable obligations.

**Open Core** and the separate commercial **Rise Full** are the two software distributions. Nova Gateway and ChessBet are optional, separate services. Private hosted engines, Full modules, operator data, and credentials are outside this repository. The source license grants no access to another party's service account.

Local or synthetic tests do not certify real-money processing, real provider connectivity, production readiness, or legal compliance across jurisdictions. Before going live, operators must separately assess gaming and financial authorizations, provider agreements, content and brand rights, and rules in the relevant markets.

For local installation, use only a new, isolated MariaDB instance. Root-owned instance enrollment and a real pre-migration snapshot are required. Do not use another operator's database or secrets, or bypass instance mismatch checks, write gates, or authorization controls. See the [installation guide](INSTALL_RUN34.md).

HTTP success does not establish payment finality. Payment, game, and settlement integrations use a single canonical wallet, transaction, and ledger authority. AI output and dashboard interpretation confer no financial authority.

Report security issues privately as described in [SECURITY.md](../SECURITY.md).
