# Business Networking

A frontend-first WordPress plugin that runs a member-only business directory and networking platform for the Saffa Network site. Members manage their account and business listing entirely from the public site — wp-admin is used only by administrators, for approving members, managing vocabularies, and reviewing pending listings.

Requires WordPress 6.8+, PHP 7.4+.

## Features

**Member accounts**
- Front-end registration and login (no wp-admin access needed). New accounts start as `pending` and require admin approval before they can log in.
- Members can edit their own name, display name, and bio from the front end.
- Self-service account deletion: a member can request deletion, with a 24-hour delay (via WP-Cron) to cancel before it's actioned.

**Business listings**
- A member may own any number of business listings (custom post type `rbn_business`), enforced via WordPress capabilities plus a server-side safety net.
- A single front-end form covers name, logo, description, Business Type, Services, location, and contact details. Every field is mandatory except Website and Logo.
- An activated member's submission publishes immediately; the `pending` path remains as a fallback for the rare unactivated account.
- **Business Type**: single-select dropdown of admin-curated categories, plus an "Other" option, backed by a REST endpoint that reuses matching existing terms instead of creating duplicates.
- **Services**: multi-select type-ahead field with the same find-or-create, duplicate-avoidance behaviour.
- Optional logo upload (JPG/PNG/GIF/WEBP, up to 5MB), stored as the listing's featured image.
- If a submission is rejected (a missing/invalid field, a bad logo), the form redisplays exactly what was typed rather than clearing it — each failed field gets a red border and its own message, and a validly-chosen logo is kept and attached automatically on the next successful attempt even though the file input itself can't be reselected for the member.

**Directory**
- Searchable/filterable directory of published businesses (name/description search, Business Type, Service, and location filters). Server-renders the first page and progressively enhances into an async, no-reload experience via REST.
- The directory page is restricted to logged-in members, with automatic redirect-and-return around the site's own login page.

**Blocks**
- `Business Directory` — the searchable/filterable directory listing.
- `Single Business Profile` — for use in a Site Editor "Single Business" template.
- `Member Profile` — the logged-in member dashboard (profile, business edit, account deletion), or login/registration forms for logged-out visitors.
- `Roomworks Stats Counter` — live counts of members, listed businesses, and cities covered.
- `Author's Business Profile` — shows a post's author's business below the post; opt-in via a post-editor sidebar toggle. If the author owns more than one published business, a radio control in the same panel picks exactly which one to show.

**Admin-side**
- No custom member-management screens — reuses the existing wp-admin Users screen (status column, Approve/Reject row actions).
- Business Type and Services vocabularies are managed as ordinary taxonomies in wp-admin.
- A dedicated Approvals screen for pending business listings and cancelling account-deletion requests.

## Shortcodes

Drop a country name or demonym into existing copy (a Heading/Paragraph block, or anywhere else `do_shortcode()` runs) so it stays correct without hand-editing every occurrence.

**`[rbn_demonym]`** — the noun-form demonym for a country, e.g. "South African" / "South Africans" (not the adjective).
**`[rbn_country]`** — the plain country name, e.g. "United Kingdom".

Both resolve *which* country the same way:

| Attribute | Effect |
|---|---|
| `country="ZA"` or `country="27"` | Explicit override — an ISO 3166-1 alpha-2 code or a country ID. Takes priority over `source`. |
| `source="network"` (default) | The **Network Origin Country** setting (Businesses → Settings → Branding) — which country this deployment represents, e.g. South Africa for a SAFFA-style network. |
| `source="viewer_origin"` | The logged-in viewer's own **Country of Origin** profile field, falling back to `network` when logged out or not set. |
| `source="viewer_current"` | The logged-in viewer's own **Country You Currently Live In** profile field, falling back to the **Default Destination Country** setting (same Branding section) when logged out or not set. |

`[rbn_demonym]` also takes `plural="1"` (default `"0"`, i.e. singular). Both take `case="upper"`, `"lower"`, or `"title"` (default: as stored).

Demonyms are seeded for all ~195 countries and can be corrected or filled in from the Countries screen (Businesses → Countries), which also has a "Restore Missing Defaults" action.

Examples:
```
Built by [rbn_demonym plural="1"], for [rbn_demonym plural="1"]
→ Built by South Africans, for South Africans

ABOUT [rbn_demonym case="upper"] NETWORK
→ ABOUT SOUTH AFRICAN NETWORK

...business owners across the [rbn_country source="viewer_current"] come together...
→ ...across the United Kingdom... (or the viewer's own country, once they've set it)

Fellow [rbn_demonym source="viewer_origin" plural="1"]
→ Fellow South Africans (for a member whose Country of Origin is South Africa)

[rbn_demonym country="NG" plural="1"] and [rbn_country country="GB"]
→ Nigerians and United Kingdom
```

## Development

```
npm install
npm start    # watch/dev build
npm run build # production build
```

Uses `@wordpress/scripts`. Block source lives in `src/`; compiled output goes to `build/`. PHP business logic lives in `includes/`.

## Known limitations

See `PROJECT-STATUS.md` for the full, up-to-date list. Highlights:

- Follows and member "service needs" matching are not built (tables exist, unused).
- No admin UI for pending account-deletion requests beyond the Approvals screen's cancel action — administrators are notified by email.
- The public REST directory endpoints are unauthenticated by design (only public fields are returned).
- No `uninstall.php` — deactivating leaves all data intact.
- No automated tests.
- No plugin settings screen; the restricted-page slug and default Business Type list are constants in code.
- This site's Wordfence firewall blocks any REST request whose query string contains `author=` (its anti-username-enumeration rule), including core's own `?author=` post-collection filter — not just this plugin's endpoints. Filter by author via a URL path segment or a differently-named param instead (see the Author's Business Profile REST route for an example).

## License

GPL-2.0-or-later
