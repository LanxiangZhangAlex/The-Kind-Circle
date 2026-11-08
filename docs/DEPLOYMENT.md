# Deployment and rollback

## Existing production site

1. Back up the database, uploads and existing theme/plugin directories. Keep backups private and test that they can be restored.
2. Review the source diff and build packages with `python3 scripts/package.py`.
3. Validate in a separate WordPress staging installation. This repository contains no production credentials or database.
4. Upload the appropriate package through Appearance → Themes or Plugins → Add Plugin → Upload. Replace the existing package only after checking its name and version. Replacing plugin files is different from rerunning a setup action.
5. Check both languages, navigation, current events, booking forms, online submissions, images and phone layouts. Test email delivery to a controlled address when relevant; avoid sending test messages to real participants.

Pushing to GitHub does not update WordPress. No production deployment keys or automated deploy workflow are included.

## Fresh installation

Install WordPress and the dependencies listed in the main README. Configure English and Estonian in Polylang, HTTPS, Europe/Tallinn timezone, email delivery, and backup storage. Install the custom theme and plugin ZIPs.

The plugin includes administrator-only setup screens under Tools, City Surprise and Online projects. Read each screen before running it: some initial setup actions create seed content, update options, or modify page/menu mappings. The original setup is intended for a fresh site, not as an import of the current production state. Test it in a disposable installation first. The independent team-page setup expects an empty named team placeholder on the About pages and valid portrait attachments; construct a normal native page instead if that seed prerequisite is absent.

A full migration requires a separately authorised database/media backup and careful URL/configuration migration; neither is supplied by this repository.

## Rollback

Restore the previous plugin/theme package if the issue is limited to code. If a setup action or content edit changed database records, restore the affected records or an appropriate database backup as well. Media restoration is separate. Do not assume replacing a ZIP reverses database changes.

## Content seed

`site-source/build_content.py` regenerates `site-source/tkc-setup/content.json`, the original bilingual seed. This does not export the live database and does not capture later editor changes. Review generated differences before committing; do not use seed regeneration to overwrite production page edits.

## Site Tools 1.8.0 interaction schema

The first administrator request after this update creates the prefixed `tkc_online_likes` table. Load an admin page before testing guest hearts. Comments use the native WordPress tables with the `tkc_online_reply` type. Include these tables in backups. No production page bodies or existing photo records need migration for the new layout and controls.

Local boundary checks run with `php tests/online-interactions.php` and require SQLite3 and mbstring. They use synthetic records and an in-memory database, not the live website. They cover public/private target gating, nonce and origin validation, anonymous votes, duplicate prevention, comment field validation, privacy-safe responses and moderation visibility. They do not replace WordPress integration and browser checks.
