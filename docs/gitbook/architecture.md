# Architecture and product boundary

## One platform, two distribution tiers

| Component | Open Core | Rise Full |
| --- | --- | --- |
| Account, admin and Global CMS | Included source | Builds on same authority |
| Canonical wallet, transaction and ledger | Included, guarded local contracts | Must reuse canonical authority |
| Eight internal games and adapter contracts | Included supported source | May extend current platform |
| Ops Radar core read model | Included | May add advanced reporting |
| Affiliate / advanced CRM / commercial operations | Not part of this source release | Separately commercial |
| Optional Nova / ChessBet engines | **Connectors only** | Separately contracted hosted services, not a third tier |

The same code can be forked or customized under Apache-2.0. A fork is a developer right; Rise does not require building a new, disconnected finance service per customer.

## Financial invariants

- Single canonical owner and wallet/transaction/ledger writer.
- Exact asset and amount; avoid float-based financial authority.
- New session, status observation, receipt claim and actual credit are **different events**.
- A verified crypto asset deposit is not automatically spendable TRY/USD/EUR gameplay balance.
- Provider credentials, private hosted engines, operators' data and unapproved live payment features are **not shipped** in the repo.

For more detail: [Product Direction](https://github.com/riseigaming/rise-open-core/blob/main/docs/RISE_PRODUCT_DIRECTION_CURRENT.md), [Portfolio](https://github.com/riseigaming/rise-open-core/blob/main/docs/PRODUCT_PORTFOLIO.md), [Distribution](https://github.com/riseigaming/rise-open-core/blob/main/DISTRIBUTION.md).
