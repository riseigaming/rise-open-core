# vFair Dice attribution

Upstream: https://github.com/vfairgames/vfair-games

Pinned revision: `ccdb1b2f8bbfbe6249e36579e11523e6d1ed7706`.

License: Apache-2.0; the upstream LICENSE is reproduced unchanged alongside this
file. No upstream NOTICE file was present in the pinned repository tree.

Selectively adapted source:

- `libs/game-math/src/dice/dice-math.ts`: probability, multiplier and equality
  semantics (`under < target`, `over >= target`).
- `libs/game-math/src/dice/dice-provably-fair.ts`: unsigned big-endian 32-bit
  hash-to-10,000-bucket mapping only.

Modified files: `app/Services/InternalGames/DiceEngine.php` is a PHP adaptation,
not an unchanged upstream copy. It uses integer minor-unit gross payouts,
flooring once, and deliberately bounded probabilities (1–97%) for the original CMS demo and wallet-backed modes. The RTP is
98%, as in upstream's default. Test vectors and exhaustive bucket tests live
in `tests/Unit/InternalGames/DiceEngineTest.php`.

No upstream application framework, wallet, APIs, brands, images, fonts or audio
were imported. The operator-supplied screenshot is a layout reference only.
Rise's SVG card/pattern and Web Audio tones are original; the typography reuses
the CMS's existing local Manrope assets.

The runtime uses server CSPRNG output with the adapted bucket mapping. It does
NOT implement vFair's seed commitment/reveal/verification lifecycle and must
NOT be presented as "provably fair". Guest preview creates no wagering session or financial records. Member
demo/real entry points use Rise's canonical wallet, ledger and session controls;
the demo label does not create a separate virtual balance. No vFair financial
services are imported. The source attribution does not imply endorsement.
Neither mode claims independent provably-fair certification.
