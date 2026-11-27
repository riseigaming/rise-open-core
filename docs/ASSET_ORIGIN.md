# Rise-authored game assets and internal code lineage

The project maintainer identifies the following Rise Open Core game work as
original Rise-authored code and assets (including work produced with coding
assistants), not as assets copied under an external commercial content licence:

- SkyRush and its Orbit X variant: game engine, presentation and artwork.
- Mini Slot and Big Win: local symbols, graphics, animations and audio.
- Internal Blackjack, Roulette and other first-party game presentations.
- Rise's own CMS, wallet, ledger, sportsbook and support/operations modules.

The SkyRush source_reference in config/internal_games.php is therefore
internal:sky-rush, not an unverified external repository name.

PayoutRoutingService.php was adapted from code developed within the
maintainer's Rise/WinX product family; that private internal development
history does not introduce an external licence for this file.

**Separate third-party component:** The Dice mathematics and mapping are
documented as a selective adaptation of vFair Games' Apache-2.0 source.
The pinned upstream revision and its original license are retained in
[the vFair record](licenses/vfair-dice/README.md). This acknowledgement
is preserved even though Rise's PHP service, financial safety controls,
visual treatment and test harness were implemented for this project.

Other separately bundled libraries retain the licenses listed in
[Third-party licences](THIRD_PARTY_NOTICES.md). The top-level
[Apache-2.0 license](../LICENSE) applies to Rise's own distribution and
does not overwrite third-party copyright or notice requirements.

This record distinguishes the maintainer's stated authorship from
independently pinned third-party license evidence; it does not assert
an external certification or license audit.
