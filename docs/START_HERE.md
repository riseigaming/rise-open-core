# Rise developer guide

Rise Open Core brings Global CMS, user accounts, eight Internal Games variants, built-in Sportsbook, provider connectors, and operational tools together on one platform. Accounts, wallets, transactions, and the ledger share a single canonical authority.

The source is covered by Apache-2.0. Rise Full is a separate commercial distribution. Nova Gateway and ChessBet are optional services. See the [distribution terms](../DISTRIBUTION.md).

## Where to start

| Goal | Guide |
| --- | --- |
| Set up a new local instance | [Installation wizard](INSTALL_WIZARD.md), [detailed installation](INSTALL_RUN34.md) |
| Set up the first admin and CMS content | [First use](FIRST_HUMAN_TEST.md) |
| Find features in the source | [Capability map](CAPABILITY_MAP.md), [use cases](USE_CASES.md) |
| Develop a game | [Internal Game Extension](developer/INTERNAL_GAME_EXTENSION.md) |
| Connect an external game provider | [External Game Adapter](developer/EXTERNAL_GAME_ADAPTER.md) |
| Integrate a hosted iframe | [Hosted Iframe Game](developer/HOSTED_IFRAME_GAME.md) |
| Develop a payment connector | [Payment Connector](developer/PAYMENT_CONNECTOR.md), [Nova API](developer/NOVA_PROVIDER_API.md) |
| Review HTTP fields and examples | [HTTP reference](developer/OPENAPI_REFERENCE.md), [examples](developer/INTEGRATION_EXAMPLES.md) |
| Read operational records | [Ops Radar](OPS_RADAR_READ_CONTRACT.md) |
| Work on the source with AI | [AI onboarding](AI_AGENT_ONBOARDING.md), [AGENTS.md](../AGENTS.md) |
| Update a fork | [Updating guide](../UPDATING.md) |

## First installation

Select the exact published revision and verify `SOURCE_SHA256.json` in a clean `git archive` export before creating runtime files. The [installation runbook](INSTALL_RUN34.md) covers a new, isolated MariaDB instance, root-owned enrollment created by the OS administrator, a real snapshot, and first-admin setup. Stop if source hashes or instance identity do not match.

Installation requires no Nova, ChessBet, or real provider account. Use local fixtures to develop integrations. Separately assess the provider agreement and operating requirements before using remote services or real funds. See [rights and limits](REVIEW_RIGHTS_AND_LIMITS.md).

See [GitBook](gitbook/README.md) for the English topic guide and the [product portfolio](PRODUCT_PORTFOLIO.md) for product scope.
