# Rise product and architecture direction

Rise has two software distributions, **Open Core** and the separate commercial **Rise Full**. Nova Gateway and ChessBet are optional, separate services. [DISTRIBUTION.md](../DISTRIBUTION.md) explains source rights.

| Area | Direction |
| --- | --- |
| Open Core | Global CMS, accounts, canonical finance, Internal Games, built-in Sportsbook, adapter contracts, and basic Ops Radar |
| Rise Full | Affiliate, advanced CRM, reporting, and commercial operations, subject to a separate product agreement |
| External Games | Independent alias, credentials, and session mapping for each of multiple providers |
| ChessBet | A separate hosted game experience connected to Rise's account and financial authority through an iframe and API |
| Nova Gateway | Optional payment service linking the intent, verified receipt, and canonical asset wallet |
| Localization | TR/EN/ES infrastructure and country and product access policies. Copy for additional languages is prepared separately. |
| AI tools | Assistants that interpret source code and operational records without gaining independent authorization or financial authority |

Configuration and adapters handle brand and customer differences. Extend the shared user, owner, wallet, transaction, and ledger contract to avoid a separate financial engine or permanent private source branch for each customer.

Sportsbook acceptance, pending records, and authorized rejection and cancellation are separate from outcome generation. General automatic and manual won/lost settlement entry points depend on a capability that is disabled by default. A missing result stays pending. Cancelling a funded bet must produce a single refund linked to the verified original debit.

New integrations must preserve existing access, maintenance, idempotency, audit, and financial gates. The scope of Full and hosted services is outside this repository's feature list. The [capability map](CAPABILITY_MAP.md) lists the concrete entry points in the source.
