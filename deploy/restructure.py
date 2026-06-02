#!/usr/bin/env python3
"""Reorganize Boost Marine project into standard site/ + admin/ layout."""
from __future__ import annotations

import re
import shutil
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SITE = ROOT / "site"
ADMIN = ROOT / "admin"

MEDIA_EXT = {".mp4", ".webm", ".mov"}
IMAGE_EXT = {".png", ".jpg", ".jpeg", ".gif", ".webp", ".ico", ".svg"}


def ensure(*paths: Path) -> None:
    for p in paths:
        p.mkdir(parents=True, exist_ok=True)


def move(src: Path, dst: Path) -> None:
    if not src.exists():
        return
    ensure(dst.parent)
    if dst.exists():
        if src.is_dir():
            shutil.rmtree(dst)
        else:
            dst.unlink()
    shutil.move(str(src), str(dst))
    print(f"  move {src.relative_to(ROOT)} -> {dst.relative_to(ROOT)}")


def copy_file(src: Path, dst: Path) -> None:
    if not src.exists():
        return
    ensure(dst.parent)
    shutil.copy2(src, dst)


def replace_in_file(path: Path, replacements: list[tuple[str, str]]) -> None:
    if not path.exists() or path.suffix not in {".php", ".html", ".js", ".css", ".htaccess", ".md"}:
        return
    text = path.read_text(encoding="utf-8")
    orig = text
    for old, new in replacements:
        text = text.replace(old, new)
    if text != orig:
        path.write_text(text, encoding="utf-8")
        print(f"  patch {path.relative_to(ROOT)}")


def restructure_site() -> None:
    print("\n=== SITE ===")
    pages = SITE / "pages"
    css_dir = SITE / "assets" / "css"
    js_dir = SITE / "assets" / "js"
    img_dir = SITE / "assets" / "img"
    media_dir = SITE / "assets" / "media"
    ensure(pages, css_dir, js_dir, img_dir, media_dir)

    for name in ["index.php", "track.php", "sitemap.php", "sitemap.xml", "robots.txt", ".htaccess"]:
        move(ROOT / name, SITE / name)

    for html in ["index.html", "services.html", "equipment.html", "blog.html", "article.html"]:
        move(ROOT / html, pages / html)

    old_assets = ROOT / "assets"
    if old_assets.exists():
        for item in old_assets.iterdir():
            if item.name in ("css", "js"):
                continue
            if item.is_dir():
                move(item, img_dir / item.name)
            elif item.suffix.lower() in MEDIA_EXT:
                move(item, media_dir / item.name)
            elif item.is_file():
                move(item, img_dir / item.name)
        if (old_assets / "css").exists():
            for f in (old_assets / "css").glob("*"):
                copy_file(f, css_dir / f.name)
        if (old_assets / "js").exists():
            for f in (old_assets / "js").glob("*"):
                copy_file(f, js_dir / f.name)
        shutil.rmtree(old_assets, ignore_errors=True)

    for name in ["style.css", "services.css", "equipment.css"]:
        src = ROOT / name
        if src.exists():
            copy_file(src, css_dir / name)
            src.unlink()
    for name in ["script.js", "services.js", "equipment.js", "tracker.js"]:
        src = ROOT / name
        if src.exists():
            copy_file(src, js_dir / name)
            src.unlink()


def restructure_admin() -> None:
    print("\n=== ADMIN ===")
    ensure(
        ADMIN / "assets" / "css",
        ADMIN / "assets" / "js",
        ADMIN / "includes",
        ADMIN / "lib",
        ADMIN / "config",
        ADMIN / "tools",
        ADMIN / "sql",
        ADMIN / "archive",
    )

    move(ADMIN / "admin.css", ADMIN / "assets" / "css" / "admin.css")
    move(ADMIN / "admin.js", ADMIN / "assets" / "js" / "admin.js")
    if (ADMIN / "tracker.js").exists():
        move(ADMIN / "tracker.js", ADMIN / "assets" / "js" / "tracker.js")

    cfg = ADMIN / "config.php"
    if cfg.exists() and not (ADMIN / "config" / "config.php").exists():
        move(cfg, ADMIN / "config" / "config.php")

    move(ADMIN / "install.sql", ADMIN / "sql" / "install.sql")

    for name in [
        "stats_content.php",
        "articles_content.php",
        "main_services_content.php",
        "seo_content.php",
        "webmaster_content.php",
        "ai_content.php",
    ]:
        move(ADMIN / name, ADMIN / "includes" / name)

    for name in ["metrica_api.php", "webmaster_api.php", "ai_generate.php"]:
        move(ADMIN / name, ADMIN / "lib" / name)

    for name in [
        "migrate_ticker.php",
        "seed_services.php",
        "diag.php",
        "hash.php",
        "fix_dirs.php",
        "extract_tinymce.php",
        "cleanup.php",
        "check_config.py",
    ]:
        move(ADMIN / name, ADMIN / "tools" / name)

    for name in [
        "config_check_tmp.php",
        "bot_v6_backup.php",
        "export_old.php",
        "bot_old.php",
        "stats_content_old.php",
        "articles_content.php.bak",
    ]:
        move(ADMIN / name, ADMIN / "archive" / name)

    stub = ADMIN / "config.php"
    if not stub.exists():
        stub.write_text(
            "<?php\nrequire_once __DIR__ . '/config/config.php';\n",
            encoding="utf-8",
        )
        print("  create admin/config.php stub")


def patch_all_paths() -> None:
    print("\n=== PATCH PATHS ===")

    index_php = SITE / "index.php"
    if index_php.exists():
        text = index_php.read_text(encoding="utf-8")
        text = text.replace("$file = 'index.html'", "$file = __DIR__ . '/pages/index.html'")
        text = text.replace("$file = 'services.html'", "$file = __DIR__ . '/pages/services.html'")
        text = text.replace("$file = 'equipment.html'", "$file = __DIR__ . '/pages/equipment.html'")
        text = text.replace("$file = 'blog.html'", "$file = __DIR__ . '/pages/blog.html'")
        text = text.replace("$file = '404.html'", "$file = __DIR__ . '/pages/404.html'")
        text = text.replace("if (file_exists($file))", "if (file_exists($file))")
        index_php.write_text(text, encoding="utf-8")
        print("  patch site/index.php")

    site_replacements = [
        ('href="assets/favicon.png"', 'href="/assets/img/favicon.png"'),
        ('href="assets/css/', 'href="/assets/css/'),
        ('src="assets/js/', 'src="/assets/js/'),
        ('src="./assets/', 'src="/assets/img/'),
        ('src="assets/', 'src="/assets/img/'),
        ("url('/assets/", "url('/assets/img/"),
        ("url(/assets/", "url(/assets/img/"),
        ('assets/default.jpg', '/assets/img/default.jpg'),
    ]

    for html in (SITE / "pages").glob("*.html"):
        replace_in_file(html, site_replacements)

    for css in (SITE / "assets" / "css").glob("*.css"):
        replace_in_file(
            css,
            [
                ("url('/assets/", "url('/assets/img/"),
                ('url("./assets/', "url('/assets/img/"),
                ("url('assets/", "url('/assets/img/"),
            ],
        )

    for js in (SITE / "assets" / "js").glob("*.js"):
        replace_in_file(
            js,
            [
                ("'assets/default.jpg'", "'/assets/img/default.jpg'"),
                ('"assets/default.jpg"', '"/assets/img/default.jpg"'),
            ],
        )

  # equipment banner inline style
    eq = SITE / "pages" / "equipment.html"
    if eq.exists():
        t = eq.read_text(encoding="utf-8")
        t = t.replace("url('assets/", "url('/assets/img/")
        eq.write_text(t, encoding="utf-8")

    ht = SITE / ".htaccess"
    if ht.exists():
        extra = """
# Старые пути картинок (до реструктуризации)
RewriteRule ^assets/(?!css/|js/|img/|media/)(.+)$ assets/img/$1 [L]
"""
        text = ht.read_text(encoding="utf-8")
        if "assets/img/" not in text:
            text = text.replace(
                "# ========== СТАРЫЕ ПУТИ CSS/JS",
                extra + "# ========== СТАРЫЕ ПУТИ CSS/JS",
            )
            ht.write_text(text, encoding="utf-8")
            print("  patch site/.htaccess")

    admin_cfg = ADMIN / "config" / "config.php"
    if admin_cfg.exists():
        text = admin_cfg.read_text(encoding="utf-8")
        if "ADMIN_ROOT" not in text:
            text = text.replace(
                "define('ASSET_VERSION', '20260519');",
                "define('ASSET_VERSION', '20260520');\n"
                "define('ADMIN_ROOT', dirname(__DIR__));",
            )
            text = text.replace(
                "define('UPLOAD_DIR', __DIR__ . '/uploads/');",
                "define('UPLOAD_DIR', ADMIN_ROOT . '/uploads/');",
            )
            text = text.replace(
                "$fullPath = __DIR__ . '/' . $path;",
                "$fullPath = ADMIN_ROOT . '/' . $path;",
            )
            admin_cfg.write_text(text, encoding="utf-8")
            print("  patch admin/config/config.php")

    replace_in_file(ADMIN / "includes" / "head_assets.php", [
        ('href="admin.css', 'href="/assets/css/admin.css'),
    ])
    replace_in_file(ADMIN / "index.php", [
        ("require_once __DIR__ . '/stats_content.php'", "require_once __DIR__ . '/includes/stats_content.php'"),
        ("require_once __DIR__ . '/main_services_content.php'", "require_once __DIR__ . '/includes/main_services_content.php'"),
        ("require_once __DIR__ . '/articles_content.php'", "require_once __DIR__ . '/includes/articles_content.php'"),
        ("require_once __DIR__ . '/webmaster_content.php'", "require_once __DIR__ . '/includes/webmaster_content.php'"),
        ("require_once __DIR__ . '/seo_content.php'", "require_once __DIR__ . '/includes/seo_content.php'"),
        ("require_once __DIR__ . '/ai_content.php'", "require_once __DIR__ . '/includes/ai_content.php'"),
        ('src="admin.js', 'src="/assets/js/admin.js'),
        ("$configFile = __DIR__ . '/config.php'", "$configFile = __DIR__ . '/config/config.php'"),
    ])

    admin_patches = [
        ("require_once __DIR__ . '/metrica_api.php'", "require_once __DIR__ . '/lib/metrica_api.php'"),
        ("require_once __DIR__ . '/webmaster_api.php'", "require_once __DIR__ . '/lib/webmaster_api.php'"),
        ("require_once __DIR__ . '/ai_generate.php'", "require_once __DIR__ . '/lib/ai_generate.php'"),
        ("__DIR__ . '/vendor/autoload.php'", "ADMIN_ROOT . '/vendor/autoload.php'"),
        ("__DIR__ . '/credentials.json'", "ADMIN_ROOT . '/credentials.json'"),
    ]

    for php in ADMIN.rglob("*.php"):
        if "vendor" in php.parts or "archive" in php.parts:
            continue
        replace_in_file(php, admin_patches)

    replace_in_file(ADMIN / "includes" / "stats_content.php", [
        ("require_once __DIR__ . '/metrica_api.php'", "require_once __DIR__ . '/../lib/metrica_api.php'"),
    ])
    replace_in_file(ADMIN / "includes" / "webmaster_content.php", [
        ("require_once __DIR__ . '/webmaster_api.php'", "require_once __DIR__ . '/../lib/webmaster_api.php'"),
    ])
    replace_in_file(ADMIN / "includes" / "ai_content.php", [
        ("require_once __DIR__ . '/ai_generate.php'", "require_once __DIR__ . '/../lib/ai_generate.php'"),
        ("$configFile = __DIR__ . '/config.php'", "$configFile = __DIR__ . '/../config/config.php'"),
    ])
    replace_in_file(ADMIN / "lib" / "webmaster_api.php", [
        ("require_once __DIR__ . '/config.php'", "require_once __DIR__ . '/../config/config.php'"),
    ])
    replace_in_file(ADMIN / "lib" / "metrica_api.php", [
        ("require_once __DIR__ . '/config.php'", "require_once __DIR__ . '/../config/config.php'"),
    ])
    replace_in_file(ADMIN / "lib" / "ai_generate.php", [
        ("require_once __DIR__ . '/config.php'", "require_once __DIR__ . '/../config/config.php'"),
        ("require_once __DIR__ . '/metrica_api.php'", "require_once __DIR__ . '/metrica_api.php'"),
    ])

    replace_in_file(ADMIN / "includes" / "webmaster_content.php", [
        ("config.php", "config/config.php"),
    ])


def main() -> None:
    print(f"Root: {ROOT}")
    restructure_site()
    restructure_admin()
    patch_all_paths()
    print("\nDone. Project root should contain: site/, admin/, deploy/, README.md")


if __name__ == "__main__":
    main()
