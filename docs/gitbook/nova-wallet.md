# Nova and canonical wallet

Nova is an **optional** private gateway service connector, not an included open-source payment engine.

The connector creates an intent, polls the authenticated gateway, verifies its receipt and writes one canonical crypto asset-wallet credit. The provider backend is separate. Remote configuration is limited to BTC/mainnet; an LTC rail definition does not enable remote LTC credit. USDT deposit and settlement are disabled.

## Financial separation

- A fiat amount submitted for a quote is a **reference amount**, not proof of settled fiat.
- Units credited to a crypto asset wallet do not automatically become TRY/USD/EUR gameplay spending money.
- A pending intent or successful HTTP response does not authorize credit.
- Successful funds require verified owner, tenant, session, network, exact amount, receipt and one canonical claim/ledger effect.
- Remote BTC/LTC and withdrawal/production operations require independent acceptance and permissions.

Never paste API credentials into GitBook, source code or support tickets.

[Payment Connector](https://github.com/riseigaming/rise-open-core/blob/main/docs/developer/PAYMENT_CONNECTOR.md) · [Nova Provider API](https://github.com/riseigaming/rise-open-core/blob/main/docs/developer/NOVA_PROVIDER_API.md).
