# The Kind Circle MTÜ

The bilingual WordPress website for **The Kind Circle MTÜ**, a nonprofit community organisation in Tallinn, Estonia.

**Website:** [thekindcircle.ee](https://thekindcircle.ee/) · [English](https://thekindcircle.ee/en/) · [Eesti](https://thekindcircle.ee/et/)

The site helps people find community activities, register for events, share discoveries and photographs, and get involved. Content editors maintain ordinary pages in the WordPress block editor; activity lists read from the relevant activity records.

## Features

- **English and Estonian:** linked pages, navigation and language switching using Polylang.
- **In-person activities:** Events Manager records, registration forms, availability, upcoming and past activity lists, and native image galleries with a lightbox.
- **Online activities:** a shared listing for City Surprise rounds and online projects, displayed below in-person activities on the homepage and beside them on the Activities page.
- **City Surprise:** image clues, area hints, photo tasks, Tallinn-time deadlines, check-in submissions and a selected participant. Public check-ins keep participant email addresses private.
- **The Sky, The City:** photo sharing with organiser review before publication, city details and a photo viewer.
- **Editable site content:** contact details, social links, form copy, metadata, navigation, partner content and native page blocks.
- **Team page:** portraits, names, roles, languages, personal messages and LinkedIn links, edited as native WordPress blocks.
- **Enquiries and newsletter:** Contact Form 7, Flamingo and Newsletter integration.
- **Responsive theme:** mobile navigation and layouts based on the organisation’s visual identity, with locally hosted fonts.

## Repository layout

```text
site-source/
  tkc-community/        Custom block theme and brand assets
  tkc-setup/            The Kind Circle Site Tools plugin
  build_content.py     Generator for the original bilingual page seed
scripts/
  package.py           Build installable theme and plugin ZIPs
  check_public_files.py Check tracked files for private-data mistakes
  README.md            Scope and limits of the checks
docs/
  MAINTENANCE.md       Daily editing and maintenance
  DEPLOYMENT.md        Installation, updates, validation and rollback
.github/workflows/
  validate.yml         PHP syntax and public-file checks
```

The theme version is **1.1.3** and Site Tools is **1.7.5** at this source snapshot. The live site uses WordPress **7.1.4**; Site Tools declares PHP **7.4+**. These are recorded versions, not a recommendation to keep an unsupported runtime. Validate compatibility before upgrading WordPress, PHP or dependencies.

## Running the website

This is a WordPress theme and plugin project. It is not a standalone static site, and there is no `npm start` step.

1. Prepare a WordPress installation with HTTPS, a database and email delivery.
2. Install the dependencies: **Polylang**, **Events Manager**, **Contact Form 7**, **Flamingo** and **Newsletter** from their official sources.
3. Configure English and Estonian in Polylang and set the site timezone to **Europe/Tallinn**.
4. Run `python3 scripts/package.py` to create ZIP packages in `dist/`.
5. Install `dist/tkc-community.zip` as the theme and `dist/tkc-setup.zip` as a plugin.
6. Read [deployment instructions](docs/DEPLOYMENT.md) before using the one-time setup screens.

**This repository does not contain the live database or media uploads.** A clone alone will not reproduce current pages, event registrations, submissions, menus or operational configuration. `content.json` and `build_content.py` describe the original seed content; subsequent changes are held in WordPress. Do not rerun a content generator or initial setup against the production site to update an ordinary page.

## Maintenance

Start with [the maintenance guide](docs/MAINTENANCE.md). Common editing locations:

| Content | WordPress location |
| --- | --- |
| General pages, partners and team | Pages, separately for EN and ET |
| In-person activities and galleries | Events |
| Registrations | Events → Bookings |
| City Surprise rounds and check-ins | City Surprise |
| Other online projects and photo moderation | Online projects |
| Shared details, social links and form wording | Website content |
| Header and footer navigation | Appearance → Menus |
| Newsletter subscribers and campaigns | Newsletter |

A Git push stores source changes on GitHub. **It does not deploy the website.** There is no automatic production deployment configured here.

## Validation

```sh
python3 scripts/check_public_files.py
python3 scripts/package.py
find site-source -name '*.php' -exec php -l {} \;
```

GitHub Actions runs PHP syntax checks and the public-file check. These checks do not replace testing the rendered site, bookings, uploads, email delivery and access controls in a staging environment. Before deployment, back up the database, media and the previous theme/plugin packages. Verify both languages, desktop and mobile after deployment.

## Privacy and publication boundaries

Commit source code and deliberately public documentation only. Keep WordPress configuration, tokens, credentials, database/WXR exports, backups, attendee contact details, newsletter subscriber lists, private submissions and operational logs outside Git. `.gitignore` helps avoid accidental additions; it is not a secret scanner or a substitute for reviewing the staged diff.

Do not store WordPress or hosting passwords in a README, issue, commit, workflow file or example configuration.

## Assets and licensing

Brand artwork, the logo, event photographs and other media belong to their respective rights holders. Public availability of this repository does not grant permission to reuse those assets. The bundled Montserrat and Open Sans font license files are retained beside the fonts.

No additional blanket license is applied to this project. WordPress and third-party plugins retain their own licenses and are not redistributed here, apart from the small Events Manager form template override shipped with the theme. Review applicable upstream terms before redistribution or reuse.
