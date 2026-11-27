"""Focused local deterministic tests for private Run34 wizard. No SQL, sudo or providers."""
import importlib.util
from pathlib import Path
import stat
import tempfile
import unittest
from unittest import mock

SCRIPT = Path(__file__).resolve().parents[2] / "scripts/first_install_wizard.py"
spec = importlib.util.spec_from_file_location("rise_first_install_wizard", SCRIPT)
wizard = importlib.util.module_from_spec(spec)
spec.loader.exec_module(wizard)


class WizardTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix="rise-wizard-test-")
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.patch_root = mock.patch.object(wizard, "ROOT", self.root)
        self.patch_root.start()
        self.addCleanup(self.patch_root.stop)

    def test_git_checkout_is_not_a_pristine_handoff(self):
        (self.root / ".git").mkdir()
        self.assertEqual(wizard.source_check()[0], "BLOCKED")

    def test_absent_manifest_is_a_blocker(self):
        self.assertEqual(wizard.source_check()[0], "BLOCKED")

    def test_generated_runtime_never_runs_source_inventory_verifier(self):
        (self.root / "vendor").mkdir()
        with mock.patch.object(wizard, "safe_call") as verifier:
            self.assertEqual(wizard.source_check()[0], "BLOCKED")
        verifier.assert_not_called()

    def test_source_verification_skips_generated_env(self):
        (self.root / ".env").write_text("synthetic private config")
        with mock.patch.object(wizard, "safe_call") as verifier:
            self.assertEqual(wizard.source_check()[0], "BLOCKED")
        verifier.assert_not_called()

    def test_symlink_manifest_rejected(self):
        other = self.root / "external"
        other.write_text("{}")
        (self.root / "SOURCE_SHA256.json").symlink_to(other)
        self.assertEqual(wizard.source_check()[0], "BLOCKED")

    def test_doctor_does_not_claim_complete_install(self):
        with mock.patch.object(wizard, "runtime_check", return_value=("PASS", {})):
            result = wizard.diagnostic("php", "composer")
        self.assertEqual(result["overall"], "PARTIAL")
        self.assertFalse(result["real_db_install_verified"])
        self.assertNotIn("independent_human_acceptance", result)

    def test_storage_is_dry_run_and_leaves_no_new_folders(self):
        status, _ = wizard.prepare_storage(False)
        self.assertEqual(status, "DRY_RUN")
        self.assertFalse((self.root / "storage").exists())

    def test_storage_apply_creates_only_expected_paths(self):
        status, _ = wizard.prepare_storage(True)
        self.assertEqual(status, "PASS")
        for path in wizard.STORAGE_DIRS:
            p = self.root / path
            self.assertTrue(p.is_dir())
            self.assertFalse(p.is_symlink())
        self.assertFalse((self.root / ".env").exists())

    def test_storage_rejects_symlink_without_touching_target(self):
        target = self.root / "outside"
        target.mkdir()
        (self.root / "storage").symlink_to(target, target_is_directory=True)
        status, _ = wizard.prepare_storage(True)
        self.assertEqual(status, "BLOCKED")
        self.assertEqual(list(target.iterdir()), [])

    def test_storage_rejects_permissive_dir_without_chmod(self):
        p = self.root / "storage"
        p.mkdir()
        p.chmod(0o777)
        status, _ = wizard.prepare_storage(True)
        self.assertEqual(status, "BLOCKED")
        self.assertEqual(stat.S_IMODE(p.stat().st_mode), 0o777)

    def test_storage_descriptor_walk_blocks_symlink_swap_after_inspection(self):
        target = self.root / "outside"
        target.mkdir()
        def swap():
            (self.root / "storage").symlink_to(target, target_is_directory=True)
            return "NOT_READY", "Synthetic inspection-to-write race"
        with mock.patch.object(wizard, "storage_status", side_effect=swap):
            self.assertEqual(wizard.prepare_storage(True)[0], "BLOCKED")
        self.assertEqual(list(target.iterdir()), [])

    def test_empty_env_cannot_pass(self):
        self.assertEqual(wizard.env_check()[0], "NOT_READY")

    def test_private_env_with_local_profile_passes_static_validation(self):
        env = self.root / ".env"
        env.write_text("""APP_ENV=local
APP_DEBUG=false
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=rise_b1_candidate
DB_PORT=17990
DB_SOCKET=
APP_URL=http://127.0.0.1:18016
DB_USERNAME=local-only
DB_PASSWORD=synthetic-password-not-a-real-secret
APP_KEY=base64:synthetic-key
CHESS_INTEGRATION_ENABLED=false
NOVA_PAYMENTS_ENABLED=false
""")
        env.chmod(0o600)
        status, text = wizard.env_check()
        self.assertEqual(status, "PASS_CONFIG_ONLY")
        self.assertNotIn("synthetic-password", text)
        original = env.read_text()
        for override in ("DB_URL=mysql://remote.invalid/schema", "DATABASE_URL=mysql://remote.invalid/schema",
                         "DB_SAFETY_REQUIRE_RECENT_SNAPSHOT=false"):
            env.write_text(original + "\n" + override + "\n")
            self.assertEqual(wizard.env_check()[0], "BLOCKED")
        env.write_text(original)
        env.chmod(0o644)
        self.assertEqual(wizard.env_check()[0], "BLOCKED")

    def test_remote_payment_enabled_is_rejected(self):
        env = self.root / ".env"
        env.write_text("""APP_ENV=local
APP_DEBUG=false
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=rise_b1_candidate
DB_USERNAME=local-only
DB_PASSWORD=test
APP_KEY=synthetic
NOVA_PAYMENTS_ENABLED=true
""")
        env.chmod(0o600)
        self.assertEqual(wizard.env_check()[0], "BLOCKED")

    def test_nonlocal_app_url_is_rejected_without_secret_output(self):
        env = self.root / ".env"
        env.write_text("""APP_ENV=local
APP_DEBUG=false
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=rise_b1_candidate
DB_PORT=17990
DB_SOCKET=
APP_URL=http://127.0.0.1.evil.invalid:18016
DB_USERNAME=local-only
DB_PASSWORD=synthetic-secret-not-to-print
APP_KEY=synthetic-test-key
""")
        env.chmod(0o600)
        status, detail = wizard.env_check()
        self.assertEqual(status, "BLOCKED")
        self.assertNotIn("synthetic-secret", detail)

    def test_secondary_remote_finance_gate_is_rejected(self):
        env = self.root / ".env"
        env.write_text("""APP_ENV=local
APP_DEBUG=false
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=rise_b1_candidate
DB_PORT=17990
DB_SOCKET=
APP_URL=http://127.0.0.1:18016
DB_USERNAME=local-only
DB_PASSWORD=synthetic-test-pass
APP_KEY=synthetic-test-key
NOVA_PAYMENTS_REMOTE_CREDIT_ENABLED=true
""")
        env.chmod(0o600)
        self.assertEqual(wizard.env_check()[0], "BLOCKED")

    def test_runtime_rejects_old_php(self):
        (self.root / "database/migrations").mkdir(parents=True)
        for i in range(72):
            (self.root / f"database/migrations/m{i:02d}.php").write_text("<?php")
        extensions = "\n".join(wizard.PHP_EXTENSIONS + ("posix",))
        def fake(args, timeout=12):
            if args[1:] == ["-r", "echo PHP_VERSION;"]:
                return 0, "8.3.1"
            if args[1:] == ["-m"]:
                return 0, extensions
            if args[-1] == "--version":
                return 0, "Composer version 2.10.0" if "composer" in args[0] else "mariadb"
            return 127, ""
        with mock.patch.object(wizard, "safe_call", side_effect=fake):
            status, data = wizard.runtime_check("php", "composer")
        self.assertEqual(status, "BLOCKED")
        self.assertEqual(data["migrations"], 72)

    def test_composer_lock_platform_check_is_mandatory(self):
        (self.root / "database/migrations").mkdir(parents=True)
        for i in range(72):
            (self.root / f"database/migrations/m{i:02d}.php").write_text("<?php")
        modules = "\n".join(wizard.PHP_EXTENSIONS + ("posix",))

        def simulate(platform_rc):
            def fake(args, timeout=12):
                if args[1:] == ["-r", "echo PHP_VERSION;"]:
                    return 0, "8.5.4"
                if args[1:] == ["-m"]:
                    return 0, modules
                if args[:2] == ["composer", "check-platform-reqs"]:
                    return platform_rc, "Checking platform requirements using the lock file\nphp 8.5.4 success\n"
                if args[:2] == ["composer", "--version"]:
                    return 0, "Composer version 2.10.3"
                if args[:2] == ["mariadb", "--version"]:
                    return 0, "mariadb version synthetic"
                return 127, ""
            with mock.patch.object(wizard, "safe_call", side_effect=fake):
                return wizard.runtime_check("php", "composer")

        good, report = simulate(0)
        self.assertEqual(good, "PASS")
        self.assertEqual(report["composer_lock_platform"], "PASS")
        bad, report = simulate(1)
        self.assertEqual(bad, "BLOCKED")
        self.assertEqual(report["composer_lock_platform"], "BLOCKED")

    def test_php_interpreting_shell_wrapper_does_not_count_as_composer(self):
        (self.root / "database/migrations").mkdir(parents=True)
        for i in range(72):
            (self.root / f"database/migrations/m{i:02d}.php").write_text("<?php")
        modules = "\n".join(wizard.PHP_EXTENSIONS + ("posix",))
        def fake(args, timeout=12):
            if args[1:] == ["-r", "echo PHP_VERSION;"]:
                return 0, "8.4.27"
            if args[1:] == ["-m"]:
                return 0, modules
            if args[:2] == ["composer", "--version"]:
                return 0, '/usr/bin/php85 /usr/bin/composer.phar "$@"'
            if args[:2] == ["composer", "check-platform-reqs"]:
                return 0, '/usr/bin/php85 /usr/bin/composer.phar "$@"'
            if args[:2] == ["mariadb", "--version"]:
                return 0, "mariadb from 11.8"
            return 127, ""
        with mock.patch.object(wizard, "safe_call", side_effect=fake):
            status, detail = wizard.runtime_check("php", "composer")
        self.assertEqual(status, "BLOCKED")
        self.assertEqual(detail["composer_lock_platform"], "BLOCKED")

    def test_prepare_storage_apply_blocks_without_verified_source(self):
        self.assertEqual(wizard.source_check()[0], "BLOCKED")
        with mock.patch.object(wizard, "prepare_storage") as mutated:
            self.assertEqual(wizard.main(["setup", "prepare-storage", "--apply"]), 1)
        mutated.assert_not_called()

    def test_interactive_storage_write_requires_explicit_yes(self):
        with mock.patch.object(wizard, "source_check", return_value=("PASS", "verified")), \
             mock.patch.object(wizard, "runtime_check", return_value=("PASS", {"php": "8.5.4"})), \
             mock.patch.object(wizard, "storage_status", return_value=("NOT_READY", "storage missing")), \
             mock.patch.object(wizard, "enrollment_check", return_value=("NOT_READY", "not enrolled")), \
             mock.patch.object(wizard, "env_check", return_value=("NOT_READY", "no local env")), \
             mock.patch.object(wizard, "prepare_storage", return_value=("PASS", "created")) as apply_storage, \
             mock.patch.object(wizard.sys.stdin, "isatty", return_value=True), \
             mock.patch("builtins.input", side_effect=["e", "q", "q"]):
            self.assertEqual(wizard.interactive("php", "composer"), 2)
        apply_storage.assert_called_once_with(True)

    def test_installer_cannot_accept_apply_or_json_flag(self):
        with self.assertRaises(SystemExit):
            wizard.main(["install", "--apply"])
        with self.assertRaises(SystemExit):
            wizard.main(["install", "--json"])


if __name__ == "__main__":
    unittest.main()
