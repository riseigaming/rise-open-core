#!/usr/bin/env python3
"""Verify an extracted Rise source handoff before dependency installation."""
import hashlib
import json
import sys
from pathlib import Path, PurePosixPath

if len(sys.argv) != 2:
    raise SystemExit('Usage: python3 scripts/verify-source-manifest.py SOURCE_SHA256.json')
root = Path(__file__).resolve().parent.parent
manifest = Path(sys.argv[1]).resolve()
expected = json.loads(manifest.read_text(encoding='utf-8'))
if not isinstance(expected, dict) or not expected:
    raise SystemExit('Empty or invalid source manifest.')
for name, digest in expected.items():
    path = PurePosixPath(name)
    if path.is_absolute() or '..' in path.parts or path.as_posix() != name:
        raise SystemExit('Unsafe manifest path.')
    if not isinstance(digest, str) or len(digest) != 64 or any(c not in '0123456789abcdef' for c in digest):
        raise SystemExit(f'Invalid SHA-256: {name}')
actual = set()
for path in root.rglob('*'):
    if path.is_symlink():
        raise SystemExit(f'Unexpected symlink: {path.relative_to(root)}')
    if path.is_file() and path != manifest:
        actual.add(path.relative_to(root).as_posix())
if actual != set(expected):
    raise SystemExit(f'Unexpected source paths. Missing: {sorted(expected.keys() - actual)}. Extra: {sorted(actual - expected.keys())}.')
for name, digest in expected.items():
    if hashlib.sha256((root / name).read_bytes()).hexdigest() != digest:
        raise SystemExit(f'Source hash mismatch: {name}')
print(f'PASS: {len(expected)} source hashes verified.')
