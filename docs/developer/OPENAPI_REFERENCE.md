# Developer HTTP reference

This Markdown reference describes selected existing routes and parameters. It provides no executable OpenAPI schema. Do not add an extra `/api/v1` prefix or a general bearer-token contract to CMS endpoints. [Installation](../INSTALL_RUN34.md) · [Request examples](INTEGRATION_EXAMPLES.md).

## Authorization and request direction

| Surface | Existing boundary |
| --- | --- |
| Browser → Rise | CMS cookie/session, existing CSRF for POST, and site, country, maintenance, and locale checks. The game session is bound to the account and browser. The client has no authority over balances or outcomes. |
| Rise admin | `catalog.providers.manage` for the catalog and `finance.manual.override` for Nova finance reads. The External Games catalog POST also requires `cmsRecentAuth`. |
| Provider → Rise External callback | Explicitly exempt from browser CSRF. Uses the provider alias and existing payload credential and signature verification. A browser cookie or arbitrary bearer token cannot substitute for those checks. |
| Rise → Nova/provider | Separate outbound connector contract. The Nova `/api/external/crypto/*` routes below belong to Nova. |

Route sources include [base_catalog.php](../../routes/base_catalog.php), [internal_games.php](../../routes/internal_games.php), [external_games.php](../../routes/external_games.php), and [canonical_finance.php](../../routes/canonical_finance.php). External Games browser, admin, and callback groups fall under the existing `SyntheticLocalFinance` → `PrelaunchFinanceWriteGate` boundary. Nova has separate gates for connection, remote sessions, and credits.

## Internal Games: Dice

| Method / Rise path | Input / output |
| --- | --- |
| `GET /casino`, `GET /cms/games/{slug}` | HTML catalog or game page. |
| `POST /cms/games/dice/launch` | `playType` is `demo` or `real`, subject to the existing access policy. Guest demo returns an inert HTML preview. An authorized member launch redirects to the existing play page. |
| `GET /cms/internal-games/play/{sessionToken}` | HTML bound to the account and browser. Obtain the token from launch. |
| `POST /cms/internal-games/dice/roll` | UUID `sessionToken`, `requestKey` of 8–100 ASCII letters, digits, `_` or `-`, decimal `betAmount` and `target`, and `mode=under|over`. After controller validation, the engine checks stake and chance limits. |
| `GET /cms/internal-games/dice/snapshot?sessionToken=<uuid>` | `status:200` for the same session, with currency, balance, limits, and history in `data`. This read performs no economic operation, and the existing web session chain applies. |

The roll response has `status:200` and includes `roundId`, `requestKey`, `betAmount`, `winAmount`, `balance`, and `result` in `data`. The server computes the outcome. The example guarantees no particular win or balance. Roll inputs may not include `isDemo`, `is_demo`, `playType`, `currency`, `wallet`, `wallet_account_id`, `balance`, or `result`. A replay with the same session/key and stake/mode/target reads the same round. A conflicting payload returns 409, and a busy lock can also return 409. Invalid amounts or insufficient balance return 422, an unbound account or browser 403, a closed or expired session 410, a hidden game 404, and maintenance 503. CSRF (419) and route throttling also apply.

Guest preview creates no persistent session, round, or balance. Current member demo and real entry flows use the canonical wallet. A `demo` label does not rule out an economic transaction. See [GameController](../../app/Http/Controllers/Cms/Front/GameController.php), [DiceController](../../app/Http/Controllers/Cms/Front/DiceController.php), and [DiceService](../../app/Services/InternalGames/DiceService.php). Relevant tests are [DiceDemoTest](../../tests/Feature/InternalGames/DiceDemoTest.php) and [CanonicalInternalGameEntryTest](../../tests/Feature/InternalGames/CanonicalInternalGameEntryTest.php). For other game families, see the [extension guide](INTERNAL_GAME_EXTENSION.md).

## External Games callbacks by connector

| Method / Rise path | Contract |
| --- | --- |
| `POST /cms/external-games/{game}/launch` | Requires an existing game record and a member. The existing launch policy validates `playType`. |
| `GET /cms/external-games/play/{token}` | Launch bound to the registered provider and wallet and the allowed iframe origin. |
| `GET /cms/admin/external-games?provider_alias=<alias>` | Admin read with catalog permission. |
| `POST /cms/admin/external-games/catalog` | `provider_alias` is an existing connector alias. Requires recent authentication and RBAC. May make an outbound call to the provider. |
| `POST /integrations/games/{provider_alias}/wallet` | The alias in the URL is public. The server resolves it to the internal connector alias. |
| `POST /api/v1/external-games/callback/{command?}` | Legacy endpoint retained for the primary connector only. |

Provider wallet methods are `account_details`, `user_balance`, `transaction_bet`, `transaction_win`, and `refund`. The payload credential fields are `agent_code`, `agent_token`, and `agent_secret_key`. Requests use the issued `session_token` along with `user_id`, `currency`, `game`, and `round_id`, plus `transaction_id` for mutations. The bet field is `bet`, the win field is `win`, and the refund field is `amount`. Read [ExternalGameCallbackService](../../app/Services/ExternalGames/ExternalGameCallbackService.php) and [ExternalGameProviderConfig](../../app/Services/ExternalGames/ExternalGameProviderConfig.php) for additional wire requirements by profile. Do not guess the signature algorithm or credential placement.

The default provider response includes `status:1` and `balance`. Errors use `status:0` and may arrive in an HTTP 200 response. For an unknown public alias, the controller returns `{"status":0,"error":"UNKNOWN_PROVIDER","message":"Unknown provider."}`. HTTP 200 alone does not establish success. The legacy `authenticate|balance|changebalance` branch has a separate signature and error format from this wallet-method example.


Provider alias, session, wallet, and currency bindings remain pinned. The economic idempotency key consists of the provider prefix, operation, and external transaction ID. The server verifies the prior debit link and total refund limit. Credentials or a session for one connector cannot change another connector's balance. Existing transaction and ledger, audit and redaction, and provider health flows remain in effect. See the [callback controller](../../app/Http/Controllers/Api/ExternalGameCallbackController.php), [ExternalGameMultiProviderTest](../../tests/Feature/ExternalGames/ExternalGameMultiProviderTest.php), [ExternalGameTransferTest](../../tests/Feature/ExternalGames/ExternalGameTransferTest.php), and the [adapter guide](EXTERNAL_GAME_ADAPTER.md). This local contract does not establish QA against a real provider.

## Payments through the Nova customer API and outbound connector

| Method / Rise path | Contract |
| --- | --- |
| `GET /cms/wallet/nova` | Member access under payment country and product policy. Returns an HTML intent list. |
| `POST /cms/wallet/nova` | CSRF, UUID `request_key`, and string `amount` of at least 1 with up to two decimal places. Throttled to 10 per minute. The amount is a fiat price reference. |
| `POST /cms/wallet/nova/{reference}/recheck` | Same owner and CSRF, with authenticated gateway polling. Throttled to 30 per minute. This operation can write a credit. |
| `GET /cms/admin/finance/nova` | Existing finance read with a session and `finance.manual.override`. |

With `Accept: application/json`, create and recheck return `reference`, `status`, `asset`, `amount`, and `payment`. A form request redirects to the list. Pending is `status:2` and credited is `status:1`. The response `amount` is the asset amount, distinct from the fiat `amount` input. The same UUID and amount reuse the same intent. A conflicting amount returns 409, and another owner's reference returns 404. A receipt, asset, network, or amount mismatch returns 422, and missing gateway availability or permission returns 503. An invalid payload returns 422. CSRF and country and product restrictions also apply.

Outbound routes on the Nova host are `GET /api/external/crypto/rails/btc/readiness`, `POST /api/external/crypto/session`, and `GET /api/external/crypto/transaction/{reference}`. They use Basic Auth, with `siteSecret` in the create body and `X-Site-Secret` on GET requests. Do not wire them as inbound callbacks to Rise. Rise has no inbound Nova callback. Remote configuration is limited to BTC/mainnet/LIVE_HTTP, and readiness, session, and credit permissions remain separate.

Credit is created only after a verified `COMPLETED` receipt passes tenant, session, invoice, network, quote, exact amount, and indexed output claim checks. The credit is written atomically to the canonical **BTC asset wallet** and ledger, with no conversion to a TRY/USD/EUR gameplay balance. The LTC rail definition provides no live remote LTC path. See [NovaDepositController](../../app/Http/Controllers/Cms/Front/NovaDepositController.php), [NovaDepositService](../../app/Services/Payments/NovaDepositService.php), [NovaGatewayClient](../../app/Services/Payments/NovaGatewayClient.php), and [NovaDepositTest](../../tests/Feature/Finance/NovaDepositTest.php). Details are in the [Nova API](NOVA_PROVIDER_API.md) and [Payment Connector](PAYMENT_CONNECTOR.md) guides.

For ChessBet, use the [hosted iframe/API contract](HOSTED_IFRAME_GAME.md). For built-in Sportsbook, use the [capability map](../CAPABILITY_MAP.md). Internal payout, cancellation, and reconciliation services define no new customer withdrawal or provider dispatch endpoint. Do not open unsupported write gates.
