#!/usr/bin/env python3
"""Upload specific files to reg.ru FTP with passive mode and retries."""
from __future__ import annotations

import os
import sys
import time
from ftplib import FTP, error_perm
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / "site"
FTP_USER = os.environ.get("BM_FTP_USER", "u3413843_BoostMarine")
FTP_PASS = os.environ.get("BM_FTP_PASS", "")
HOSTS = ["ftp.boostmarine.ru", "31.31.196.178", "boostmarine.ru"]
REMOTE_BASE = "www/boostmarine.ru"

FILES = [
    "pages/index.html",
    "pages/equipment.html",
    "pages/services.html",
    "pages/blog.html",
    "pages/article.html",
    "assets/css/style.css",
    "assets/css/equipment.css",
    "assets/css/services.css",
    "assets/js/script.js",
    "assets/js/equipment.js",
    "assets/js/services.js",
    "assets/js/tracker.js",
    "index.php",
    ".htaccess",
]


def connect() -> FTP:
    if not FTP_PASS:
        raise SystemExit("Set BM_FTP_PASS")
    last = None
    for host in HOSTS:
        for attempt in range(1, 6):
            try:
                print(f"Connect {host} (attempt {attempt})...")
                ftp = FTP(timeout=90)
                ftp.set_pasv(True)
                ftp.connect(host, 21, timeout=90)
                ftp.login(FTP_USER, FTP_PASS)
                print(ftp.getwelcome().strip())
                return ftp
            except Exception as e:
                last = e
                print(f"  fail: {e}")
                time.sleep(3)
    raise SystemExit(f"FTP failed: {last}")


def ensure_dir(ftp: FTP, remote_dir: str) -> None:
    ftp.cwd("/")
    for part in [p for p in remote_dir.split("/") if p]:
        try:
            ftp.cwd(part)
        except error_perm:
            ftp.mkd(part)
            ftp.cwd(part)


def upload_file(ftp: FTP, local: Path, remote_rel: str) -> None:
    remote = f"{REMOTE_BASE}/{remote_rel.replace(chr(92), '/')}"
    parts = remote.split("/")
    ensure_dir(ftp, "/".join(parts[:-1]))
    with open(local, "rb") as f:
        ftp.storbinary(f"STOR {parts[-1]}", f)
    print(f"ok {remote_rel}")


def main() -> None:
    ftp = connect()
    try:
        for rel in FILES:
            local = ROOT / rel.replace("/", os.sep)
            if not local.exists():
                print(f"skip missing {rel}")
                continue
            upload_file(ftp, local, rel)
    finally:
        ftp.quit()
    print("\nUpload complete.")


if __name__ == "__main__":
    main()
