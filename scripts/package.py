"""Build installable ZIP packages from the reviewed theme and plugin source."""
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile

ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / 'dist'
OUTPUT.mkdir(exist_ok=True)
for name in ('tkc-community', 'tkc-setup'):
    source = ROOT / 'site-source' / name
    files = sorted(p for p in source.rglob('*') if p.is_file() and not any(part.startswith('.') for part in p.relative_to(source).parts))
    if not files:
        raise SystemExit(f'No source files found: {name}')
    target = OUTPUT / f'{name}.zip'
    with ZipFile(target, 'w', ZIP_DEFLATED) as archive:
        for file in files:
            archive.write(file, file.relative_to(ROOT / 'site-source'))
    print(f'{target.relative_to(ROOT)}: {len(files)} files')
