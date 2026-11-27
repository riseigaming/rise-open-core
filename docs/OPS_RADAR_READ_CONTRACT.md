# Ops Radar · developer and operator read contract

`GET /cms/admin/ops-radar` returns HTML using the existing CMS session. `Accept: application/json` returns the same redacted read model under the same RBAC rules. The optional filters are `provider`, `state`, and `reference`. Examples include `?provider=nova&state=pending` and `?reference=sportsbook:12`. An exact reference leads to the source record. The `state` filter selects matches only from the latest 30 source records. The provider health summary uses its own 24-hour callback counter.

| Code | Evidence |
|---|---|
| `observed` | A recorded session, callback, game result, or authorized audit event. This alone establishes no economic effect. |
| `applied` | The canonical transaction and a single positive applied ledger entry agree on account, owner, asset, exact amount, direction, and before/after values. Nova also requires a receipt claim tied to the same tenant. A Sportsbook refund also requires the original debit and its link to the refund. |
| `pending` | An open game, a bet slip awaiting a decision or funded and awaiting an outcome, or a Nova intent awaiting verification or local credit. |
| `protected` | A recorded External Games replay, or an existing Nova receipt claim linked to a different intent for the same tenant/session/invoice/network. A successful Nova no-op poll creates no durable replay record, so `attempt_not_recorded` is displayed separately. |
| `failed` | A recorded launch or callback error, a rejected transaction, or a gateway error or expiry. |
| `unknown` | Missing, conflicting, or contract-inconsistent evidence. |

Source references are `session:<id>` for an External Games session, `txn:<id>` for a canonical transaction, `sportsbook:<id>` for an acceptance, `round:<id>` for an Internal Games round, `chess:<id>` for a ChessBet reservation, and `audit:<id>` for an authorized CMS audit record. `ledger:<id>` is the local reference for the matching ledger entry. Rows omit free-form provider error text, credentials, launch URLs, callback payloads, user identifiers, and payment addresses. The screen links to authorized admin resources. Links to local write surfaces are hidden while the local finance gate is closed.

Provider operators see only provider and session records. The `finance.manual.override` permission grants access to finance, Sportsbook, Internal Games, ChessBet, and Nova evidence. The CMS content summary and authorized audit records are reserved for admins. Global status, the maintenance flag, and the finance control are read from existing records. Source permissions still apply.

## Adding an adapter

- An External Games adapter uses the registered alias, session relationship, alias-specific health counters, and canonical transaction/ledger prefix from the existing [adapter guide](developer/EXTERNAL_GAME_ADAPTER.md). Ops Radar reads from these sources. Provider health and reconciliation details remain on the existing authorized control screen.
- A payment connector preserves the canonical intent and transaction reference and the link between verified status and receipt claim in the [Payment Connector contract](developer/PAYMENT_CONNECTOR.md). Add a gateway only after explicitly mapping the fields in its verified contract.
- Ledger-only corrections, missing historical provenance, live provider probes, and detailed external service SLAs are outside this summary. Investigate ambiguous states in the source screen. Ops Radar provides no retry, refund, settlement, or provider write actions.
