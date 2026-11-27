# External game adapters

Rise has a catalog/launch and canonical wallet callback contract for external game providers. Each provider must have distinct alias, host, credential, origin, session, wallet and transaction identity.

## Connect a provider

Configure a unique internal alias and public callback alias, separate endpoint credentials, exact iframe origin and supported currency. Import the catalog, then launch a session pinned to that provider and canonical wallet.

Match the provider's actual inbound authentication and replay contract to the selected source profile. Preserve existing finance, access and maintenance gates. Outbound signing is a separate contract. Report security concerns privately through [SECURITY.md](https://github.com/riseigaming/rise-open-core/blob/main/SECURITY.md).

A provider may send a successful HTTP status with a failed response body. The result status and canonical ledger evidence determine the outcome.

For each provider, test catalog, launch, account/balance, bet/win/refund, malformed signature, expired session, duplicate callback and cross-provider ID conflict.

[External Game Adapter](https://github.com/riseigaming/rise-open-core/blob/main/docs/developer/EXTERNAL_GAME_ADAPTER.md) · [HTTP contract](https://github.com/riseigaming/rise-open-core/blob/main/docs/developer/OPENAPI_REFERENCE.md).
