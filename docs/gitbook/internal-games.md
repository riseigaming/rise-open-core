# Internal Games

The source includes eight internal game variants: Dice, European Roulette, Blackjack Classic, Blackjack Salon, SkyRush, Orbit X, Big Win, and Fusion Reels (Mini Slot Classic).

The operator controls catalog visibility and maintenance. The provider `config.kill_switch` is enforced at **new session admission and new gameplay actions**, while existing session readback and settlement retain the prior guarded path.

## Contract

- The server, not the client, authorizes game, owner, wallet, balance, country and allowed bet state.
- A guest preview does not create a real bet.
- Current original member demo/real entry points use existing account/finance rules; a new walletless demo engine is **not implied** by this documentation.
- Replayed economic requests must not create a second debit or credit.

[Internal Game Extension](https://github.com/riseigaming/rise-open-core/blob/main/docs/developer/INTERNAL_GAME_EXTENSION.md) · [Capability Map](https://github.com/riseigaming/rise-open-core/blob/main/docs/CAPABILITY_MAP.md).

Do not infer that the eight game-local contracts prove live RNG certification, real-provider acceptance or gambling-law compliance.
