#!/usr/bin/env python3
"""Deploy site/ and admin/ to reg.ru FTP."""
from __future__ import annotations

import os
import sys
from ftplib import FTP, error_perm
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SITE = ROOT / "site"
ADMIN = ROOT / "admin"

FTP_USER = os.environ.get("BM_FTP_USER", "u3413843_BoostMarine")
FTP_PASS = os.environ.get("BM_FTP_PASS", "")
FTP_HOSTS = ["ftp.boostmarine.ru", "boostmarine.ru"]

SITE_REMOTE = os.environ.get("BM_SITE_REMOTE", "www/boostmarine.ru")
ADMIN_REMOTE = os.environ.get("BM_ADMIN_REMOTE", "www/admin.boostmarine.ru")

SKIP_DIRS = {".venv", ".git", "deploy", "__pycache__", "node_modules", "archive"}
SKIP_FILES = {".env", ".bak"}
SKIP_EXT = {".pyc", ".md"}


def connect() -> FTP:
    if not FTP_PASS:
        raise SystemExit("Set BM_FTP_PASS")
    for host in FTP_HOSTS:
        try:
            print(f"Connecting to {host}...")
            ftp = FTP(host, timeout=120)
            ftp.login(FTP_USER, FTP_PASS)
            print(ftp.getwelcome())
            return ftp
        except Exception as e:
            print(f"  failed: {e}")
    raise SystemExit("FTP failed")


def ensure_dir(ftp: FTP, remote_dir: str) -> None:
    ftp.cwd("/")
    for part in [p for p in remote_dir.split("/") if p]:
        try:
            ftp.cwd(part)
        except error_perm:
            ftp.mkd(part)
            ftp.cwd(part)


def should_skip(path: Path, base: Path, extra_skip: set[str] | None = None) -> bool:
    rel = path.relative_to(base)
    skip_dirs = SKIP_DIRS | (extra_skip or set())
    if any(p in skip_dirs for p in rel.parts):
        return True
    if path.name in SKIP_FILES or path.suffix in SKIP_EXT:
        return True
    if path.suffix == ".bak":
        return True
    return False


def upload_dir(ftp: FTP, local: Path, remote_base: str, extra_skip: set[str] | None = None) -> int:
    n = 0
    for dirpath, dirnames, filenames in os.walk(local):
        dp = Path(dirpath)
        dirnames[:] = [d for d in dirnames if not should_skip(dp / d, local, extra_skip)]
        rel = dp.relative_to(local)
        remote_dir = remote_base if str(rel) == "." else f"{remote_base}/{rel.as_posix()}"
        ensure_dir(ftp, remote_dir)
        ftp.cwd("/")
        for part in remote_dir.split("/"):
            ftp.cwd(part)
        for name in filenames:
            fp = dp / name
            if should_skip(fp, local, extra_skip):
                continue
            with open(fp, "rb") as f:
                ftp.storbinary(f"STOR {name}", f)
            n += 1
            safe = name.encode("ascii", "backslashreplace").decode()
            print(f"  + {remote_dir}/{safe}")
    return n


def main() -> None:
    targets = sys.argv[1:] or ["site", "admin"]
    ftp = connect()
    try:
        if "site" in targets:
            if not SITE.exists():
                raise SystemExit("site/ folder not found")
            print(f"\n=== SITE -> {SITE_REMOTE} ===")
            c = upload_dir(ftp, SITE, SITE_REMOTE)
            print(f"Uploaded: {c} files")
        if "admin" in targets:
            if not ADMIN.exists():
                raise SystemExit("admin/ folder not found")
            print(f"\n=== ADMIN -> {ADMIN_REMOTE} ===")
            c = upload_dir(ftp, ADMIN, ADMIN_REMOTE, extra_skip={"vendor"})
            print(f"Uploaded: {c} files")
    finally:
        ftp.quit()
    print("\nDeploy complete.")


if __name__ == "__main__":
    main()
