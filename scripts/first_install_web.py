#!/usr/bin/env python3
"""Loopback-only Open Core setup guide. The only write API prepares private storage.

No DB connections, PHP application commands, package installs or credential inputs.
Source verification is a session receipt, never a bypass of the PHP DB guard.
"""
from __future__ import annotations

import hashlib
import hmac
from http.server import BaseHTTPRequestHandler, HTTPServer
import json
import os
from pathlib import Path, PurePosixPath
import secrets
import stat
import sys
import threading

sys.dont_write_bytecode = True
import first_install_wizard as wizard

DOCS = {
    "/guide/run34": "INSTALL_RUN34.md",
    "/guide/human-test": "FIRST_HUMAN_TEST.md",
    "/guide/rights": "REVIEW_RIGHTS_AND_LIMITS.md",
}

# Fixed source contract, never an observation of .env, installed DB or credentials.
# Keep aligned with PHP source and Run34 via the source-contract regression test.
INSTALL_PROFILE = {
    "heading": "Installation profile",
    "label": "SOURCE CONTRACT INFORMATION / LOCKED",
    "notice": "Recheck inspects local prerequisites. Choose your installation country and currency during first admin setup in your terminal.",
    "rows": [
        ["Languages", "EN default; EN / TR / ES active. System timezone: UTC."],
        ["Country", "Choose a real country by ISO2 code and name at the terminal prompt. The selection does not provide an operating licence."],
        ["Currency", "TRY / USD / EUR supported fiat-wallet assets. Choose the installation currency at the terminal prompt. No automatic FX or payment-provider acceptance."],
        ["User types", "Normal / VIP / Riskli: fixed catalog classification labels, not permission roles."],
        ["Site baseline", "Global; registration closed; 72 migration files (not an applied DB ledger)."],
        ["Admin identity", "Enter name, username and email interactively in your terminal with app:admin-bootstrap. Password and confirmation are terminal-only; never enter credentials in this browser."],
        ["Password audit", "After creating the first admin, run app:install:check in your terminal to verify the initial administrator and password setup. Password entry stays in the terminal; this page does not inspect credentials."],
    ],
}


def observation(relative: str) -> str:
    """Presence is deliberately not install/snapshot acceptance; never read contents."""
    path = wizard.ROOT
    try:
        for part in Path(relative).parts:
            path = path / part
            if path.is_symlink():
                return "BLOCKED"
        info = path.stat()
        if not stat.S_ISREG(info.st_mode) or info.st_uid != os.getuid():
            return "BLOCKED"
        return "PRESENT_ONLY"
    except FileNotFoundError:
        return "NOT_READY"
    except OSError:
        return "BLOCKED"


class SetupState:
    def __init__(self, php: str = "php", composer: str = "composer"):
        self.php, self.composer = php, composer
        self.token = secrets.token_urlsafe(32)
        self.lock = threading.Lock()
        self.receipt: dict[str, str] | None = None
        self.manifest_digest = ""

    def source(self) -> tuple[str, str]:
        if self.receipt is None:
            status, detail = wizard.source_check()
            if status != "PASS":
                return status, detail
            # Pin only after the existing strict source verifier passed on a clean export.
            try:
                raw = (wizard.ROOT / "SOURCE_SHA256.json").read_bytes()
                self.receipt = json.loads(raw)
                self.manifest_digest = hashlib.sha256(raw).hexdigest()
            except (OSError, ValueError):
                self.receipt = None
                return "BLOCKED", "Cannot retain the verified source receipt. Stop."
        try:
            manifest = wizard.ROOT / "SOURCE_SHA256.json"
            if manifest.is_symlink() or hashlib.sha256(manifest.read_bytes()).hexdigest() != self.manifest_digest:
                raise ValueError
            # Runtime additions are allowed after the pristine check; pinned sources cannot change.
            # Do not invoke the full source inventory verifier after runtime generation.
            if not isinstance(self.receipt, dict) or not self.receipt:
                raise ValueError
            for name, digest in self.receipt.items():
                rel = PurePosixPath(name)
                if rel.is_absolute() or ".." in rel.parts or rel.as_posix() != name:
                    raise ValueError
                path = wizard.ROOT
                for part in rel.parts:
                    path /= part
                    if path.is_symlink():
                        raise ValueError
                if hashlib.sha256(path.read_bytes()).hexdigest() != digest:
                    raise ValueError
        except (OSError, ValueError, TypeError):
            return "BLOCKED", "Pinned source or manifest changed since verification. Stop; do not regenerate hashes."
        return "VERIFIED_THIS_SESSION", "Clean source inventory verified in this server session; pinned hashes still match. Runtime additions are not source acceptance."

    def report(self) -> dict:
        with self.lock:
            source, source_detail = self.source()
            runtime, runtime_detail = wizard.runtime_check(self.php, self.composer)
            enrollment, enrollment_detail = wizard.enrollment_check()
            env, env_detail = wizard.env_check()
            storage, storage_detail = wizard.storage_status()
            dependencies = observation("vendor/autoload.php")
            locked = observation("vendor/composer/installed.json")
            source_ok = source == "VERIFIED_THIS_SESSION"
            can_prepare = (source_ok and storage == "NOT_READY"
                           and not (hasattr(os, "geteuid") and os.geteuid() == 0))
            return {
                "checkpoint": "72 migration source files",
                "real_db_install_verified": False,
                "can_prepare_storage": can_prepare,
                "install_profile": INSTALL_PROFILE,
                "steps": [
                    step("welcome", "01", "Meet your workspace", "Rise Open Core local setup", "GUIDE",
                         "A careful first install, one gate at a time.",
                         "Rise is one canonical CMS, user and wallet/ledger. This Open Core source includes the local CMS, game and adapter foundation. Rise Full is the separate commercial distribution; ChessBet and Nova Gateway are optional hosted services.",
                         [("The boundary", "New disposable local data only. Keep providers and real funds disabled. The included Open Core source is Apache-2.0 licensed; separately hosted services have their own access terms."),
                          ("What this guide does", "Reads actual local checks. The only browser write creates missing private storage directories after your explicit consent. All DB, enrollment, dependency and admin commands stay in your Terminal.")],
                         "git rev-parse HEAD\ngit status --short", "/guide/rights"),
                    step("source", "02", "Source & environment", "Verify before generating runtime files", source,
                         "Start from a clean, pinned source export.", source_detail,
                         [("Runtime preflight · " + runtime, json.dumps(runtime_detail, ensure_ascii=False)),
                          ("Source receipt", "The initial full manifest check happens before .env/vendor/runtime generation. This running session then compares pinned source hashes only. Keep the server open through setup. No receipt is persisted or accepted from the browser.")],
                         "# In your Git checkout, record the exact SHA first:\ngit rev-parse HEAD\n# Choose a NEW empty destination; do not overwrite an existing install.\nmkdir ../rise-local\ngit archive --format=tar HEAD | tar -xf - -C ../rise-local\ncd ../rise-local\npython3 scripts/verify-source-manifest.py SOURCE_SHA256.json\n./rise web-setup --port 18734\n# Run34 §1: choose explicit PHP/Composer binaries if PATH differs.", "/guide/run34"),
                    step("trust", "03", "Local database trust", "Two terminals · independent OS owner", enrollment,
                         "Trust the intended instance before the first write.", enrollment_detail,
                         [("1 · New instance / Terminal A", "Use Run34 §2 to choose a NEW datadir, socket, PID file, loopback port and server ID. Start native MariaDB as its unprivileged OS owner. Keep this foreground terminal open."),
                          ("2 · Preflight / Terminal B", "Redefine all paths in the second shell. Independently review the rise-local-instance-v2 manifest and SHA. Run the exact Run34 §2 preflight/provision function: it checks OS/SQL identity and absent target schemas before any CREATE. Never adopt existing data."),
                          ("3 · Human OS administrator", "After target identity and empty schemas are verified, the OS administrator runs the exact enrollment command in Run34 §3. Do not run PHP or this web server as root. Without an enrollment record this step cannot continue. A stored record alone does not verify the running database.")],
                         "# Read Run34 §2–3 before substituting reviewed local values.\n# Read-only preflight; no CREATE and no enrollment:\npython3 scripts/enroll-local-instance.py --preflight \\\n  --manifest /absolute/path/to/reviewed-manifest.json \\\n  --sha256 REVIEWED_SHA256 --pid VERIFIED_MARIADB_PID --port VERIFIED_PORT \\\n  --socket /absolute/path/to/new/rise-db.sock \\\n  --pid-file /absolute/path/to/new/rise-db.pid --owner \"$(id -un)\"\n# STOP on any error. Provision/enrollment remain human-run in Run34.", "/guide/run34"),
                    step("storage", "04", "Private storage & .env", "One narrowly scoped browser action", storage if storage != "PASS" else env,
                         "Keep runtime files private, and secrets in your editor.", storage_detail,
                         [("Environment · " + env, env_detail),
                          ("Manual configuration", "Create .env only if it does not exist. Use your private editor for NEW DB credentials, APP_ENV=local, APP_DEBUG=false, DB_HOST=127.0.0.1, your verified port, rise_b1_candidate, empty DB_SOCKET and a loopback APP_URL. Keep CHESS/NOVA/finance gates disabled. The browser never reads back secret values or accepts credentials."),
                          ("Storage consent", "The action below calls the terminal helper to create only missing owned storage/cache/DB_BACKUPS directories. Existing unsafe permissions or symlinks stop it; no chmod/chown, .env edits or DB operations are performed.")],
                         "# In the exported source root; refuse to overwrite any existing .env:\n(umask 077; set -C; cat .env.example > .env)\n# Edit .env privately; verify owner and 0600 permissions.\n# After locked dependencies are installed, generate the NEW key once:\n\"$RISE_PHP\" artisan key:generate --no-interaction", "/guide/run34"),
                    step("dependencies", "05", "Lockfile & snapshot", "Manual install · genuine native dump", "GUIDE",
                         "Install the lock. Preserve a real recovery point.",
                         "File presence cannot prove a locked install or a genuine snapshot. These acceptance gates remain human-run.",
                         [("Dependency observation", "vendor/autoload.php: " + dependencies + "; vendor/composer/installed.json: " + locked + ". Neither proves lock parity, audit or platform acceptance."),
                          ("Locked dependencies", "Use Run34 §1 with an explicit PHP/Composer pair. Do not update the lock or bypass advisory/platform checks. The UI itself needs no network; downloading missing locked packages requires separately approved access or an existing trusted cache."),
                          ("Snapshot gate", "After DB trust/enrollment and before migrations, use Run34 §4's native mariadb-dump command on the verified empty target. Keep dump 0600 and DB_BACKUPS 0700. Record target, exit 0, bytes and SHA privately. A recent .sql filename or marker is not proof of a genuine, restorable dump. This UI does not inspect or download dumps.")],
                         "# Set RISE_PHP / RISE_COMPOSER to your reviewed binaries (Run34 §1).\n\"$RISE_PHP\" \"$RISE_COMPOSER\" validate --no-check-publish --no-plugins --no-scripts\n\"$RISE_PHP\" \"$RISE_COMPOSER\" install --no-plugins --no-scripts --prefer-dist --no-interaction\n\"$RISE_PHP\" \"$RISE_COMPOSER\" check-platform-reqs --lock --no-plugins --no-scripts\n# Then follow the complete interactive-password dump recipe in Run34 §4.\n# Never put DB passwords in command arguments or this browser.", "/guide/run34"),
                    step("application", "06", "Migrations & first admin", "Human approval · existing PHP safety guards", "GUIDE",
                         "Build the neutral baseline on your new local database.",
                         "Web setup never connects to DB or invokes Artisan. Recheck refreshes file/record observations only; it cannot certify migrations or first admin.",
                         [("Before running", "Confirm the exact disposable target, owner, enrollment, private .env, platform and genuine snapshot. Read Run34 §4. The existing PHP DB safety guard must accept the intended current and TCP instance. Any mismatch means STOP; never disable a guard."),
                          ("First admin", "Run the interactive bootstrap once, entering a new password only in your Terminal. It refuses existing CMS identities and is not password recovery. Expected neutral baseline: 72 migration ledger entries, Global, EN default with EN/TR/ES, UTC, registration closed. Choose the installation country and currency at the terminal prompts."),
                          ("Keep evidence separate", "Retain sanitized migrate:status and app:install:check exits before customizing defaults. A file count is not the applied migration ledger. Neither is independent HTTP acceptance.")],
                         "# Only after your explicit local target/snapshot approval:\n\"$RISE_PHP\" artisan migrate --no-interaction\n\"$RISE_PHP\" artisan migrate:status\n\"$RISE_PHP\" artisan app:admin-bootstrap\n\"$RISE_PHP\" artisan app:install:check\n\"$RISE_PHP\" artisan storage:link\n\"$RISE_PHP\" artisan route:list --json", "/guide/run34"),
                    step("acceptance", "07", "Login & readback", "Login and saved-content review", "GUIDE",
                         "Prove the experience in a fresh browser session.",
                         "Finish the login, save and readback checks in your own authorized local instance. This guide does not perform those operations.",
                         [("1 · Serve and sign in", "Choose a free loopback HTTP port matching APP_URL (different from web setup). Guest /cms/admin must redirect to login. Use the real admin form, CSRF and session; keep credentials out of reports and this wizard."),
                          ("2 · Save → refresh → guest readback", "Save a synthetic title/menu and TR/EN content draft. Refresh and reopen it. Publish/unpublish and inspect a NEW guest session. Keep neutral install-check evidence separate from intentionally changed defaults."),
                          ("3 · Record the result", "Follow FIRST_HUMAN_TEST.md and record the source revision, setup checks, any skipped steps and sanitized results. Navigating this guide does not perform installation or authorize real funds.")],
                         "# Use a free port matching your private APP_URL; example only:\nRISE_HTTP_PORT=18016\n\"$RISE_PHP\" artisan serve --host=127.0.0.1 --port=\"$RISE_HTTP_PORT\" --no-reload\n# In your browser: http://127.0.0.1:18016/cms/admin\n# Complete docs/FIRST_HUMAN_TEST.md privately; never paste secrets.", "/guide/human-test"),
                ],
            }

    def prepare(self, payload: dict) -> tuple[int, dict]:
        if payload != {"consent": True} or payload.get("consent") is not True:
            return 400, {"error": "Explicit storage-only consent is required."}
        with self.lock:
            if self.source()[0] != "VERIFIED_THIS_SESSION":
                return 409, {"error": "A verified clean source session is required; no write performed."}
            status, detail = wizard.prepare_storage(True)
            return (200 if status == "PASS" else 409), {"status": status, "detail": detail}


def step(key, number, title, subtitle, status, heading, detail, notes, commands, guide):
    return dict(id=key, number=number, title=title, subtitle=subtitle, status=status,
                heading=heading, detail=detail, notes=notes, commands=commands, guide=guide)


class SetupServer(HTTPServer):
    allow_reuse_address = False

    def __init__(self, address, state):
        if address[0] != "127.0.0.1":
            raise ValueError("Web setup binds only to 127.0.0.1")
        self.state = state
        super().__init__(address, Handler)
        self.authority = "127.0.0.1:" + str(self.server_port)
        self.origin = "http://" + self.authority

    def get_request(self):
        connection, address = super().get_request()
        connection.settimeout(5)
        return connection, address

    def handle_error(self, request, client_address):
        # Never print headers, URLs, request data, secrets or exception payloads.
        pass


class Handler(BaseHTTPRequestHandler):
    server_version = "RiseLocalSetup"
    sys_version = ""

    def log_message(self, format, *args):
        pass  # No access logs (including query strings, tokens or request bodies).

    def reply(self, status, body, content_type="application/json; charset=utf-8", nonce=""):
        data = (json.dumps(body, ensure_ascii=False) if isinstance(body, dict) else body).encode()
        self.send_response(status)
        self.send_header("Content-Type", content_type)
        self.send_header("Content-Length", str(len(data)))
        self.send_header("Cache-Control", "no-store")
        self.send_header("Referrer-Policy", "no-referrer")
        self.send_header("X-Content-Type-Options", "nosniff")
        self.send_header("X-Frame-Options", "DENY")
        self.send_header("Permissions-Policy", "camera=(), microphone=(), geolocation=()")
        self.send_header("Content-Security-Policy", "default-src 'none'; script-src 'nonce-" + nonce
                         + "'; style-src 'nonce-" + nonce + "'; connect-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'")
        self.send_header("Connection", "close")
        self.end_headers()
        self.close_connection = True
        self.wfile.write(data)

    def allowed(self, api=False, post=False):
        if (self.client_address[0] != "127.0.0.1"
                or self.headers.get_all("Host", []) != [self.server.authority]
                or self.headers.get_all("Origin", []) not in ([], [self.server.origin])
                or self.headers.get("Sec-Fetch-Site", "") not in ("", "same-origin", "none")
                or "?" in self.path or "#" in self.path):
            self.reply(403, {"error": "Only the exact local setup origin is allowed."})
            return False
        if post and self.headers.get_all("Origin", []) != [self.server.origin]:
            self.reply(403, {"error": "A same-origin browser action is required."})
            return False
        if api:
            supplied = self.headers.get_all("X-Rise-Token", [])
            if (len(supplied) != 1 or not supplied[0].isascii()
                    or not hmac.compare_digest(supplied[0], self.server.state.token)):
                self.reply(403, {"error": "Invalid local session token. Reload the guide."})
                return False
        return True

    def do_GET(self):
        if not self.allowed(api=self.path.startswith("/api/")):
            return
        if self.path == "/":
            nonce = secrets.token_urlsafe(24)
            self.reply(200, PAGE.replace("__NONCE__", nonce).replace("__TOKEN__", self.server.state.token),
                       "text/html; charset=utf-8", nonce)
        elif self.path == "/api/status":
            try:
                self.reply(200, self.server.state.report())
            except Exception:
                self.reply(500, {"error": "Local inspection failed. No install acceptance; inspect your local prerequisites."})
        elif self.path in DOCS:
            import html
            try:
                # Exact fixed allowlist; no arbitrary file server or traversal.
                path = wizard.ROOT / "docs" / DOCS[self.path]
                if path.is_symlink() or path.parent.is_symlink():
                    raise OSError
                text = path.read_text(encoding="utf-8")
                nonce = secrets.token_urlsafe(24)
                self.reply(200, "<!doctype html><html lang=en><meta charset=utf-8><meta name=viewport content='width=device-width,initial-scale=1'><title>Rise · Local runbook</title><style nonce='" + nonce + "'>body{font:15px/1.7 system-ui;margin:24px;max-width:1000px}pre{white-space:pre-wrap;overflow-wrap:anywhere}</style><a href='/'>← Back to setup</a><pre>" + html.escape(text) + "</pre></html>", "text/html; charset=utf-8", nonce)
            except (OSError, UnicodeError):
                self.reply(404, {"error": "Local runbook unavailable."})
        else:
            self.reply(404, {"error": "Unknown setup route."})

    def do_POST(self):
        if not self.allowed(api=True, post=True):
            return
        if self.path != "/api/prepare-storage":
            self.reply(404, {"error": "No such setup action."})
            return
        lengths = self.headers.get_all("Content-Length", [])
        if (self.headers.get("Transfer-Encoding") or len(lengths) != 1
                or len(lengths[0]) > 3 or not lengths[0].isascii() or not lengths[0].isdigit()
                or not 1 <= int(lengths[0]) <= 128
                or self.headers.get_all("Content-Type", []) != ["application/json"]):
            self.reply(400, {"error": "A small JSON consent body is required."})
            return
        try:
            raw = self.rfile.read(int(lengths[0]))
            # Reject duplicate keys, not just the last consent value.
            pairs = json.loads(raw, object_pairs_hook=lambda items: items)
            if pairs != [("consent", True)] or pairs[0][1] is not True:
                raise ValueError
            code, body = self.server.state.prepare({"consent": True})
            self.reply(code, body)
        except (ValueError, UnicodeError, TypeError):
            self.reply(400, {"error": "Explicit storage-only consent is required."})
        except Exception:
            self.reply(409, {"error": "Storage action stopped. Recheck paths; partial directories may exist. No DB/env/permissions were changed."})


def serve(port=18734, php="php", composer="composer") -> int:
    if not 1 <= port <= 65535:
        print("BLOCKED: choose a port from 1 to 65535.")
        return 1
    if hasattr(os, "geteuid") and os.geteuid() == 0:
        print("BLOCKED: web setup must run as an unprivileged OS user.")
        return 1
    try:
        server = SetupServer(("127.0.0.1", port), SetupState(php, composer))
    except OSError:
        print("BLOCKED: loopback port unavailable. Choose another --port; do not reuse another service.")
        return 1
    print("Rise Open Core local setup: " + server.origin + "/", flush=True)
    print("Open this URL locally. Ctrl-C stops setup. No DB/provider calls; never enter credentials here.", flush=True)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        server.server_close()
    return 0


PAGE = Path(__file__).with_name("first_install_web.html").read_text(encoding="utf-8")

if __name__ == "__main__":
    raise SystemExit(wizard.main(["web-setup", *sys.argv[1:]]))
