"""Keep the public repository entrance concise, usable and navigable."""
import unittest
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[2]
README = ROOT / "README.md"


class ReadmeEditorialContract(unittest.TestCase):
    def test_readme_starts_with_project_and_is_navigable(self):
        body = README.read_text(encoding="utf-8")
        self.assertTrue(body.startswith("# Rise Open Core\n"))
        self.assertLessEqual(len(body.splitlines()), 280)
        self.assertLessEqual(len(body), 16500)
        for heading in ("## What's Included?", "## Open Core vs Rise Full",
                        "## Built with Rise", "## Quick Start", "## Documentation & License"):
            self.assertIn(heading, body)

    def test_open_core_full_boundaries_and_value_are_clear(self):
        body = README.read_text(encoding="utf-8")
        self.assertIn("8 built-in games", body)
        self.assertIn("18,000+ games", body)
        self.assertIn("160+ game providers", body)
        self.assertNotIn("200+ game providers", body)
        self.assertIn("payment methods, and currencies", body)
        self.assertIn("**Already licensed?**", body)
        self.assertIn("**Not licensed yet?**", body)
        self.assertIn("### Unified Fiat & Crypto Wallet Infrastructure", body)
        self.assertIn("**AI-Native Operations · MCP Ready**", body)
        self.assertIn("| Not included | MCP-ready, permission-controlled read-only access |", body)
        self.assertIn("[Explore WinX](https://winx.now/)", body)
        self.assertIn("**Operations Overview:**", body)
        self.assertNotRegex(body, r"(?i)\bops\b")
        full = body.split("### What Does Rise Full Add?", 1)[1].split("### White-label", 1)[0]
        for feature in ("Magic Search", "Social Betting & Poker Engine", "Player Account Management (PAM)"):
            self.assertIn(feature, full)
        self.assertIn("| Social Betting & Poker Engine | Not included | Commercial modules |", body)
        self.assertGreater(body.index("**AI-Native Operations · MCP Ready**"), body.index("### What Does Rise Full Add?"))

    def test_public_open_core_rights_and_product_boundary_are_clear(self):
        body = README.read_text()
        self.assertIn("Apache-2.0", body)
        self.assertIn("Rise Full", body)
        self.assertIn("ChessBet", body)
        self.assertIn("Nova Gateway", body)

    def test_readme_links_resolve(self):
        body = README.read_text()
        for target in re.findall(r"(?<!!)\[[^]]+\]\(([^)]+)\)", body):
            relative = target.split("#", 1)[0]
            if relative and not relative.startswith(("https://", "http://", "mailto:")):
                with self.subTest(target=relative):
                    self.assertTrue((ROOT / relative).exists(), relative)

    def test_install_path_is_concrete(self):
        body = README.read_text()
        self.assertIn("git archive HEAD", body)
        self.assertIn("SOURCE_SHA256.json", body)
        self.assertIn("./rise web-setup --port", body)
        self.assertIn("docs/INSTALL_WIZARD.md", body)

    def test_architecture_requirements_and_contributing(self):
        body = README.read_text()
        for item in ("```mermaid", "Laravel 12.71", "PHP 8.4.1+", "Composer 2", "Python 3.8+", "No Node.js build required", "planning estimates", "CONTRIBUTING.md", "CHANGELOG.md"):
            self.assertIn(item, body)
        architecture = body.split("## Architecture", 1)[1].split("## Who Is Rise For?", 1)[0]
        for item in ("Multi-source sportsbook adapters", "Multiple odds feeds", "Multi-aggregator / game-provider adapters", "Extensible payment gateway adapters", "Currency & market mapping", "Localization & policies", "Canonical financial authority", "Permission-controlled, read-only MCP", "Provider-specific implementation"):
            self.assertIn(item, architecture)
        self.assertIn("canonical wallet/transaction/ledger", (ROOT / "CONTRIBUTING.md").read_text())

    def test_intro_uses_normal_human_language(self):
        body = README.read_text()
        self.assertNotIn("B1-BT-SAFETY-INSTANCE-001", body)
        self.assertNotIn("48 PASS", body)
        self.assertNotIn("HOLD3", body)


if __name__ == "__main__":
    unittest.main()
