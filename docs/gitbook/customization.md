# Customize a fork

Customize using the existing CMS and documented adapter boundaries instead of rewriting the canonical wallet/ledger.

## Safe customization order

1. Fork and clone the public repository. Pin the base commit.
2. Make a dedicated branch for your design or connector change.
3. Use CMS Global/admin settings for title, navigation, pages, promos and supported themes before editing framework code.
4. When adding a game/provider, preserve product visibility, country access, permissions, provider identity and finance gate.
5. Run the narrowest tests for changed behavior plus any relevant negative/replay cases.
6. Keep your business credentials and operator data outside Git history.
7. Use [Updates](updating.md) to merge future upstream releases without losing your own changes.

The base product uses Laravel and a single canonical finance authority. Avoid maintaining a second internal finance engine simply because a storefront needs a different UI.

Developer source contracts:
[Internal Game Extension](https://github.com/riseigaming/rise-open-core/blob/main/docs/developer/INTERNAL_GAME_EXTENSION.md) ·
[External Game Adapter](https://github.com/riseigaming/rise-open-core/blob/main/docs/developer/EXTERNAL_GAME_ADAPTER.md) ·
[HTTP Reference](https://github.com/riseigaming/rise-open-core/blob/main/docs/developer/OPENAPI_REFERENCE.md).

Brand logos and trademarks are not automatically relicensed by Apache-2.0. Replace marks appropriately for your own brand.
