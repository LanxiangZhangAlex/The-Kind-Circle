# Website maintenance

## Editing ordinary pages

Use **Pages → Edit** and the visual block editor. Save, then open the public page and check it on a phone. English and Estonian are separate linked pages: update both versions. Avoid deleting and recreating an existing page because its ID may be used in menus, forms or site configuration.

## Team

Edit **Our team** for English and **Meie meeskond** for Estonian. In Document Overview, expand Columns and a member Group. Each member has an Image, Heading and Paragraph blocks for role, languages, personal message and LinkedIn link, in that order. Use Image → Replace to change the portrait. To add someone, duplicate a member column and replace every field, including the link target and image alternative text.

Portraits use a 4:5 display frame with scoped CSS crops. Replacing the image keeps the original file in the media library; preview the face position after replacing it. The current crop classes are defined in `team-page.css`. Desktop uses columns; phones stack members vertically. Team is a separate page between About us and Resource in the main menu.

## Partners

Use the relevant **Pages** entries in each language to edit the partner page with native blocks. Add or duplicate an existing partner group, upload a logo, and edit its text and external link. If the same partner appears on the homepage, update that separate page section too. Partner entries are not currently a separate structured post type.

## In-person activities

Use **Events** as the source for the activity description, featured image, dates, location and booking settings. Use **Events → Bookings** to review participants. The upcoming/past lists use event data rather than copied frontend cards. Galleries belong in the event body; select images at an appropriate resolution and preview the lightbox.

Before emailing registrants, confirm the event, booking status, recipients and message. Do not expose attendees’ email addresses in a group’s To/CC field. Exported participant lists are private operational data and must stay outside Git.

## Online activities

**City Surprise:** create or edit a round, set its independent featured picture, clue image, hint, task and deadline. The featured picture appears in listings; the clue appears in the round details. Check-ins are shown publicly according to the round’s submission flow, with email kept private. Manage submissions and the selected participant in the City Surprise admin screens.

**Online projects:** maintain projects such as The Sky, The City. Review photo submissions in the moderation screen before publishing them. Participant emails and unpublished photographs must not be committed to this repository.

Sky detail pages show the introduction, submission form/status, then the photo feed at the bottom. Both desktop and mobile show one submission per row; photos fit within a 560px maximum height and retain the lightbox.

The homepage shows in-person activities above online activities. The Activities page groups in-person and online activities in separate columns on desktop. Listings are generated from activity records; editors should not duplicate them manually in page HTML.

## Shared settings and newsletter

Use **Website content** for shared contact and social links, footer text, and form wording. Use **Appearance → Menus** for the EN/ET header and footer menus. Use **Newsletter** for subscribers and campaigns. An installed form or a successful submission is not proof that email was delivered: verify the actual mailbox when testing delivery.

## Routine upkeep

- Before updates, take a full database and media backup plus the current theme/plugin files. A WXR export or Git repository is not a full backup.
- Apply updates in staging first; check plugin compatibility, bookings, form validation, private data access and uploads.
- After changes, verify EN/ET, menu links, desktop/mobile, image sizes and activity status/deadlines.
- Periodically test restoration, review administrative access and remove obsolete private exports from operational storage according to the organisation’s retention decisions.
- Keep code changes in Git; keep live content, registrations, uploads and server configuration in the separate backup process.

## Guest likes and discussion replies

Every public City Surprise check-in and approved Sky photo has a red heart and shared like count. A signed, HttpOnly, SameSite=Lax cookie remembers a browser for 180 days, set on its first like; the server stores a derived visitor hash. Repeat requests for the same desired state do not add duplicate votes. Clearing cookies or using another browser creates another visitor identity, so this is not a one-person-one-vote mechanism.

Discussion replies need only a required nickname (80 characters maximum) and comment (2,000 characters maximum). No login or email is required. Replies are public, load 10 at a time, and use basic rate limiting, duplicate prevention, a honeypot and WordPress's disallowed-comment list. They do not trigger subscription or email notifications. No email, IP or user-agent field is stored in the reply object.

Manage replies in **Comments → Online activity comments / 线上活动评论**. Edit, Unapprove or Trash a reply without withdrawing its parent photo. If a parent entry or activity is no longer public, guest interaction reads and writes are rejected; restoring publication preserves previous reactions and approved replies.

Back up the complete database, including the prefixed `tkc_online_likes` table and WordPress `comments`/`commentmeta`. WXR and photo-export ZIPs alone do not restore likes. Updating Site Tools creates the likes table through an administrator-only schema upgrade. Rolling back plugin files leaves interaction data in the database; a full rollback may also require restoring the database.

## City Surprise description layouts

The task editor accepts either mission-only text or a complete formatted description. A complete description with a **Hint** or **Vihje** heading is rendered as authored, including its headings, gesture and instructions; automatic task sections are not added again. Use the editor heading menu, without editing code. Mission-only rounds retain the automatic hint/mission/gesture layout. Maintain each language separately. The area-hint field still supplies list cards; deadline and clue/cover images remain in their own settings.
