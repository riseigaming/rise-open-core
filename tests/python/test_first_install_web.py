"""Real loopback HTTP security tests; disposable source fixtures, never DB/OS enrollment."""
import contextlib
import hashlib
import http.client
import importlib
import io
import json
import os
from pathlib import Path
import re
import shutil
import sys
import tempfile
import threading
import unittest
from unittest import mock

sys.dont_write_bytecode = True
SCRIPTS = Path(__file__).resolve().parents[2] / "scripts"
sys.path.insert(0, str(SCRIPTS))
web = importlib.import_module("first_install_web")
wizard = web.wizard


class WebTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix="rise-web-test-")
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        # Test-only manifest for a deliberately tiny synthetic handoff. Never rewrite repo manifest.
        (self.root / "scripts").mkdir()
        shutil.copyfile(SCRIPTS / "verify-source-manifest.py", self.root / "scripts/verify-source-manifest.py")
        (self.root / "source.txt").write_text("synthetic private source\n")
        (self.root / "docs").mkdir()
        for name in web.DOCS.values():
            (self.root / "docs" / name).write_text("# Synthetic runbook <unsafe>\n")
        manifest = {p.relative_to(self.root).as_posix(): hashlib.sha256(p.read_bytes()).hexdigest()
                    for p in self.root.rglob("*") if p.is_file()}
        (self.root / "SOURCE_SHA256.json").write_text(json.dumps(manifest))
        patches = [mock.patch.object(wizard, "ROOT", self.root),
                   mock.patch.object(wizard, "runtime_check", return_value=("PASS", {"php": "8.5.4", "migrations": 72})),
                   mock.patch.object(wizard, "enrollment_check", return_value=("NOT_READY", "No root-owned record."))]
        for patch in patches:
            patch.start()
            self.addCleanup(patch.stop)
        self.state = web.SetupState()
        self.server = web.SetupServer(("127.0.0.1", 0), self.state)
        self.thread = threading.Thread(target=self.server.serve_forever, kwargs={"poll_interval": .01}, daemon=True)
        self.thread.start()
        self.addCleanup(self.stop_server)

    def stop_server(self):
        self.server.shutdown()
        self.server.server_close()
        self.thread.join(timeout=2)

    def call(self, method="GET", path="/api/status", body=None, headers=None):
        base = {"Host": self.server.authority, "X-Rise-Token": self.state.token}
        if method == "POST":
            base.update({"Origin": self.server.origin, "Content-Type": "application/json"})
        base.update(headers or {})
        base = {k: v for k, v in base.items() if v is not None}
        conn = http.client.HTTPConnection("127.0.0.1", self.server.server_port, timeout=4)
        conn.request(method, path, body=body, headers=base)
        response = conn.getresponse()
        result = response.status, dict(response.getheaders()), response.read().decode()
        conn.close()
        return result

    def post(self, body='{"consent":true}', **kwargs):
        return self.call("POST", "/api/prepare-storage", body, **kwargs)

    def test_page_has_seven_steps_live_api_and_security_headers(self):
        code, headers, body = self.call(path="/")
        self.assertEqual(code, 200)
        self.assertIn("/api/status", body)
        self.assertIn("/api/prepare-storage", body)
        self.assertNotIn("https://", body)
        self.assertIn("aria-live=\"polite\"", body)
        self.assertIn("frame-ancestors 'none'", headers["Content-Security-Policy"])
        self.assertNotIn("'unsafe-inline'", headers["Content-Security-Policy"])
        self.assertEqual(headers["Cache-Control"], "no-store")
        self.assertNotIn("Access-Control-Allow-Origin", headers)
        self.assertIn(self.state.token, body)  # Ephemeral CSRF value, never credentials/URL/logs.

    def test_real_source_verification_get_does_not_write(self):
        before = sorted(p.relative_to(self.root).as_posix() for p in self.root.rglob("*"))
        code, _, body = self.call()
        report = json.loads(body)
        self.assertEqual(code, 200)
        self.assertEqual(report["steps"][1]["status"], "VERIFIED_THIS_SESSION")
        self.assertEqual(len(report["steps"]), 7)
        self.assertTrue(report["can_prepare_storage"])
        self.assertEqual(before, sorted(p.relative_to(self.root).as_posix() for p in self.root.rglob("*")))

    def test_missing_enrollment_not_false_pass(self):
        report = json.loads(self.call()[2])
        self.assertEqual(report["steps"][2]["status"], "NOT_READY")
        self.assertNotIn("overall", report)
        self.assertFalse(report["real_db_install_verified"])
        self.assertNotIn("independent_human_acceptance", report)

    def test_profile_is_fixed_source_information_even_when_source_is_blocked(self):
        original = json.loads(self.call()[2])["install_profile"]
        (self.root / "source.txt").write_text("changed source")
        report = json.loads(self.call()[2])
        self.assertEqual(report["steps"][1]["status"], "BLOCKED")
        self.assertEqual(report["install_profile"], original)
        self.assertEqual(original["label"], "SOURCE CONTRACT INFORMATION / LOCKED")
        self.assertIn("Recheck inspects local prerequisites", original["notice"])
        self.assertFalse(report["real_db_install_verified"])
        self.assertEqual(report["steps"][5]["status"], "GUIDE")
        # Profile fields cannot turn the storage-only action into configuration.
        extra_field = json.dumps({"consent": True, "install_profile": None})
        self.assertLessEqual(len(extra_field.encode()), 128)  # Reject extra field, not the size gate.
        self.assertEqual(self.post(extra_field)[0], 400)
        self.assertEqual(self.call("POST", "/api/install-profile", '{}')[0], 404)

    def test_profile_catalog_matches_php_source_and_run34_without_executing_php(self):
        root = SCRIPTS.parent
        rows = dict(web.INSTALL_PROFILE["rows"])
        bootstrap = (root / "app/Console/Commands/BootstrapFirstAdminCommand.php").read_text()
        checker = (root / "app/Console/Commands/CheckFreshInstallationCommand.php").read_text()
        types = (root / "app/Models/CmsUserType.php").read_text()
        wallets = (root / "config/product_wallets.php").read_text()
        run34 = (root / "docs/INSTALL_RUN34.md").read_text()
        catalog = re.findall(r"public const TYPE_\w+ = '([^']+)';", types)
        self.assertEqual(rows["User types"].split(":", 1)[0].split(" / "), catalog)
        self.assertIn("classification labels, not permission roles", rows["User types"])
        assets = re.search(r"'fiat_assets'\s*=>\s*\[([^]]+)\]", wallets).group(1)
        self.assertEqual(re.search(r"([A-Z]{3} / [A-Z]{3} / [A-Z]{3}) supported", rows["Currency"]).group(1).split(" / "),
                         re.findall(r"'([^']+)'", assets))
        language_map = re.search(r"foreach \(\[([^]]+)\] as \$code => \$languageName\)", bootstrap).group(1)
        self.assertEqual(sorted(re.findall(r"'([a-z]{2})' =>", language_map)),
                         sorted(code.lower() for code in re.search(r"; (.*?) active", rows["Languages"]).group(1).split(" / ")))
        self.assertTrue(rows["Languages"].startswith("EN default;"))
        self.assertIn("UTC", rows["Languages"])
        self.assertIn("'timezone' => env('APP_TIMEZONE', 'UTC')", (root / "config/app.php").read_text())
        commands = next(step["commands"] for step in web.SetupState("php", "composer").report()["steps"]
                        if step["id"] == "application")
        for guide in (commands, run34):
            self.assertIn("artisan app:admin-bootstrap\n", guide)
            self.assertNotIn("--country-code=", guide)
            self.assertNotIn("--currency-code=", guide)
        self.assertIn("real country by ISO2 code and name", rows["Country"])
        self.assertIn("Choose the installation currency at the terminal prompt", rows["Currency"])
        self.assertIn("No automatic FX or payment-provider acceptance", rows["Currency"])
        self.assertIn("where('code', 'en')->update(['is_default' => 1])", bootstrap)
        self.assertIn("'active_theme' => 'global', 'default_lang' => 'en', 'register_status' => 0", bootstrap)
        migrations = len(list((root / "database/migrations").glob("*.php")))
        self.assertEqual(migrations, 72)
        self.assertIn("72 migration", run34)
        self.assertIn("Global; registration closed; 72 migration files", rows["Site baseline"])
        for field in ("name", "username", "email"):
            self.assertIn("$this->inputValue('" + field + "', 'Administrator", bootstrap)
            self.assertIn(field, rows["Admin identity"])
        self.assertIn("$this->ask($prompt)", bootstrap)
        self.assertIn("$this->secret('Password (minimum 12 characters)')", bootstrap)
        self.assertIn("$this->secret('Confirm password')", bootstrap)
        for check in ("first CMS admin identity and password hash", "bootstrap password mutation audit"):
            self.assertIn(check, checker)
        self.assertIn("app:install:check", run34)
        self.assertIn("terminal-only", rows["Admin identity"])
        self.assertIn("verify the initial administrator and password setup", rows["Password audit"])
        self.assertNotIn("NOT_RUN", rows["Password audit"])

    def test_presence_and_all_static_checks_cannot_fake_install_ready(self):
        (self.root / "vendor/composer").mkdir(parents=True)
        (self.root / "vendor/autoload.php").write_text("<?php")
        (self.root / "vendor/composer/installed.json").write_text("{}")
        (self.root / "DB_BACKUPS").mkdir()
        (self.root / "DB_BACKUPS/fake.sql").write_text("-- marker, not a dump")
        with mock.patch.object(wizard, "env_check", return_value=("PASS_CONFIG_ONLY", "static")), \
             mock.patch.object(wizard, "enrollment_check", return_value=("PASS_RECORD_ONLY", "record")), \
             mock.patch.object(wizard, "storage_status", return_value=("PASS", "present")):
            report = json.loads(self.call()[2])
        self.assertNotIn("overall", report)
        self.assertFalse(report["real_db_install_verified"])
        self.assertEqual([s["status"] for s in report["steps"][4:]], ["GUIDE"] * 3)
        self.assertFalse(report["can_prepare_storage"])

    def test_post_only_storage_action_real_helper_and_receipt_survives_runtime(self):
        self.assertEqual(self.call()[0], 200)
        self.assertEqual(self.post()[0], 200)
        for part in wizard.STORAGE_DIRS:
            self.assertTrue((self.root / part).is_dir())
            self.assertEqual((self.root / part).stat().st_mode & 0o077, 0)
        self.assertFalse((self.root / ".env").exists())
        # The session compares pinned hashes, never the source inventory verifier now.
        (self.root / ".env").write_text("private runtime data")
        with mock.patch.object(wizard, "source_check", side_effect=AssertionError("must not rerun inventory")):
            report = json.loads(self.call()[2])
        self.assertEqual(report["steps"][1]["status"], "VERIFIED_THIS_SESSION")
        self.assertFalse(report["can_prepare_storage"])

    def test_get_cannot_prepare_storage(self):
        with mock.patch.object(wizard, "prepare_storage") as write:
            self.assertEqual(self.call(path="/api/prepare-storage")[0], 404)
        write.assert_not_called()
        self.assertFalse((self.root / "storage").exists())

    def test_consent_must_be_exact_boolean_and_only_field(self):
        with mock.patch.object(wizard, "prepare_storage") as write:
            for body in ('{}', '{"consent":false}', '{"consent":1}', '{"consent":"true"}',
                         '{"consent":true,"action":"migrate"}', '{"consent":false,"consent":true}',
                         '[]', 'null', 'invalid', '{"consent":{"nested":true}}'):
                with self.subTest(body=body):
                    self.assertEqual(self.post(body)[0], 400)
        write.assert_not_called()

    def test_csrf_token_mandatory_for_status_and_action(self):
        with mock.patch.object(wizard, "prepare_storage") as write:
            for token in (None, "wrong-token"):
                self.assertEqual(self.call(headers={"X-Rise-Token": token})[0], 403)
                self.assertEqual(self.post(headers={"X-Rise-Token": token})[0], 403)
        write.assert_not_called()

    def test_unexpected_host_and_origin_blocked(self):
        with mock.patch.object(wizard, "prepare_storage") as write:
            for host in ("evil.invalid", "localhost:" + str(self.server.server_port),
                         self.server.authority + ".evil.invalid", "127.0.0.1:1", "127.0.0.1"):
                self.assertEqual(self.call(path="/", headers={"Host": host})[0], 403)
                self.assertEqual(self.post(headers={"Host": host})[0], 403)
            for origin in ("null", "https://evil.invalid", "http://localhost:18734", self.server.origin + "/"):
                self.assertEqual(self.call(headers={"Origin": origin})[0], 403)
                self.assertEqual(self.post(headers={"Origin": origin})[0], 403)
            self.assertEqual(self.post(headers={"Origin": None})[0], 403)
            self.assertEqual(self.call(path="/", headers={"Sec-Fetch-Site": "cross-site"})[0], 403)
        write.assert_not_called()

    def test_duplicate_host_and_origin_rejected(self):
        conn = http.client.HTTPConnection("127.0.0.1", self.server.server_port, timeout=4)
        conn.putrequest("GET", "/", skip_host=True)
        conn.putheader("Host", self.server.authority)
        conn.putheader("Host", "evil.invalid")
        conn.endheaders()
        response = conn.getresponse()
        self.assertEqual(response.status, 403)
        response.read()
        conn.close()
        self.assertEqual(self.post(headers={"Origin": self.server.origin + ", " + self.server.origin})[0], 403)

    def test_no_traversal_or_secret_file_routes(self):
        for path in ("/.env", "/SOURCE_SHA256.json", "/../.env", "/%2e%2e/.env", "/guide/../.env", "/scripts/first_install_web.py"):
            with self.subTest(path=path):
                self.assertEqual(self.call(path=path)[0], 404)

    def test_secret_query_is_rejected_not_logged_or_reflected(self):
        secret = "synthetic-secret-do-not-log"
        output = io.StringIO()
        with contextlib.redirect_stderr(output), contextlib.redirect_stdout(output):
            code, _, body = self.call(path="/?token=" + secret)
        self.assertEqual(code, 403)
        self.assertNotIn(secret, body + output.getvalue())

    def test_content_type_transfer_encoding_and_oversized_payload_rejected(self):
        for headers, body in (({"Content-Type": "text/plain"}, '{"consent":true}'),
                              ({"Transfer-Encoding": "chunked"}, '{"consent":true}'),
                              ({}, "x" * 129)):
            self.assertEqual(self.post(body, headers=headers)[0], 400)

    def test_source_change_missing_manifest_and_checkout_block_writes(self):
        for alteration in ("source", "manifest", "git"):
            with self.subTest(alteration=alteration):
                if alteration == "source":
                    self.call()  # Obtain pristine receipt first.
                    (self.root / "source.txt").write_text("changed")
                elif alteration == "manifest":
                    (self.root / "SOURCE_SHA256.json").unlink()
                else:
                    (self.root / ".git").mkdir()
                    self.state.receipt = None
                with mock.patch.object(wizard, "prepare_storage") as write:
                    self.assertEqual(self.post()[0], 409)
                write.assert_not_called()

    def test_symlink_storage_and_permissive_root_block_real_action(self):
        self.call()
        outside = Path(self.temp.name).parent / (self.root.name + "-outside")
        outside.mkdir()
        self.addCleanup(outside.rmdir)
        (self.root / "storage").symlink_to(outside, target_is_directory=True)
        self.assertEqual(self.post()[0], 409)
        self.assertEqual(list(outside.iterdir()), [])
        (self.root / "storage").unlink()
        self.root.chmod(0o777)
        self.assertEqual(self.post()[0], 409)
        self.assertFalse((self.root / "storage").exists())
        self.root.chmod(0o700)

    def test_pinned_source_cannot_be_replaced_with_symlink(self):
        self.call()
        source = self.root / "source.txt"
        body = source.read_text()
        source.unlink()
        target = self.root / "other.txt"
        target.write_text(body)
        source.symlink_to(target)
        self.assertEqual(self.post()[0], 409)

    def test_restart_after_runtime_does_not_invent_clean_source_receipt(self):
        self.call()
        self.post()
        (self.root / ".env").write_text("runtime")
        with mock.patch.object(wizard, "safe_call") as verifier:
            self.assertEqual(web.SetupState().source()[0], "BLOCKED")
        verifier.assert_not_called()

    def test_local_docs_are_allowlisted_and_escaped(self):
        code, _, body = self.call(path="/guide/run34")
        self.assertEqual(code, 200)
        self.assertIn("&lt;unsafe&gt;", body)
        self.assertNotIn("<unsafe>", body)
        doc = self.root / "docs/INSTALL_RUN34.md"
        doc.unlink()
        doc.symlink_to(self.root / "source.txt")
        self.assertEqual(self.call(path="/guide/run34")[0], 404)

    def test_unknown_mutations_have_no_backend(self):
        for path in ("/api/migrate", "/api/admin", "/api/env", "/api/enroll", "/api/snapshot"):
            self.assertEqual(self.call("POST", path, '{"consent":true}')[0], 404)

    def test_non_loopback_bind_root_and_bad_ports_refused(self):
        for host in ("0.0.0.0", "::1", "localhost", "example.invalid", "192.168.1.1"):
            with self.assertRaises(ValueError):
                web.SetupServer((host, 0), self.state)
        with mock.patch.object(web, "SetupServer") as server:
            self.assertEqual(web.serve(0), 1)
            self.assertEqual(web.serve(65536), 1)
            with mock.patch.object(web.os, "geteuid", return_value=0):
                self.assertEqual(web.serve(18734), 1)
        server.assert_not_called()

    def test_cli_dispatch_keeps_terminal_commands_and_rejects_network_host(self):
        with mock.patch.object(web, "serve", return_value=0) as serve:
            self.assertEqual(wizard.main(["web-setup", "--port", "19034", "--php", "/local/php"]), 0)
            serve.assert_called_once_with(19034, "/local/php", "composer")
        with contextlib.redirect_stderr(io.StringIO()):
            for args in (["web-setup", "--host", "0.0.0.0"], ["web-setup", "--apply"],
                         ["web-setup", "--json"], ["doctor", "--port", "1234"]):
                with self.assertRaises(SystemExit):
                    wizard.main(args)


if __name__ == "__main__":
    unittest.main()
