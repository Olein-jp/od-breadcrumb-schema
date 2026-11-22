#!/usr/bin/env python3
"""配布 ZIP を作成し、バージョンと収録内容を検証する。"""

import json
from pathlib import Path
import re
import sys
import zipfile

ROOT = Path(__file__).resolve().parents[1]
SLUG = "od-breadcrumb-schema"
FILES = (
    f"{SLUG}.php",
    "includes/class-breadcrumb-schema.php",
    "README.md",
    "readme.txt",
    "LICENSE",
)


def main():
    source = (ROOT / f"{SLUG}.php").read_text()
    version = re.search(r"^ \* Version: (.+)$", source, re.M).group(1)
    if not re.fullmatch(r"(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)", version):
        raise SystemExit("バージョンは 0.0.0 形式で指定してください。")
    if len(sys.argv) > 1 and sys.argv[1] != version:
        raise SystemExit("タグとプラグインのバージョンが一致しません。")

    readme = (ROOT / "readme.txt").read_text()
    stable = re.search(r"^Stable tag: (.+)$", readme, re.M).group(1)
    package = json.loads((ROOT / "package.json").read_text())
    lock = json.loads((ROOT / "package-lock.json").read_text())
    if any(value != version for value in (stable, package["version"], lock["version"], lock["packages"][""]["version"])):
        raise SystemExit("readme.txt・package.json・package-lock.json のバージョンが一致しません。")

    output = ROOT / "dist" / f"{SLUG}.zip"
    output.parent.mkdir(exist_ok=True)
    with zipfile.ZipFile(output, "w", zipfile.ZIP_DEFLATED) as archive:
        for path in FILES:
            archive.write(ROOT / path, f"{SLUG}/{path}")
    with zipfile.ZipFile(output) as archive:
        if archive.testzip() is not None:
            raise SystemExit("ZIP の検証に失敗しました。")
    print(f"配布 ZIP を作成しました: {output}")


if __name__ == "__main__":
    main()
