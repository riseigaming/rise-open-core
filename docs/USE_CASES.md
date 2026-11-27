# Rise use cases

These examples start with existing Open Core features. Use isolated local data for installation and development. Real funds and remote providers are subject to separate agreements and operating requirements.

| Goal | How to start |
| --- | --- |
| Set up a local development environment | Extract the exact revision into a clean export, verify the manifest, and follow the [installation guide](INSTALL_WIZARD.md). |
| Set up a branded site | Save the title, logo, menus, sliders, and accent color in the CMS. Check the result in a separate guest session. [First use](FIRST_HUMAN_TEST.md) |
| Publish multilingual content | Prepare TR/EN/ES titles and body text for the content page, and open the menu destinations in each language. New language metadata does not translate text. |
| Create different game presentations | Review the catalog and configuration mappings for the existing Blackjack, crash, or slot family. [Internal Games](developer/INTERNAL_GAME_EXTENSION.md) |
| Develop a Dice request | Send a roll with the launch session token and a unique request key. Compare a replay of the same input against the snapshot. [Examples](developer/INTEGRATION_EXAMPLES.md) |
| Connect a second game provider | Define a separate alias/public alias, credentials, endpoint, origin, and currency. [External adapter](developer/EXTERNAL_GAME_ADAPTER.md) |
| Connect hosted ChessBet | Implement the server-to-server session exchange and signed wallet contract. [Hosted iframe](developer/HOSTED_IFRAME_GAME.md) |
| Create a payment intent | Review the Nova request key, fiat reference, and authenticated status poll. A BTC asset credit does not count as a fiat gameplay balance. [Payment Connector](developer/PAYMENT_CONNECTOR.md) |
| Try a promotion code | Create a limited code as an authorized admin. Redeem it as a sample member, and verify that repeat redemption adds no credit. |
| Investigate a transaction | Use the provider/state/reference filters in Ops Radar and open the source transaction and ledger. [Ops Radar](OPS_RADAR_READ_CONTRACT.md) |
| Review a pending bet slip | Read the Sportsbook acceptance record and its link to the original debit. Use the authorized cancellation or rejection flow. Do not manufacture a missing result. |
| Update a fork | Merge the reviewed upstream revision, then check customizations and relevant tests. [Updating](../UPDATING.md) |
| Use AI to understand a flow | Ask for an explanation of the route, controller, service, and test sequence with source links. [AI guide](AI_AGENT_ONBOARDING.md) |

See the [product portfolio](PRODUCT_PORTFOLIO.md) for Rise Full scope and optional services, and the [HTTP reference](developer/OPENAPI_REFERENCE.md) for API fields.
