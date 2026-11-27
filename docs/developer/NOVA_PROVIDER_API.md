# Nova Gateway API

Nova is a separate optional crypto payment service with a connector in Open Core. The gateway engine and service account are provided separately. The connector creates intents, polls with authentication, verifies receipts, and credits the canonical asset wallet.

## Rise customer and admin routes

| Method / path | Parameters and authorization |
| --- | --- |
| `GET /cms/wallet/nova` | CMS member session and payment country and product policy |
| `POST /cms/wallet/nova` | CSRF, UUID `request_key`, and string `amount` of at least 1 with up to two decimal places |
| `POST /cms/wallet/nova/{reference}/recheck` | Same owner and CSRF. Polls the gateway and may write a credit. |
| `GET /cms/admin/finance/nova` | Read access with `finance.manual.override` |

```json
{"request_key":"b4c6124a-d4c8-4892-bc9f-f2ae05c211ba","amount":"100.00"}
```

The response to `Accept: application/json` includes `reference`, `status`, `asset`, `amount`, and `payment`. A form request redirects to the list. The input `amount` is a fiat price reference. The response `amount` is the crypto asset amount. Pending is `status=2`, and credited is `status=1`.

The same request key and amount reuse the same intent. A changed amount returns 409, another owner's reference 404, an invalid payload or receipt 422, and an unavailable gateway 503. Do not open a new intent because of a timeout. A pending payment creates no credit.

## Rise → Nova

These routes are on the **Nova host**.

| Method / path | Usage |
| --- | --- |
| `GET /api/external/crypto/rails/btc/readiness` | Tenant fingerprint and BTC rail preflight check |
| `POST /api/external/crypto/session` | Versioned payment session request |
| `GET /api/external/crypto/transaction/{reference}` | Authenticated status and receipt read |

The client uses Basic Auth, with `siteSecret` in the create body and `X-Site-Secret` in GET requests. It expects `status: "Success"` and a `data` object. Create requires HTTP 200/201, and status requires HTTP 200. Rise has no inbound Nova webhook route.

## Configuration

Key fields in [config/nova_payments.php](../../config/nova_payments.php) are `NOVA_PAYMENTS_URL`, `NOVA_PAYMENTS_USERNAME`, `NOVA_PAYMENTS_PASSWORD`, `NOVA_PAYMENTS_SITE_SECRET`, `NOVA_PAYMENTS_FIAT`, and `NOVA_PAYMENTS_RAIL`. Keep secrets only in the private server environment.

`NOVA_PAYMENTS_ENABLED`, `NOVA_PAYMENTS_REMOTE_SESSIONS_ENABLED`, and `NOVA_PAYMENTS_REMOTE_CREDIT_ENABLED` are disabled by default. Remote configuration requires an exact, authorized HTTPS authority, the BTC rail, mainnet, and `LIVE_HTTP`. Use `php artisan nova:connection-check` for a connection preflight. It provides no evidence of a completed payment or credit. This guide grants no authorization to open write gates.

Keep the remote BTC/BTC contract distinct from the LTC/LTC and USDT/TRC20 rail definitions. The remote client is limited to BTC, and USDT deposits and settlement are disabled. The customer's fiat currency must be one of TRY/USD/EUR and match the configured fiat currency. There is no general country × method × currency admin settings matrix.

## Credits and asset separation

An authenticated `COMPLETED` result produces a credit only after verification of the tenant, session, invoice, network, quote, exact amount, and indexed receipt claim bindings. The same on-chain output cannot fund two intents. The BTC asset-wallet credit, transaction, and ledger entry are written atomically.

BTC credit does not automatically convert to a TRY/USD/EUR gameplay balance. General crypto→fiat conversion and customer withdrawal or provider dispatch APIs are outside this contract. Local fixtures do not establish real-funds acceptance.

See [NovaGatewayClient](../../app/Services/Payments/NovaGatewayClient.php), [NovaDepositService](../../app/Services/Payments/NovaDepositService.php), the [controller](../../app/Http/Controllers/Cms/Front/NovaDepositController.php), [NovaDepositTest](../../tests/Feature/Finance/NovaDepositTest.php), and the [payment connector guide](PAYMENT_CONNECTOR.md).
