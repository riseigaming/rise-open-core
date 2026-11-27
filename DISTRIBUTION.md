# Rise Open Core distribution

The Rise Open Core source code in this repository is covered by the [Apache License 2.0](LICENSE). You may copy, fork, modify, distribute, and use the source commercially, subject to the license, copyright, and attribution obligations. The [NOTICE](NOTICE) and [third-party notices](docs/THIRD_PARTY_NOTICES.md) apply together.

Rise has two software distributions, **Open Core** and the separate commercial **Rise Full**. Full modules are outside this source package. **Nova Gateway** and **ChessBet** are optional, separate services accessed through connectors. They do not constitute a third distribution tier. Their private hosted engines, service secrets, and operator data are outside this repository.

The source license does not provide hosted accounts, provider agreements, brand rights, operating authorization, managed services, support, or an SLA. Operators must independently assess the requirements of the markets and services they intend to use.

Integrations preserve a single canonical authority for users, wallets, transactions, and the ledger. New remote payment, withdrawal, or provider write permissions are not assumed. [Installation](docs/INSTALL_RUN34.md) requires an isolated database, root-owned enrollment, and a real snapshot. Local or synthetic transactions do not constitute real-funds acceptance.

Updates are adopted by merging reviewed Git changes as described in [UPDATING.md](UPDATING.md).
