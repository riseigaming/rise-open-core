# External Game Adapter

The External Games connector links the provider catalog, launch sessions, and wallet callbacks to Rise. The implementing classes are `ExternalGameProviderConfig`, `ExternalGameCatalogImportService`, `ExternalGameSessionService`, `ExternalGameCallbackController`, and `ExternalGameCallbackService`.

| Direction | Endpoint |
|---|---|
| Rise admin | `GET /cms/admin/external-games?provider_alias=secondary-external-provider`, `POST /cms/admin/external-games/catalog` body `{"provider_alias":"secondary-external-provider"}` |
| Rise member | `POST /cms/external-games/{game}/launch` with body `{"playType":"real"}`, redirect/HTML launch at `GET /cms/external-games/play/{token}` |
| Rise → provider | `POST /api/v1/auth/authentication`, `GET /api/v1/games/all`, and `POST /api/v1/games/game_launch` are on the provider host |
| Provider → Rise | `POST /integrations/games/{public_alias}/wallet`. The legacy `POST /api/v1/external-games/callback/{command?}` is also retained for the primary connector. |

Example JSON for `POST /integrations/games/secondary/wallet`.

```json
{"method":"transaction_bet","agent_code":"<code>","agent_token":"<token>","agent_secret_key":"<secret>","session_token":"<issued-token>","user_id":42,"currency":"TRY","game":"fixture-game","round_id":"fixture-round","transaction_id":"fixture-bet-1","bet":"1.00"}
```

The default provider response is shaped like `{"status":1,"balance":499}`. Currency, echo, and balance formats can differ by wire profile. `status:0` can indicate an error in an HTTP 200 response. For example, `{"status":0,"error":"UNKNOWN_PROVIDER","message":"Unknown provider."}`. Account and balance reads use `account_details` and `user_balance`. A win uses `transaction_win` and `win`, and a refund uses `refund` and `amount`. Rise verifies the debit link and the total already refunded. Existing CSRF and admin catalog/RBAC and recent authentication rules apply to admin and browser routes. Provider callbacks use credentials and, when required, a signature.

**Minimal implementation.** Follow the existing second example's `EXTERNAL_GAMES_SECONDARY_*` configuration. Define unique internal and public aliases, independent endpoint and credentials, and exact origin and currency. The session is pinned to the registered provider and wallet. The canonical key consists of the provider alias, operation, and external transaction ID. Replaying the same payload returns the existing result. Provider A's credentials, session, and matching external ID cannot change provider B's financial records. See the [Ops Radar contract](../OPS_RADAR_READ_CONTRACT.md) for the event and health sources.

## Development checks

After setting up the [isolated test profile](../INSTALL_RUN34.md#8-isolated-test-profile), use `vendor/bin/phpunit --filter 'ExternalGameMultiProviderTest|ExternalGameTransferTest'`. Cover exact replay, conflicting payloads, cross-provider credentials and sessions, missing debits, and ledger rollback.

Verify the provider's actual inbound authentication and replay contract separately. Do not assume that the outbound signer validates inbound requests. Evaluate each provider's wire profile separately in the source. Preserve the existing `SyntheticLocalFinance` and prelaunch write gate. A local fixture does not establish acceptance of real funds or a commercial provider. Report security issues privately through [SECURITY.md](../../SECURITY.md).
