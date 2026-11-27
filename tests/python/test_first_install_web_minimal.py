"""Minimal GitHub-style installation UI: focused layout and no default text wall."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
UI = ROOT / "scripts/first_install_web.html"


class MinimalWebUiTest(unittest.TestCase):
    def test_detail_is_collapsed_and_long_text_is_hidden_by_default(self):
        text = UI.read_text(encoding="utf-8")
        self.assertIn('<details id="advanced" class="advanced">', text)
        self.assertNotIn('<details id="advanced" class="advanced" open', text)
        self.assertIn('.quick{display:none}', text)
        self.assertIn("id('notes').replaceChildren()", text)
        self.assertIn("id('advanced').open=false", text)
        self.assertIn("id('detail').textContent=step.detail", text)
        self.assertIn("id('commands').textContent=step.commands", text)
        self.assertIn('id(\'advanced\').open=false', text)

    def test_real_status_and_storage_action_remain(self):
        text = UI.read_text(encoding="utf-8")
        for item in ('/api/status', '/api/prepare-storage',
                     'X-Rise-Token', 'consent:true', 'id="consent"',
                     'id="prepare"', 'id="recheck"', 'id="next"',
                     'id="steps"', 'id="warning"'):
            self.assertIn(item, text)

    def test_profile_uses_accessible_text_rows_on_welcome_and_application(self):
        text = UI.read_text(encoding="utf-8")
        self.assertIn('aria-labelledby="profile-heading"', text)
        self.assertIn('aria-describedby="profile-label profile-notice"', text)
        self.assertIn('<dl id="profile-rows"></dl>', text)
        self.assertIn("step.id!=='welcome'&&step.id!=='application'", text)
        self.assertIn("term.textContent=item[0];value.textContent=item[1]", text)
        self.assertIn("id('profile-notice').textContent=profile.notice", text)
        self.assertIn('@media(max-width:400px)', text)
        self.assertNotIn('innerHTML', text)
        self.assertNotRegex(text, r'<(?:select|textarea|form)\b')
        self.assertEqual(text.count('<input '), 1)  # Existing storage consent only.

    def test_locked_profile_agrees_with_independent_install_guides(self):
        """Prevent an invited developer from mistaking display fields for install choices."""
        guide = (ROOT / "docs/INSTALL_WIZARD.md").read_text(encoding="utf-8")
        human = (ROOT / "docs/FIRST_HUMAN_TEST.md").read_text(encoding="utf-8")
        profile = (ROOT / "scripts/first_install_web.py").read_text(encoding="utf-8")
        self.assertIn("Displayed installation profile", guide)
        self.assertIn("SOURCE CONTRACT INFORMATION / LOCKED", profile)
        for item in ("English by default", "Turkish and Spanish", "UTC", "TRY/USD/EUR", "Normal/VIP/Riskli"):
            self.assertIn(item, human)
        self.assertIn("Choose a real installation country and a supported fiat currency at the terminal prompts.", guide)
        self.assertIn("Choose the installation country and currency in the terminal.", human)
        self.assertIn("Password and confirmation are terminal-only", profile)
        self.assertIn("Recheck inspects local prerequisites", profile)

    def test_git_style_compact_layout_without_remote_dependencies(self):
        text = UI.read_text(encoding="utf-8")
        self.assertIn('--bg:#f6f8fa', text)
        self.assertIn('--text:#1f2328', text)
        self.assertIn('max-width:720px', text)
        self.assertNotIn('grid-template-columns:230px', text)
        self.assertNotIn('s.notes.slice(0,2)', text)
        self.assertIn('@media(max-width:620px)', text)
        self.assertNotIn('src="https://', text)
        self.assertNotIn('href="https://', text)
        self.assertNotIn('font-size:80px', text)


if __name__ == "__main__":
    unittest.main()
