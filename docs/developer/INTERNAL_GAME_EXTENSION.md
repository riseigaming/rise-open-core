# Internal Game Extension

The Dice example uses `InternalGameCatalog`, `InternalGameSessionService`, `DiceController`, and `DiceService` for catalog and game services. Start the first integration with the existing engine, catalog, and canonical finance contract.

| Rise endpoint | Contract |
|---|---|
| `GET /casino`, `GET /cms/games/{slug}` | Catalog and visible game page |
| `POST /cms/games/dice/launch` | Form or JSON with `{"playType":"demo"}` or `{"playType":"real"}`. Guest demo returns an HTML preview. Members receive a 302 redirect to the existing session's game page. |
| `GET /cms/internal-games/play/{sessionToken}` | HTML bound to the browser session and user |
| `POST /cms/internal-games/dice/roll` | Member, CSRF, and session token. See the example below. |
| `GET /cms/internal-games/dice/snapshot?sessionToken=<uuid>` | Reads back state for the same session |

```json
{"sessionToken":"<uuid-from-launch>","requestKey":"dice-request-001","betAmount":"1.00","target":"50.00","mode":"over"}
```

An example success response is `{"status":200,"data":{"roundId":"<round>","requestKey":"dice-request-001","betAmount":1,"winAmount":0,"balance":9,"result":{"mode":"over","target":50,"rolled":30,"won":false}}}`. The server computes the result. The client does not send wallet, currency, balance, demo flag, or result. An invalid payload returns 422, an unbound user or session 403, an expired session 410, and an unavailable game or catalog 404 or 503. The existing chain enforces country, maintenance, and rate limits.

**Minimal implementation.** Follow the mapping in `InternalDiceDemoSeeder` and `config/internal_games.php`. The existing provider `internal-games` and `reference_id=internal:<code>` match the configured slug and supported asset policy. If new behavior is needed, add a narrowly scoped adaptation to the existing controller dispatch and engine structure. Open the member session through `InternalGameSessionService` and use the `SpendableWalletService` exact decimal and ledger flow through the same `SpendableWalletContext`. Keep the round, transaction, ledger, and balance change in the same database transaction. Retry after success or failure with the same `requestKey` and the same input. A different payload with the same key cannot produce a new result. Guest preview creates no persistent round, virtual balance, or economic record.

## Game families and checks

Catalog and configuration mappings can reuse the same engine with different presentations, as in Blackjack Classic/Salon, SkyRush/Orbit X, and Big Win/Fusion Reels. European Roulette uses a separate table and bet-line contract. See the [eight variants](../CAPABILITY_MAP.md#internal-games) and [settings](../../config/internal_games.php).

The existing provider's `config.kill_switch` restricts new session and gameplay acceptance. Existing session readback and settlement retain their own guarded paths. Apply these controls to the new game too.

With the [isolated test profile](../INSTALL_RUN34.md#8-isolated-test-profile), use `vendor/bin/phpunit --filter 'CanonicalInternalGameEntryTest|DiceDemoTest'`. Check invalid amounts, unbound users or browsers, disabled games, exact replay, payload conflicts, and audit or ledger rollback. The Dice example defines no general plugin registration API.
