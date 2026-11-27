# Hosted iframe and wallet API contract

ChessBet is a separately provided hosted B2B gaming service. The Rise connector opens the iframe session and processes signed server-to-server wallet requests. Rise retains authority over users, canonical wallets, transactions, and the ledger. This repository excludes the hosted engine.

## Configuration and session

Map the fields in [config/chess_wallet.php](../../config/chess_wallet.php) and [.env.example](../../.env.example) to the partner's approved endpoint and origin values.

| Setting | Usage |
| --- | --- |
| `CHESS_WALLET_PARTNERS_JSON` | Partner code and server-side wallet permissions |
| `CHESS_EMBED_EXCHANGE_ENDPOINT` | Partner session exchange address |
| `CHESS_EMBED_KEY_ID`, `CHESS_EMBED_SECRET` | Server-only exchange credentials |
| `CHESS_EMBED_PUBLIC_BASE_URL`, `CHESS_EMBED_CONSUME_PATH` | Hosted consume URL, with default path `/embed/consume` |
| `CHESS_EMBED_PARENT_ORIGIN`, `CHESS_EMBED_CHILD_POST_MESSAGE_ORIGIN` | Exact parent and child origin mapping |
| `CHESS_EMBED_BOOTSTRAP_TTL_SECONDS` | Bootstrap lifetime, 120 seconds by default |

`GET /cms/providers/chess` creates no wallet bootstrap or bridge token for guests. For members, Rise prepares a short-lived bootstrap from the current user, wallet, and locale context. The server sends the following payload to the exchange endpoint.

```json
{"keyId":"<partner-key-id>","secret":"<server-secret>","externalUserId":"42","requestedSurfaceType":"PUBLIC","requestedRole":"VIEWER","locale":"tr"}
```

The partner response contains `sessionToken` and `expiresAt`. Rise uses the token in the consume URL. Map the endpoint, hostname, and optional profile fields to the partner contract. Keep the secret out of browser code and preserve the frame and postMessage origin checks.

See [ChessEmbedService](../../app/Services/ChessWallet/ChessEmbedService.php) and the [routes](../../routes/chess.php).

## Request signature

All wallet requests carry the `x-partner-code`, `x-request-id`, `x-timestamp`, and `x-signature` headers. Requests other than GET also require `idempotency-key`. The timestamp is UTC Unix time in seconds. The default tolerance is 300 seconds and is set in the partner configuration.

Join the seven fields below with LF to form the canonical text. There is no trailing LF.

```text
UPPERCASE_METHOD
/path/without/query
partner-code
request-id
idempotency-key-or-empty
timestamp
sha256-of-raw-body
```

The signature is the lowercase hexadecimal output of `HMAC-SHA256(canonical_text)` using the partner secret. Serialize the JSON once and hash the exact bytes to be sent. Sign again if the body changes. A retry uses the same economic intent and idempotency key. Generate a valid timestamp and signature.

See [ChessWalletSignature](../../app/Services/ChessWallet/ChessWalletSignature.php) and the [authentication middleware](../../app/Http/Middleware/ChessWalletAuthenticate.php).

## Wallet operations

| Method / path | Input and behavior |
| --- | --- |
| `POST /wallet/reservations/reserve` | `externalUserId`, `placementIntentId`, and positive `amount`. The server checks currency, wallet, and market/quote context. |
| `POST /wallet/reservations/confirm` | `partnerCode`, `externalUserId`, body `idempotencyKey`, and ISO-8601 `requestTimestamp`, with `placedBetId` and `marketInstanceId` for placement context |
| `POST /wallet/reservations/release` | `cmsReservationId`. Linked refund based on the current hold and placement state. |
| `GET /wallet/reservations/{cmsReservationId}` | Reservation, placement, hold, and result state |
| `GET /wallet/balance` | Query `externalUserId` and optional `currency`. Returns `balanceExact` and wallet identifiers. |
| `GET /wallet/health` | Partner/provider configuration status |
| `POST /wallet/settlements/handoff` | `cmsReservationId`, `placedBetId`, `resultStatus`, the `settlementSummary` object, and ISO-8601 `settledAt`. WIN requires a positive, exact fiat `payoutAmount`. |

In this reserve example, use identifiers from the existing partner, market, and quote records.

```json
{"externalUserId":"42","placementIntentId":"order-001","amount":"10.01","currency":"TRY","marketInstanceId":"<market-id>","selectionId":"<selection-id>","quoteExpiresAt":"<future-ISO-8601>","walletAccountId":7}
```

A successful reserve response contains `cmsReservationId` and `balanceExact`. Reservation lookup returns `sourceOfTruth: "cms"`, `reservationStatus`, `holdState`, and `resultStatus`. Outcomes follow the WIN/LOSS/VOID contract. An admin cockpit action confers no partner settlement authority.

Missing or invalid authentication returns 401. If the wallet is not ready, the response is 503 with `CHESS_WALLET_NOT_READY`. For operation errors, read `status`, `reasonCode`, and `message` together with the HTTP code. After an ambiguous timeout, look up the reservation without creating a new economic intent. Do not reuse the same intent with a different wallet, owner, amount, or outcome.

Existing `SyntheticLocalFinance`, prelaunch write gate, partner bindings, and transaction/ledger controls apply. Hosted service access requires a separate agreement. A local fixture does not establish real-money or provider acceptance.

## Development checks

After setting up the [isolated test profile](../INSTALL_RUN34.md#8-isolated-test-profile), use `vendor/bin/phpunit --filter 'ChessEmbedCockpitBoundaryTest|ChessCanonicalFinanceTest'`. Cover invalid signatures and timestamps, cross-partner access, exact and conflicting replays, and ledger rollback. See [ChessWalletReservationService](../../app/Services/ChessWallet/ChessWalletReservationService.php) for operation details.
