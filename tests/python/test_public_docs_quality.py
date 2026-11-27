"""Source-backed public documentation tests; no network, provider, or database access."""
import importlib.util
from pathlib import Path
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
FILE = ROOT / "scripts/check_public_docs.py"
spec = importlib.util.spec_from_file_location("rise_public_docs", FILE)
docs = importlib.util.module_from_spec(spec)
spec.loader.exec_module(docs)


class PublicDocumentationTests(unittest.TestCase):
    def test_current_docs_link_integrity_and_public_positioning(self):
        self.assertEqual(docs.check(ROOT), [])

    def test_obsolete_records_are_absent(self):
        for name in docs.DEPRECATED:
            with self.subTest(name=name):
                self.assertFalse((ROOT / name).exists(), name)

    def test_security_and_legal_files_are_preserved(self):
        for name in ("LICENSE", "NOTICE", "SECURITY.md", "DISTRIBUTION.md", "docs/THIRD_PARTY_NOTICES.md"):
            self.assertTrue((ROOT / name).is_file(), name)

    def test_scanner_flags_old_status_in_a_fixture(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / "docs").mkdir()
            (root / "README.md").write_text("# Rise Open Core\n\nPrivate GitHub davet. Agent-ready documentation state NOT_RUN.\n")
            errors = docs.check(root)
            self.assertTrue(any(x.startswith("obsolete_internal_status:") for x in errors))
            self.assertTrue(any(x.startswith("obsolete_release_classification:") for x in errors))

    def test_obsolete_rights_hold_is_rejected_without_flagging_wallet_hold(self):
        expression = docs.BANNED["obsolete_rights_hold"]
        self.assertIsNotNone(expression.search("Original source rights HOLD is unchanged."))
        self.assertIsNotNone(expression.search("Public rights HOLD"))
        self.assertIsNone(expression.search("Reservation wallet hold and release"))
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            folder = root / "docs/provenance"
            folder.mkdir(parents=True)
            (root / "README.md").write_text("# Rise Open Core\n")
            (folder / "frontend-vendor.json").write_text(
                '{"note": "Original source rights HOLD is unchanged."}\n'
            )
            problems = docs.check(root)
            self.assertTrue(
                any(problem.startswith("obsolete_rights_hold:docs/provenance/frontend-vendor.json:")
                    for problem in problems),
                problems,
            )

    def test_real_provider_status_is_not_mistaken_for_internal_documentation_lifecycle(self):
        pattern = docs.BANNED["obsolete_internal_status"]
        self.assertIsNone(pattern.search("Provider status IN_PROGRESS maps to board_updated."))
        self.assertIsNone(pattern.search("Provider documentation maps IN_PROGRESS to board_updated."))
        self.assertIsNone(pattern.search("HTTP security documentation requires X-Frame-Options DENY."))
        self.assertIsNone(pattern.search("HTTP CSP X-Frame-Options DENY is required."))
        self.assertIsNone(pattern.search("Reservation hold state is PENDING."))
        self.assertIsNotNone(pattern.search("Agent-ready documentation state IN_PROGRESS."))
        self.assertIsNotNone(pattern.search("Run34 independent install NOT_RUN."))
        self.assertIsNotNone(pattern.search("QA status NOT_RUN."))
        self.assertIsNotNone(pattern.search("Internal test status PARTIAL."))

    def test_public_runtime_surfaces_have_no_old_preview_or_acceptance_notices(self):
        self.assertFalse([
            path for path in docs.PUBLIC_RUNTIME_SURFACES
            if docs.OBSOLETE_RUNTIME_COPY.search((ROOT / path).read_text(encoding="utf-8"))
        ])

    def test_runtime_scanner_catches_an_old_label(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / "scripts").mkdir()
            (root / "scripts/first_install_web.html").write_text("Private developer preview.")
            failures = docs.check(root)
            self.assertTrue(any(x.startswith("obsolete_runtime_copy:scripts/first_install_web.html")
                                for x in failures), failures)

    def test_product_boundary_in_docs(self):
        content = (ROOT / "docs/PRODUCT_PORTFOLIO.md").read_text()
        self.assertIn("Rise Open Core", content)
        self.assertIn("Rise Full", content)
        self.assertIn("ChessBet", content)
        self.assertIn("Nova Gateway", content)
        self.assertIn("Apache-2.0", content)

    def test_hosted_api_keeps_signed_wallet_and_settlement(self):
        content = (ROOT / "docs/developer/HOSTED_IFRAME_GAME.md").read_text()
        for text in ("HMAC-SHA256", "reserve", "confirm", "release", "handoff", "idempotency"):
            self.assertIn(text, content)


if __name__ == "__main__":
    unittest.main()
