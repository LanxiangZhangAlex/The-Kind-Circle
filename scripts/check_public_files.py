"""Check the staged/tracked publication set, never print secret values."""
from pathlib import Path
import re
import subprocess

ROOT = Path(__file__).resolve().parents[1]
paths = list(filter(None, subprocess.check_output(['git', 'ls-files', '-z'], cwd=ROOT).decode().split('\0')))
if not paths:
    raise SystemExit('No tracked files: stage the intended publication set before checking.')
forbidden = {'backups', 'output', 'tmp', 'uploads', 'node_modules', '.aws', '.ssh'}
patterns = [
    re.compile(rb'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----'),
    re.compile(rb'gh[pousr]_[A-Za-z0-9]{30,}'),
    re.compile(rb'github_pat_[A-Za-z0-9_]{40,}'),
    re.compile(rb'AKIA[0-9A-Z]{16}'),
    re.compile(rb'define\s*\(\s*[\'"]DB_PASSWORD[\'"]\s*,\s*[\'"][^\'"]+[\'"]'),
]
issues = []
count = 0
for name in filter(None, paths):
    p = Path(name)
    if set(p.parts) & forbidden or p.name.startswith('.env') or p.name == 'wp-config.php' or p.suffix.lower() in {'.sql', '.db', '.sqlite', '.pem', '.key', '.wxr', '.zip'}:
        issues.append((name, 'private/runtime/generated path'))
        continue
    # Read the index version: this is the content that will be published.
    data = subprocess.check_output(['git', 'show', ':' + name], cwd=ROOT)
    count += 1
    if any(pattern.search(data) for pattern in patterns):
        issues.append((name, 'possible embedded secret'))
if issues:
    for name, reason in issues:
        print(f'BLOCKED: {name}: {reason}')
    raise SystemExit(1)
print(f'Public-file checks passed for {count} tracked files. Manual review is still required.')
