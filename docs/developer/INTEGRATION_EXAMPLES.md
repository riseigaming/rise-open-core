# Integration examples

Replace token, identifier, and credential placeholders with values from your isolated fixtures. Browser requests use the same CMS session and valid CSRF. Provider requests follow their own authentication contract. See the [parameter reference](OPENAPI_REFERENCE.md).

## Dice roll and snapshot

A guest demo launch from `/cms/games/dice` opens a preview that creates no economic records. Member demo and real entry flows use the canonical wallet. In the same browser, send `{"playType":"demo"}` to `POST /cms/games/dice/launch` and retrieve the session token from the redirect.

`POST /cms/internal-games/dice/roll`, `Accept: application/json`:

```json
{"sessionToken":"<uuid-from-launch>","requestKey":"dice-request-001","betAmount":"0.20","target":"50.00","mode":"over"}
```

The server returns `status:200` and includes `roundId`, `requestKey`, `betAmount`, `winAmount`, `balance`, and `result` in `data`. The server computes the result. The client does not send the wallet, currency, balance, or result.

`GET /cms/internal-games/dice/snapshot?sessionToken=<same-uuid>` reads currency, history, and balance. The same key and input read the same round. A different amount with the same key can return 409, a different browser or account 403, an expired session 410, and missing CSRF 419.

See [DiceDemoTest](../../tests/Feature/InternalGames/DiceDemoTest.php), [CanonicalInternalGameEntryTest](../../tests/Feature/InternalGames/CanonicalInternalGameEntryTest.php), and the [Internal Games guide](INTERNAL_GAME_EXTENSION.md).

## External provider callback

First use the provider catalog and launch fixture to create a session pinned to that provider and wallet. In this example, `secondary` is the public alias and `secondary-external-provider` is the internal configuration alias.

`POST /integrations/games/secondary/wallet`:

```json
{"method":"transaction_bet","agent_code":"<fixture-code>","agent_token":"<fixture-token>","agent_secret_key":"<fixture-secret>","session_token":"<issued-session>","user_id":42,"currency":"TRY","game":"fixture-game","round_id":"fixture-round","transaction_id":"fixture-bet-001","bet":"1.00"}
```

Match the user ID and game and round identifiers to the fixture. Keep credentials out of browser requests. The callback is exempt from CSRF and still subject to provider and session checks, `SyntheticLocalFinance`, and prelaunch finance controls.

The default response includes `status:1` and the server's `balance`. `status:0` can indicate an error in an HTTP 200 response. An exact replay of the same provider, operation, and transaction ID produces no second debit. For `transaction_win`, the amount is in `win`. For `refund`, it is in `amount`. The server checks the original debit and the total refund limit.

In negative tests, cover provider A's credential with provider B's session, a wrong public alias, mismatched currency, a win without a debit, and a changed replay payload. See the [multi-provider fixture](../../tests/Feature/ExternalGames/ExternalGameMultiProviderTest.php) and [adapter contract](EXTERNAL_GAME_ADAPTER.md).

## Nova intent and polling

Start with an HTTP fake in your isolated test profile. Nova is disabled on a clean installation. Use the CMS session and CSRF for `POST /cms/wallet/nova` with `Accept: application/json`.

```json
{"request_key":"b4c6124a-d4c8-4892-bc9f-f2ae05c211ba","amount":"100.00"}
```

The input amount is a fiat reference. The response contains `reference`, `status`, `asset`, `amount`, and `payment`. The response `amount` is the crypto asset amount. A pending intent alone creates no credit.

With the same owner and CSRF, `POST /cms/wallet/nova/{reference}/recheck` starts authenticated polling and **can write a credit** after the receipt is verified. This endpoint is unsuitable for a read-only smoke test. Rechecking the same intent must produce no second credit. Another owner's reference returns 404.

Inspect the link between the BTC asset wallet, transaction, ledger, and receipt claim. The fiat gameplay balance stays the same. Check duplicate output claims, incorrect network, amount, or tenant, and rollback on a ledger insert failure. See the fixture in [NovaDepositTest](../../tests/Feature/Finance/NovaDepositTest.php) and the [Nova API](NOVA_PROVIDER_API.md).

## Running tests

For fixtures that use the database, first set up the [isolated test profile](../INSTALL_RUN34.md#8-isolated-test-profile). Limit the test filter to the changed contract.

```sh
vendor/bin/phpunit --filter 'CanonicalInternalGameEntryTest|DiceDemoTest'
vendor/bin/phpunit --filter 'ExternalGameMultiProviderTest|ExternalGameTransferTest'
vendor/bin/phpunit --filter NovaDepositTest
```

Fixture results do not establish acceptance of a real provider or real money. Do not derive remote permissions or credentials from these examples. Keep secrets, cookies, and customer data out of reports.
