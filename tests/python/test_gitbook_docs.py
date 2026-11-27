"""GitBook source-tree checks; no network, account or publishing calls."""
import re
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
BOOK = ROOT / "docs" / "gitbook"


class GitBookSourceContract(unittest.TestCase):
    def test_configuration_points_only_to_book_root(self):
        content = (BOOK / ".gitbook.yaml").read_text()
        self.assertIn("root: ./", content)
        self.assertIn("readme: README.md", content)
        self.assertIn("summary: SUMMARY.md", content)

    def test_summary_links_are_unique_and_exist(self):
        summary = (BOOK / "SUMMARY.md").read_text()
        targets = re.findall(r"\]\(([^)]+\.md)\)", summary)
        self.assertGreaterEqual(len(targets), 10)
        self.assertEqual(len(targets), len(set(targets)))
        for target in targets:
            with self.subTest(target=target):
                self.assertTrue((BOOK / target).is_file())

    def test_provider_admission_does_not_replace_existing_session_contract(self):
        catalog = (ROOT / "app/Services/InternalGames/InternalGameCatalog.php").read_text()
        session = (ROOT / "app/Services/InternalGames/InternalGameSessionService.php").read_text()
        self.assertIn("function allowsNewAdmission", catalog)
        self.assertIn("assertNewGameplayAllowed", session)
        self.assertIn("allowsNewAdmission($s->game)", session)
        self.assertIn("allowsNewAdmission($game)", session)
        # Existing session validation stays on original permissions/settlement path.
        self.assertIn("$this->catalog->assertAllowed($game,$user);", session)

    def test_internal_links_resolve_and_no_unpublished_engine_or_secret_is_included(self):
        for source in BOOK.glob("*.md"):
            content = source.read_text(encoding="utf-8")
            for link in re.findall(r"\]\(([^)#]+\.md)(?:#[^)]*)?\)", content):
                if link.startswith("https://"):
                    continue
                with self.subTest(source=source.name, link=link):
                    self.assertTrue((source.parent / link).is_file())
            self.assertNotRegex(content, r"-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----")
        self.assertIn("Rise Full", (BOOK / "architecture.md").read_text())


if __name__ == "__main__":
    unittest.main()
