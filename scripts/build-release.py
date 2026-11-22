#!/usr/bin/env python3
"""本番依存だけを含む配布 ZIP を作り、タグとメタデータを照合する。"""

import json
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tempfile
import zipfile

ROOT = Path(__file__).resolve().parents[1]
SLUG = "od-breadcrumb-schema"


def main():
    source = (ROOT / f"{SLUG}.php").read_text()
    version = re.search(r"^ \* Version: (.+)$", source, re.M).group(1)
    if not re.fullmatch(r"(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)", version):
        raise SystemExit("バージョンは 0.0.0 形式で指定してください。")
    if len(sys.argv) > 1 and sys.argv[1] != version:
        raise SystemExit("タグとプラグインのバージョンが一致しません。")
    readme = (ROOT / "readme.txt").read_text()
    stable = re.search(r"^Stable tag: (.+)$", readme, re.M).group(1)
    if stable != version or json.loads((ROOT / "package.json").read_text())["version"] != version:
        raise SystemExit("readme.txt・package.json のバージョンが一致しません。")

    output = ROOT / "dist" / f"{SLUG}.zip"
    output.parent.mkdir(exist_ok=True)
    with tempfile.TemporaryDirectory(prefix=f"{SLUG}-") as temporary:
        stage = Path(temporary) / SLUG
        stage.mkdir()
        for name in (f"{SLUG}.php", "README.md", "readme.txt", "LICENSE", "composer.json", "composer.lock"):
            shutil.copy2(ROOT / name, stage / name)
        shutil.copytree(ROOT / "languages", stage / "languages")
        subprocess.run([
            "composer", "install", "--working-dir", str(stage), "--no-dev",
            "--prefer-dist", "--no-interaction", "--no-progress", "--no-scripts",
            "--no-plugins", "--optimize-autoloader",
        ], check=True)
        if not (stage / "vendor/autoload.php").is_file():
            raise SystemExit("Composer オートローダーがありません。")
        installed = json.loads((stage / "vendor/composer/installed.json").read_text())
        names = {package["name"] for package in installed["packages"]}
        expected = {package["name"] for package in json.loads((ROOT / "composer.lock").read_text())["packages"]}
        if names != expected:
            raise SystemExit("配布物の依存パッケージがロックファイルと一致しません。")
        if not (stage / "languages" / f"{SLUG}-ja.mo").is_file():
            raise SystemExit("日本語翻訳の MO ファイルがありません。i18n:mo を実行してください。")
        with zipfile.ZipFile(output, "w", zipfile.ZIP_DEFLATED) as archive:
            for path in sorted(stage.rglob("*")):
                if path.is_file():
                    archive.write(path, path.relative_to(stage.parent))
        with zipfile.ZipFile(output) as archive:
            if archive.testzip() is not None:
                raise SystemExit("ZIP の検証に失敗しました。")
    print(f"配布 ZIP を作成しました: {output}")


if __name__ == "__main__":
    main()
