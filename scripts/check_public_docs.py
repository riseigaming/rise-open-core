#!/usr/bin/env python3
"""Check the published documentation's links, historical-status copy, and book index."""
from __future__ import annotations
import argparse
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
BANNED = {
    "obsolete_internal_status": re.compile(
        r"(?i)(?:\b(?:PASS_LOCAL|PASS_INDEPENDENT|REVISION_REQUIRED|HOLD3|execute_export|approved_paths)\b"
        r"|\b(?:agent[-_ ]ready|internal (?:test|audit)|QA(?: status)?|documentation lifecycle|independent (?:human|install(?:ation)?)|hosted gameplay acceptance|run[0-9]+)\b.{0,110}\b(?:NOT_RUN|IN_PROGRESS|PARTIAL)\b"
        r"|\b(?:NOT_RUN|IN_PROGRESS|PARTIAL)\b.{0,110}\b(?:agent[-_ ]ready|internal (?:test|audit)|independent (?:human|install(?:ation)?)|hosted gameplay acceptance)\b)"
    ),
    "obsolete_release_classification": re.compile(r"(?i)(?:private_reviewer_preview|private_pre_release|private developer preview|private github invitation|private github davet|private teknik kaynak kontrol noktası)"),
    "obsolete_rights_hold": re.compile(r"(?i)\b(?:original|public|source)\s+(?:source\s+)?rights\s+HOLD\b"),
    "informal_installer_reports": re.compile(r"(?i)(?:arkadaşın.{0,75}bilgisayar|48 PASS.{0,30}1 FAIL|gerçek hosted oyun kabulü|local signed finans.{0,50}PASS)"),
}
DEPRECATED = (
    "docs/AGENT_PRODUCT_FACTS.json",
    "docs/AGENT_DOCS_STATUS.json",
    "docs/NOVA_PAYMENT_STATUS.json",
    "docs/ACCEPTANCE_STATUS.json",
    "docs/KNOWN_ISSUES.md",
    "docs/history/README_RUN27_ARCHIVE.md",
)
REQUIRED = (
    "README.md", "AGENTS.md", "LICENSE", "NOTICE", "DISTRIBUTION.md",
    "docs/START_HERE.md", "docs/INSTALL_WIZARD.md", "docs/INSTALL_RUN34.md",
    "docs/developer/HOSTED_IFRAME_GAME.md", "docs/developer/EXTERNAL_GAME_ADAPTER.md",
    "docs/developer/OPENAPI_REFERENCE.md", "docs/gitbook/README.md", "docs/gitbook/SUMMARY.md"
)
PUBLIC_RUNTIME_SURFACES = (
    "app/Providers/AppServiceProvider.php",
    "app/Console/Commands/CheckFreshInstallationCommand.php",
    "resources/views/cms/admin/betting/chess-canonical-read-model.blade.php",
    "scripts/first_install_web.py",
    "scripts/first_install_web.html",
    "scripts/first_install_wizard.py",
)
OBSOLETE_RUNTIME_COPY = re.compile(
    r"(?i)(?:private B1 runtime|private developer preview|private Run34 checkpoint"
    r"|private invitation grants no public|Web acceptance remains NOT_RUN"
    r"|Hosted gameplay acceptance: NOT_RUN|independent_human_acceptance"
    r"|Rise private local setup|RISE OPEN CORE / PRIVATE FIRST INSTALL)"
)

LINK = re.compile(r"(?<!!)\[[^\]]+\]\(([^)]+)\)")


def check(root: Path = ROOT) -> list[str]:
    root = root.resolve()
    errors: list[str] = []
    for name in REQUIRED:
        if not (root / name).is_file():
            errors.append(f"required_missing:{name}")
    for name in DEPRECATED:
        if (root / name).exists():
            errors.append(f"obsolete_public_record:{name}")
    for name in PUBLIC_RUNTIME_SURFACES:
        surface = root / name
        if not surface.is_file():
            errors.append("runtime_surface_missing:" + name)
            continue
        for number, line in enumerate(surface.read_text(encoding="utf-8").splitlines(), 1):
            if OBSOLETE_RUNTIME_COPY.search(line):
                errors.append(f"obsolete_runtime_copy:{name}:{number}")
    documents = [root / name for name in ("README.md", "AGENTS.md", "DISTRIBUTION.md", "UPDATING.md", "CHANGELOG.md")]
    documents.extend((root / "docs").rglob("*.md"))
    documents.extend((root / "docs").rglob("*.mdx"))
    documents.extend((root / "docs").rglob("*.json"))
    for path in documents:
        if not path.is_file() or path.is_symlink():
            errors.append(f"invalid_doc_file:{path.relative_to(root)}")
            continue
        rel = str(path.relative_to(root))
        content = path.read_text(encoding="utf-8")
        for number, line in enumerate(content.splitlines(), start=1):
            for label, regex in BANNED.items():
                if regex.search(line):
                    errors.append(f"{label}:{rel}:{number}")
        if path.suffix not in (".md", ".mdx"):
            continue
        for href in LINK.findall(content):
            target = href.split("#", 1)[0].strip()
            if (not target or target.startswith(("https://", "http://", "mailto:", "tel:", "data:", "/"))
                    or "%" in target):
                continue
            resolved = (path.parent / target).resolve()
            try:
                resolved.relative_to(root)
            except ValueError:
                errors.append(f"outside_repo_link:{rel}:{target}")
                continue
            if not resolved.exists():
                errors.append(f"broken_link:{rel}:{target}")
    summary = root / "docs/gitbook/SUMMARY.md"
    if summary.is_file():
        links = re.findall(r"\]\(([^)#]+\.md)\)", summary.read_text())
        if len(links) != 13 or len(links) != len(set(links)):
            errors.append("gitbook_summary_page_count_or_duplicate")
    source = root / "docs/developer/HOSTED_IFRAME_GAME.md"
    if source.is_file():
        body = source.read_text()
        for contract in ("reserve", "confirm", "release", "handoff", "x-signature"):
            if contract not in body:
                errors.append(f"hosted_contract_missing:{contract}")
    return sorted(set(errors))


def main() -> int:
    parser = argparse.ArgumentParser(description="Validate public Rise developer documentation")
    parser.add_argument("--root", type=Path, default=ROOT)
    args = parser.parse_args()
    problems = check(args.root)
    if problems:
        for problem in problems:
            print("ERROR:", problem)
        return 1
    print("Public documentation integrity: OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
