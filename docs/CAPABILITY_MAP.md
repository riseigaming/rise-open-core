# Rise capability map

This map identifies entry points in the Open Core source. Rise Full is a separate commercial distribution. Nova and ChessBet are optional services. See the [product portfolio](PRODUCT_PORTFOLIO.md).

## Platform and finance

| Feature | Usage and source |
| --- | --- |
| Global CMS | Themes, menus, sliders, promotions, and content pages. [Content flows](../tests/Feature/Front/GlobalContentPageTest.php), [Global tests](../tests/Feature/Front/GlobalJourneyTest.php) |
| Language and access | TR/EN/ES infrastructure and country and product rules. Language metadata does not generate translations. [Catalog routes](../routes/base_catalog.php) |
| Local installation | `./rise doctor --json`, `./rise install`, and the loopback web guide. [Installation](INSTALL_WIZARD.md) |
| Canonical finance | A single owner, wallet, transaction, and ledger authority, with exact decimal amounts and linked records. [CanonicalWalletService](../app/Services/Wallet/CanonicalWalletService.php) |
| Game wallets | TRY/USD/EUR `primary_balance`, with the wallet pinned to the session. [Policy](../config/product_wallets.php) |
| Manual finance | Authorized admin actions, recent authentication, fingerprinting, and auditing. [Service](../app/Services/Finance/ManualFinanceOverrideService.php) |
| Promotion codes | Date and usage limits, repeat redemption checks, and canonical credit. [Controller](../app/Http/Controllers/Cms/Front/PromotionCodeController.php) |
| Internal finance services | Withdrawal identity, request-ledger, cancellation, and reconciliation components. These do not constitute a ready-to-use customer payout API. [Finance tests](../tests/Feature/Finance) |

## Internal Games

| Game | Code | Experience |
| --- | --- | --- |
| Dice | `dice` | Target and over/under selection |
| European Roulette | `roulette-european` | 0–36 table with multiple bet lines |
| Blackjack Classic | `blackjack-classic` | Hit, stand, and double |
| Blackjack Salon | `blackjack-salon` | A different table presentation using the shared Blackjack engine |
| SkyRush | `sky-rush` | Betting and cashout using server time |
| Orbit X | `orbit-x` | A crash variant with different timing and visual settings |
| Big Win | `big-win` | Reel and paytable-based rounds |
| Fusion Reels | `mini-slot-classic` | A distinct presentation in the mini-slot family |

Games use the existing session, access, maintenance, idempotency, and canonical finance contract. Guest previews create no economic records. Member demo and real entry flows use a wallet. [Game development guide](developer/INTERNAL_GAME_EXTENSION.md), [game settings](../config/internal_games.php), [engine tests](../tests/Unit/InternalGames).

## Integration and operations

| Feature | Contract |
| --- | --- |
| External Games | Provider alias, catalog import, launch, pinned session, and account/balance/bet/win/refund callbacks. [Adapter](developer/EXTERNAL_GAME_ADAPTER.md) |
| Built-in Sportsbook | Offer and quote validation, acceptance and debit, durable pending state, authorized rejection and cancellation, and linked refunds. General outcome settlement is disabled by default. [Routes](../routes/sportsbook.php), [pending and refund tests](../tests/Feature/Betting/SportsbookPendingRefundTest.php) |
| ChessBet connector | Bootstrap and exchange, exact origin, signed reserve/confirm/release, and result handoff. The private hosted engine is provided separately. [Iframe/API contract](developer/HOSTED_IFRAME_GAME.md) |
| Nova connector | Intent, authenticated polling, receipt claim, and BTC asset-wallet credit. Remote configuration is limited to BTC, with no automatic fiat conversion. [Nova API](developer/NOVA_PROVIDER_API.md) |
| Ops Radar | A redacted, read-only operational view of existing sources, governed by RBAC. [Read contract](OPS_RADAR_READ_CONTRACT.md) |

Affiliate, advanced CRM and reporting, and commercial operations are evaluated separately under Rise Full. AI assistants can interpret records and have no authority over permissions, funds, or game outcomes.

This source map does not certify real provider connectivity, real-money processing, or legal compliance. [Installation troubleshooting](TROUBLESHOOTING.md), [operating requirements](REVIEW_RIGHTS_AND_LIMITS.md).
