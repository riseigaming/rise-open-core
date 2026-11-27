# Rise product portfolio

Rise has two software distributions. Both use the same architecture for users, canonical wallets, transactions, and the ledger.

| Distribution | Scope |
| --- | --- |
| **Rise Open Core** | Apache-2.0 source covering Global CMS, accounts, eight Internal Games variants, built-in Sportsbook flows, connector contracts, and basic Ops Radar |
| **Rise Full** | Separate commercial product covering Affiliate, advanced CRM, reporting, and operational features. Exact scope depends on the purchased product agreement. |

The [distribution terms](../DISTRIBUTION.md) explain Open Core source rights, and the [capability map](CAPABILITY_MAP.md) lists features in this repository.

## Optional services

**ChessBet** is a separate hosted B2B gaming service. Open Core includes the iframe/API connector. The hosted engine source is outside Open Core. The operator manages users, compliance processes, and canonical funds. Signed reserve, confirm, and release operations and the result handoff use the existing wallet and ledger contract. See the [technical integration guide](developer/HOSTED_IFRAME_GAME.md).

**Nova Gateway** is a separate optional payment service. Open Core includes a connector for payment intents, authenticated polling, receipt claims, and canonical crypto asset-wallet credits. The gateway account and private engine are provided separately. A BTC asset credit does not automatically convert to a TRY/USD/EUR gameplay balance. See the [payment integration guide](developer/PAYMENT_CONNECTOR.md).

These services do not form a third Rise distribution tier. Provider access, operational support, and managed service scope are subject to separate agreements.

## Product development

Customize branding, themes, menus, and content through the existing CMS. Develop new games and provider integrations within adapter boundaries. AI assistants can interpret source code and records. Financial and game outcome authority remains with the server.

Local and synthetic examples confer no real-money or cross-border operating authorization. See [operating limits](REVIEW_RIGHTS_AND_LIMITS.md) and [product direction](RISE_PRODUCT_DIRECTION_CURRENT.md).


## Build vs Buy

Buying an iGaming platform and acquiring the right to develop the software and add your own games to your operation are distinct decisions. Compare setup fees, monthly costs, revenue share, and source code access together.

| 2026 pricing reference | Setup / development | Ongoing cost |
|:---|---:|:---|
| White-label — Gamingsoft | **30,000–80,000 USD** | 5,000–15,000 USD/month, 10–25% GGR share |
| Turnkey — Gamingsoft | **80,000–200,000 USD** | 15,000–30,000 USD/month, 5–15% revenue share |
| White-label / Turnkey development — Capermint | **35,000–120,000 USD** | Depends on the project and agreement |

Sources are [Gamingsoft, March 2026](https://www.gamingsoft.com/blog/2026/03/how-much-does-casino-software-cost/) and the [Capermint project cost guide](https://www.capermint.com/game-development-cost-calculator/). These published price ranges cover different scopes and commercial models. They are not market averages. Assess licensing, provider access, and operating costs separately.

**The main difference is the freedom to make product decisions.** Paying setup and monthly fees for a closed-source platform does not automatically give you the right to add a game or module you developed directly to the system. Rise Open Core makes the source code available under Apache-2.0. Rise Full also lets customers bring their own games, themes, and modules into operation through hosted White-label and Turnkey models.
