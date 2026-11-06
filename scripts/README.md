# Repository scripts

`package.py` builds WordPress theme/plugin ZIPs in `dist/`, excluded from Git.

`check_public_files.py` checks the Git index, so run it after explicitly staging the intended files. It rejects selected private/runtime paths and common secret patterns without printing matching values. It is a limited guard, not a complete secret scanner; manually review staged text and assets before the first public push or adding exports. It does not prove that every personal detail has been removed.
