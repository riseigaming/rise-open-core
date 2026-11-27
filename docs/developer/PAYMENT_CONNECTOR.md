# Developing a payment connector

The existing Nova connector shows how a gateway links a payment intent to the canonical asset wallet. Nova Gateway is a separate service, and its private engine is outside this repository. See the [Nova API reference](NOVA_PROVIDER_API.md) for endpoints and environment fields.

## Transaction flow

1. With a CMS session and CSRF, send the UUID `request_key` and fiat reference `amount` to `POST /cms/wallet/nova`.
2. `NovaDepositService` binds the intent to the user, gateway tenant, and rail. The same key and amount reuse the same intent.
3. `NovaGatewayClient` creates a session at the gateway. An ambiguous timeout does not justify creating a new economic reference.
4. The same owner starts authenticated polling with `POST /cms/wallet/nova/{reference}/recheck`. This call can write a credit and must not be used as a read-only check.
5. After receipt and indexed output claim verification, `AssetWalletService` writes the canonical credit and ledger entry atomically.

The string `COMPLETED` alone is insufficient. Verify the tenant, session, invoice, network, quote, exact amount, claim, and finance and settlement gates together. Rise has no inbound Nova callback route.

## Adapting a new gateway

Keep the new gateway's versioned create and status client separate. Explicitly map the provider and tenant identities, intent, rail, verified output identifier, and final status to the existing contract. Do not create a second wallet or ledger. BTC asset credit does not automatically convert to a fiat gameplay balance.

The existing extension points are listed below.

- [NovaDepositService](../../app/Services/Payments/NovaDepositService.php) handles intents and polling.
- [AssetWalletService](../../app/Services/Wallet/AssetWalletService.php) handles canonical asset credits.

A different provider protocol requires a focused adapter and tests. Do not assume a ready-made general adapter for a second payment provider. Remote sessions and remote credits have separate gates. Local development provides neither a provider agreement nor real-money approval.

## Testing approach

If the [isolated test profile](../INSTALL_RUN34.md#8-isolated-test-profile) is ready, start with `vendor/bin/phpunit --filter NovaDepositTest`. Verify that pending creates no credit. Check exact replay, changed amounts, a different owner, incorrect asset/network/receipt, duplicate output claims, and rollback on a ledger error. Keep secrets and receipt-related personal data out of logs and reports.
