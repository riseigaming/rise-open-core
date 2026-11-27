#!/usr/bin/env python3
"""Rise Open Core local setup guide: source checks and explicitly authorized storage preparation.

The wizard deliberately does NOT enroll DBs, CREATE schema, alter .env, migrate, bootstrap admin,
run a service, or access a remote provider. See docs/INSTALL_RUN34.md for the human-authorized steps.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import stat
import subprocess
import sys

# Source-only verification must not create unmanifested Python cache files.
sys.dont_write_bytecode = True

ROOT = Path(__file__).resolve().parent.parent
MIN_PHP = (8, 4, 1)
PHP_EXTENSIONS = (
    "dom", "exif", "fileinfo", "gd", "iconv", "intl", "json", "libxml",
    "mbstring", "openssl", "pcre", "pdo_mysql",
)
STORAGE_DIRS = (
    "storage/app/public",
    "storage/framework/cache/data",
    "storage/framework/sessions",
    "storage/framework/views",
    "storage/logs",
    "bootstrap/cache",
    "DB_BACKUPS",
)


def safe_call(args: list[str], timeout: int = 12) -> tuple[int, str]:
    """Bounded, no-shell, non-interactive READ-ONLY subprocess execution."""
    try:
        p = subprocess.run(args, cwd=ROOT, input="", capture_output=True,
                           text=True, timeout=timeout, check=False,
                           env={**os.environ, "COMPOSER_DISABLE_NETWORK": "1",
                                "PYTHONDONTWRITEBYTECODE": "1"})
        return p.returncode, p.stdout + "\n" + p.stderr
    except (OSError, subprocess.TimeoutExpired):
        return 127, ""


def source_check() -> tuple[str, str]:
    if (ROOT / ".git").exists():
        return "BLOCKED", "Export a clean source tree with git archive before verifying the manifest."
    if ((ROOT / "vendor").exists() or (ROOT / ".env").exists()
            or (ROOT / ".env.testing").exists()
            or any(p.is_symlink() or (p.is_file() and p.name != ".gitignore")
                   for part in STORAGE_DIRS for p in (ROOT / part).rglob("*"))):
        return "BLOCKED", "Runtime or environment files already exist. Retain the initial source verification receipt and keep your runtime files."
    manifest = ROOT / "SOURCE_SHA256.json"
    if not manifest.is_file() or manifest.is_symlink():
        return "BLOCKED", "SOURCE_SHA256.json is missing or a symbolic link. Use the manifest shipped with the source."
    rc, output = safe_call([sys.executable, str(ROOT / "scripts/verify-source-manifest.py"), "SOURCE_SHA256.json"], 35)
    if rc == 0 and "PASS:" in output:
        return "PASS", output.strip()
    return "BLOCKED", "Source paths or hashes could not be verified. Check the clean export and its official manifest."


def runtime_check(php: str, composer: str) -> tuple[str, dict]:
    report: dict = {"php": "NOT_RUN", "composer": "NOT_RUN", "composer_lock_platform": "NOT_RUN",
                    "mariadb": "NOT_RUN", "python": sys.version.split()[0], "missing_php_extensions": []}
    if hasattr(os, "geteuid") and os.geteuid() == 0:
        return "BLOCKED", {**report, "reason": "Run PHP and the setup guide as an unprivileged user."}
    rc, out = safe_call([php, "-r", "echo PHP_VERSION;"])
    version = re.search(r"\b(\d+)\.(\d+)\.(\d+)\b", out) if rc == 0 else None
    if not version:
        return "BLOCKED", {**report, "reason": "PHP CLI was not found. Select the intended binary with --php."}
    report["php"] = ".".join(version.groups())
    php_ok = tuple(map(int, version.groups())) >= MIN_PHP and int(version.group(1)) == 8
    rc, modules = safe_call([php, "-m"])
    lower = set(m.strip().lower() for m in modules.splitlines()) if rc == 0 else set()
    missing = sorted(set(PHP_EXTENSIONS) - lower)
    report["missing_php_extensions"] = missing
    if "posix" not in lower:
        report["missing_php_extensions"] = sorted(set(missing + ["posix"]))
    rc, out = safe_call([composer, "--version", "--no-plugins", "--no-scripts"])
    match = re.search(r"(?m)^Composer version (2\.\d+\.\d+)\b", out) if rc == 0 else None
    if match:
        report["composer"] = match.group(1)
        platform_rc, platform_output = safe_call([composer, "check-platform-reqs", "--lock",
                                                   "--no-plugins", "--no-scripts"], 25)
        # A PHP invocation of a shell wrapper can print its source and exit 0
        # without running Composer. Require proof of actual lock verification.
        report["composer_lock_platform"] = ("PASS"
            if platform_rc == 0
            and "Checking platform requirements using the lock file" in platform_output
            and re.search(r"(?m)^php\s+\S+\s+success\b", platform_output)
            else "BLOCKED")
    else:
        report["composer"] = "BLOCKED"
        report["composer_lock_platform"] = "BLOCKED"
    rc, out = safe_call(["mariadb", "--version"])
    if rc == 0:
        report["mariadb"] = "available"
    expected = sum(1 for _ in (ROOT / "database/migrations").glob("*.php"))
    report["migrations"] = expected
    ok = (php_ok and not report["missing_php_extensions"] and report["composer"] != "NOT_RUN"
          and report["composer_lock_platform"] == "PASS" and report["mariadb"] != "NOT_RUN"
          and expected == 72)
    return ("PASS" if ok else "BLOCKED"), report


def enrollment_check() -> tuple[str, str]:
    try:
        physical = ROOT.resolve(strict=True)
        if physical != ROOT or ROOT.is_symlink():
            return "BLOCKED", "The application root must match its physical path without symlinks."
        if sys.platform == "darwin":
            parent = Path("/Library/Application Support/RiseOpenCore/instance-manifests")
        elif sys.platform.startswith("linux"):
            parent = Path("/etc/rise-open-core/instance-manifests")
        else:
            return "BLOCKED", "Root-owned enrollment is unavailable on this platform."
        f = parent / (hashlib.sha256(str(physical).encode()).hexdigest() + ".json")
        # Match the PHP guard's protected ancestry requirement; a root-owned file
        # inside a replaceable or symlinked parent is not a trusted record.
        for entry in (f, *f.parents):
            st = entry.lstat()
            kind_ok = stat.S_ISREG(st.st_mode) if entry == f else stat.S_ISDIR(st.st_mode)
            if not kind_ok or st.st_uid != 0 or st.st_mode & 0o022 or entry.is_symlink():
                return "BLOCKED", "Root-owned enrollment ancestry/permissions are not trusted."
        info = f.lstat()
        if not stat.S_ISREG(info.st_mode) or info.st_uid != 0 or info.st_mode & 0o022:
            return "BLOCKED", "The root-owned enrollment record has unsafe permissions or file type."
        data = json.loads(f.read_text(encoding="utf-8"))
        if data.get("schema") != "rise-local-instance-v2" or data.get("application_root") != str(physical):
            return "BLOCKED", "The enrollment record does not match the physical application root."
        if not any(isinstance(s, dict) and s.get("environment") == "local"
                   and s.get("database") == "rise_b1_candidate" and s.get("connection") == "mysql"
                   for s in data.get("scopes", [])):
            return "BLOCKED", "The enrollment record does not authorize the installation schema."
        return "PASS_RECORD_ONLY", "The record owner and application root match. Verify the running database identity separately."
    except (OSError, ValueError, TypeError, KeyError):
        return "NOT_READY", "The root-owned enrollment record for this source is missing or unreadable. See Run34 sections 2–3."


def env_check() -> tuple[str, str]:
    env = ROOT / ".env"
    try:
        info = env.lstat()
    except FileNotFoundError:
        return "NOT_READY", "Create a private .env from the template with mode 0600."
    if not stat.S_ISREG(info.st_mode) or info.st_mode & 0o077 or info.st_uid != os.getuid():
        return "BLOCKED", "The .env file has an unsafe owner, type or permissions."
    values = {}
    try:
        for line in env.read_text().splitlines():
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, value = line.split("=", 1)
            values[key.strip()] = value.strip().strip("'").strip('"')
    except (UnicodeError, OSError):
        return "BLOCKED", "Could not read .env."
    expected = {"APP_ENV": "local", "APP_DEBUG": "false", "DB_CONNECTION": "mysql",
                "DB_HOST": "127.0.0.1", "DB_DATABASE": "rise_b1_candidate"}
    if any(values.get(k, "").lower() != v for k, v in expected.items()):
        return "BLOCKED", "The local database or application configuration failed the check."
    for k in ("CHESS_INTEGRATION_ENABLED", "CHESS_FRONT_ENABLED", "CHESS_ADMIN_ENABLED",
              "CHESS_WALLET_ENABLED", "NOVA_PAYMENTS_ENABLED", "NOVA_PAYMENTS_REMOTE_SESSIONS_ENABLED",
              "NOVA_PAYMENTS_REMOTE_CREDIT_ENABLED", "SPORTSBOOK_LOCAL_FINANCE_ENABLED",
              "SPORTSBOOK_LOCAL_FINANCE_SANDBOX"):
        if values.get(k, "false").lower() not in ("false", "0", ""):
            return "BLOCKED", "Disable external provider and finance gates for local preview."
    if values.get("SPORTSBOOK_LOCAL_WRITE_TARGET", "disabled").lower() != "disabled":
        return "BLOCKED", "Disable the external sportsbook write target."
    if values.get("DB_URL") or values.get("DATABASE_URL"):
        return "BLOCKED", "DB URL overrides must be empty; use only the verified explicit local profile."
    if values.get("DB_SAFETY_REQUIRE_RECENT_SNAPSHOT", "true").lower() not in ("true", "1"):
        return "BLOCKED", "The existing recent-snapshot safety gate must remain enabled."
    port = values.get("DB_PORT", "")
    if not port.isascii() or not port.isdigit() or not 1 <= int(port) <= 65535:
        return "BLOCKED", "Specify the new local database port."
    if values.get("DB_SOCKET", ""):
        return "BLOCKED", "Leave DB_SOCKET empty for the local TCP profile."
    from urllib.parse import urlsplit
    try:
        app_url = urlsplit(values.get("APP_URL", ""))
        if (app_url.scheme != "http" or app_url.hostname != "127.0.0.1"
                or not app_url.port or app_url.username or app_url.password or app_url.path not in ("", "/")):
            return "BLOCKED", "Set APP_URL to http://127.0.0.1:PORT using your local HTTP port."
    except ValueError:
        return "BLOCKED", "APP_URL is invalid. Verify the local loopback port."
    if not values.get("APP_KEY") or not values.get("DB_USERNAME") or not values.get("DB_PASSWORD"):
        return "NOT_READY", "A new APP_KEY or dedicated local database credentials are missing. Keep these values private."
    return "PASS_CONFIG_ONLY", "Local .env permissions and fields passed. Database connectivity and financial flows require separate checks."


def storage_status() -> tuple[str, str]:
    bad = []
    missing = []
    for part in STORAGE_DIRS:
        dest = ROOT
        for component in part.split("/"):
            dest = dest / component
            if dest.is_symlink():
                bad.append(part)
                break
            if dest.exists():
                st = dest.stat()
                if not stat.S_ISDIR(st.st_mode) or st.st_uid != os.getuid() or (st.st_mode & 0o022):
                    bad.append(part)
                    break
            else:
                missing.append(part)
                break
    if bad:
        return "BLOCKED", "Unsafe storage owner/permissions/symlink: " + ", ".join(sorted(set(bad)))
    return ("NOT_READY", "Missing private directories: " + ", ".join(sorted(set(missing)))) if missing else ("PASS", "Private storage directories are present.")


def prepare_storage(apply: bool) -> tuple[str, str]:
    # Must only write to a pristine source, with caller's explicit --apply and no elevated privileges.
    if hasattr(os, "geteuid") and os.geteuid() == 0:
        return "BLOCKED", "Prepare storage as an unprivileged user."
    stat_code, info = storage_status()
    if stat_code == "BLOCKED":
        return stat_code, info
    if not apply:
        return "DRY_RUN", "Directories: " + ", ".join(STORAGE_DIRS) + ". Use --apply to create missing directories."
    # Walk directory descriptors with O_NOFOLLOW: an ancestor swapped for a
    # symlink between inspection and mkdir must never redirect a browser write.
    if not hasattr(os, "O_NOFOLLOW") or not hasattr(os, "O_DIRECTORY"):
        return "BLOCKED", "Safe directory-descriptor operations are unavailable on this OS."
    flags = os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW
    try:
        for part in STORAGE_DIRS:
            fd = os.open(ROOT, flags)
            try:
                st = os.fstat(fd)
                if st.st_uid != os.getuid() or st.st_mode & 0o022:
                    return "BLOCKED", "Application root owner/permissions are unsafe; unchanged."
                for component in part.split("/"):
                    try:
                        os.mkdir(component, mode=0o700, dir_fd=fd)
                    except FileExistsError:
                        pass
                    child = os.open(component, flags, dir_fd=fd)
                    os.close(fd)
                    fd = child
                    st = os.fstat(fd)
                    if st.st_uid != os.getuid() or st.st_mode & 0o022:
                        return "BLOCKED", "A storage directory has unsafe ownership or permissions."
            finally:
                os.close(fd)
    except OSError:
        return "BLOCKED", "Storage path changed or is unsafe; stopped without permission changes."
    return "PASS", "Private storage, cache and DB_BACKUPS directories are ready."


def diagnostic(php: str, composer: str) -> dict:
    src, src_detail = source_check()
    rt, rt_detail = runtime_check(php, composer)
    enroll, enroll_detail = enrollment_check()
    env, env_detail = env_check()
    storage, storage_detail = storage_status()
    all_preflight_complete = (src == "PASS" and rt == "PASS" and storage == "PASS"
                              and enroll == "PASS_RECORD_ONLY" and env == "PASS_CONFIG_ONLY")
    return {"source": {"status": src, "detail": src_detail},
            "runtime": {"status": rt, "details": rt_detail},
            "enrollment": {"status": enroll, "detail": enroll_detail},
            "environment": {"status": env, "detail": env_detail},
            "storage": {"status": storage, "detail": storage_detail},
            "overall": "READY_FOR_MANUAL_APPLICATION_CHECKS" if all_preflight_complete else "PARTIAL",
            "real_db_install_verified": False}


def interactive(php: str, composer: str) -> int:
    print("\nRISE OPEN CORE / LOCAL INSTALLATION GUIDE")
    print("Use this guide to verify the source and review the local installation prerequisites.")
    print("Follow the manual runbook for database provisioning, enrollment and migrations.\n")
    stages = [
        ("1/5 Source manifest", lambda: source_check()),
        ("2/5 PHP/Composer/MariaDB", lambda: runtime_check(php, composer)),
        ("3/5 Storage", lambda: storage_status()),
        ("4/5 Root-owned enrollment record", lambda: enrollment_check()),
        ("5/5 Private local .env configuration", lambda: env_check()),
    ]
    needs_human = False
    for label, check in stages:
        state, detail = check()
        print("\n" + label + " — " + state)
        print(json.dumps(detail, ensure_ascii=False) if isinstance(detail, dict) else detail)
        if state == "BLOCKED":
            print("Setup stopped. Resolve the reported check before continuing with docs/INSTALL_RUN34.md.")
            return 1
        if state in ("NOT_READY", "PASS_RECORD_ONLY", "PASS_CONFIG_ONLY"):
            needs_human = True
        if label.startswith("3") and state == "NOT_READY":
            print("To prepare private storage: ./rise setup prepare-storage --apply")
            if sys.stdin.isatty():
                choice = input("Create the missing local storage directories? [y/N]: ").strip().lower()
                if choice in ("y", "yes", "e", "evet"):
                    result, info = prepare_storage(True)
                    print(result + ": " + info)
                    if result != "PASS":
                        return 1
                    needs_human = True
        if label.startswith("4"):
            print("Verify the isolated, empty MariaDB instance and the reviewed manifest, then follow Run34 sections 2–3 for enrollment.")
        if label.startswith("5"):
            print("Follow Run34 to prepare .env, generate APP_KEY and configure the local database account. Keep credentials in the private .env.")
        if state == "NOT_READY" and sys.stdin.isatty() and (label.startswith("4") or label.startswith("5")):
            choice = input("Press Enter to recheck after completing the step in a separate terminal, or q to skip: ").strip().lower()
            if choice != "q":
                newer_state, newer_detail = check()
                print("RECHECK — " + newer_state)
                print(json.dumps(newer_detail, ensure_ascii=False) if isinstance(newer_detail, dict) else newer_detail)
                if newer_state == "BLOCKED":
                    return 1
    print("\nMANUAL INSTALL: isolated, empty MariaDB → independent preflight → root-owned enrollment")
    print("→ native database snapshot → locked dependencies → private .env → key:generate → 72 migrations")
    print("→ first admin → app:install:check → CSRF login and save/readback.")
    print("GUIDES: docs/INSTALL_WIZARD.md / docs/FIRST_HUMAN_TEST.md")
    print("Run php artisan app:admin-bootstrap interactively after migrations. Choose a real country and TRY, USD or EUR. English and UTC are the defaults.")
    return 2 if needs_human else 0


def main(argv: list[str] | None = None) -> int:
    p = argparse.ArgumentParser(description="Rise Open Core guided local setup; no automatic database operations")
    p.add_argument("action", nargs="?", choices=("doctor", "install", "setup", "web-setup"), default="install")
    p.add_argument("subaction", nargs="?", choices=("prepare-storage",))
    p.add_argument("--apply", action="store_true", help="Explicitly create only owned private storage directories")
    p.add_argument("--json", action="store_true", help="Machine-readable read-only doctor output")
    p.add_argument("--php", default="php", help="Explicit PHP CLI binary")
    p.add_argument("--composer", default="composer", help="Explicit Composer CLI binary")
    p.add_argument("--port", type=int, default=None, help="Web setup loopback port (default 18734)")
    a = p.parse_args(argv)
    if a.action == "web-setup":
        if a.subaction or a.apply or a.json:
            p.error("web-setup accepts only --port, --php and --composer")
        from first_install_web import serve
        return serve(a.port if a.port is not None else 18734, a.php, a.composer)
    if a.port is not None:
        p.error("--port is for web-setup")
    if a.action != "setup" and a.subaction:
        p.error("prepare-storage requires setup")
    if a.action == "setup":
        if a.subaction != "prepare-storage":
            p.error("setup requires prepare-storage")
        if a.apply and source_check()[0] != "PASS":
            print("BLOCKED: Verify the clean source manifest first.")
            return 1
        status, result = prepare_storage(a.apply)
        print(status + ": " + result)
        return 0 if status in ("PASS", "DRY_RUN") else 1
    if a.action == "doctor":
        report = diagnostic(a.php, a.composer)
        if a.json:
            print(json.dumps(report, ensure_ascii=False, indent=2, sort_keys=True))
        else:
            for k, data in report.items():
                if isinstance(data, dict):
                    print(f"{k}: {data['status']}")
            print("OVERALL:", report["overall"])
        return 0 if report["overall"] == "READY_FOR_MANUAL_APPLICATION_CHECKS" else 1
    if a.json or a.apply:
        p.error("--json is for doctor; --apply is for setup prepare-storage")
    return interactive(a.php, a.composer)


if __name__ == "__main__":
    raise SystemExit(main())
