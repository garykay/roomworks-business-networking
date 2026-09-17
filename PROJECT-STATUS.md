# Business Networking Plugin — Project Status

Custom WordPress plugin (`roomworks-business-networking`) for the Saffa Network site. Frontend-first: members manage everything from the public site, not wp-admin. wp-admin is only used by administrators (approving members, managing the Business Type/Service vocabularies, reviewing pending business listings).

Requires WordPress 6.8+, PHP 7.4+.

---

## What the plugin does

**Member accounts**
- Front-end registration and login forms (no wp-admin access needed). New accounts are created as `pending` and require an administrator to approve them via the Users screen before they can log in.
- Members can edit their own name/display name/bio from the front end.
- Members can request deletion of their own account. This is a two-step, delayed process — see "Account deletion" below — not an immediate self-delete.

**Business listings**
- Each member may have **one** business listing (a custom post type, `rbn_business`), enforced both by WordPress capabilities and a server-side safety net.
- Members submit/edit their business from a single front-end form: name, logo, description, Business Type, Services, location (town/city, county/region, postcode, service area), and contact details (website, phone, email). Every field is mandatory except Website and Logo.
- The logo is optional: a member can upload one (JPG/PNG/GIF/WEBP, up to 5MB), see a preview of the current one, or remove it. It's stored as the listing's featured image, and shown on both the single business profile page and the directory card once uploaded.
- New/edited listings from members are always saved as `pending` — members cannot self-publish. An administrator publishes them via wp-admin.
- **Business Type** is a single-select dropdown of admin-curated categories (seeded with ~30 common trade/business categories on activation), plus an "Other" option that lets a member add a type that isn't listed. New types go through a small REST endpoint that reuses an existing term instead of creating a duplicate if the name already matches one.
- **Services** is a multi-select "type ahead" field: members search existing services as they type, pick one, or create a new one on the fly, with the same duplicate-avoidance behaviour as Business Type. Unlike Business Type this stays free-typing, since members may offer several services and scrolling a long checkbox list isn't a great alternative.
- A public single-business profile page (via a dedicated block, for use in a Site Editor "Single Business" template) shows type, services, location, contact details, and the listing's owner.

**Directory**
- A searchable/filterable directory block lists published businesses (search by name/description, filter by Business Type, Service, and free-text location). Server-renders the first page (works without JS) and progressively enhances into an async, no-reload experience via a small REST API.
- The directory page itself is restricted to logged-in members — a logged-out visitor is redirected to the site's own front-end login page (not wp-login.php), and is sent back to the directory automatically after logging in.

**Account deletion (self-service, delayed)**
- A member can request deletion of their own account from their profile page. They're shown a confirmation screen (not a JS popup — works without JS) before the request is recorded.
- Once confirmed, the account is scheduled for deletion **24 hours later** via WP-Cron, giving the member a window to change their mind. A "Cancel Deletion Request" button appears in its place.
- The administrator is emailed both when a request is made and when it's cancelled.
- Not offered to administrator accounts at all (client- and server-side).
- When the 24 hours elapse, the account (and its business listing) is deleted, the plugin's own tracking-table rows for that user are cleaned up, and the (now former) member is emailed a confirmation at the email address they registered with.

**Admin-side**
- No custom admin pages were built for member/account-status management — it deliberately reuses the existing wp-admin Users screen (a status column, and Approve/Reject row actions for pending members) rather than duplicating that UI.
- Business Type and Service vocabularies are managed as ordinary taxonomies in wp-admin (Categories-style screens), with members only able to assign existing terms or add new ones through the guarded front-end flow above — never edit or delete another member's terms.

---

## What has been done

- Custom post type (`rbn_business`) with capability-based single-business-per-member enforcement (`map_meta_cap`, plus a `save_post` safety net).
- Two taxonomies: Business Type (`rbn_business_category`, hierarchical, seeded with defaults on activation) and Services (`rbn_service`, flat, shared with a `rbn_member_needs` table for future member/service matching — see below).
- Custom capabilities (`RBN_Capabilities`) mapping "member can manage only their own business, never publish directly" onto WordPress's native capability system, rather than ad-hoc checks scattered through the code.
- Front-end registration/login (`RBN_Auth_Forms`), with admin-approval gating (`RBN_Member_Approval`) blocking login for pending/rejected accounts and emailing the admin on new registrations.
- Front-end profile editing (`RBN_Profile_Forms`).
- Front-end business create/edit form (`RBN_Business_Forms`) with full server-side validation of every mandatory field (not just client-side `required` attributes, which can be bypassed).
- Business Type dropdown-with-"Other" and Services type-ahead tag field, each backed by a small REST API (`RBN_REST_Business_Categories`, `RBN_REST_Services`) that finds-or-creates a term so duplicates can't be created either by a careless typo or a deliberate attempt.
- Optional business logo upload on the business form (validated server-side for real image type via `wp_check_filetype_and_ext()` and a 5MB size cap before anything is saved, uploaded through `media_handle_upload()` and set as the listing's featured image), with a preview and a "remove logo" option; displayed on the single business profile page and the directory cards.
- Business directory block: server-rendered query + REST-async filtering/pagination (`RBN_Business_Query`, `RBN_REST_Directory`).
- Single Business Profile block for Site Editor templates.
- Member Profile block: the full logged-in dashboard (profile summary, profile edit, business edit, account deletion) or the login/registration forms for logged-out visitors.
- Front-end-only access restriction on the directory page (`RBN_Access_Control`), redirecting logged-out visitors to the site's own login page (auto-discovered, cached) rather than wp-login.php, with return-to-original-page support after login.
- Self-service, delayed account deletion (`RBN_Account_Deletion`, `RBN_Account_Deletion_Forms`) with WP-Cron scheduling, admin notification emails, a member confirmation email on completion, and cleanup of the plugin's own custom-table rows.
- A full security/coding-standards pass: ran the project's own `vendor/wp-coding-standards` WPCS install against every PHP file (security-relevant sniffs — output escaping, nonce/CSRF verification, input sanitization, SQL query safety). Closed every real gap found (nonces not unslashed before verification, a REST arg missing an explicit sanitize callback, a misplaced `phpcs:ignore` that wasn't actually suppressing anything) and documented the handful of sniff false-positives (e.g. `wp_validate_redirect()` as the sanitizer, `get_block_wrapper_attributes()` returning pre-escaped markup) with justified ignore comments instead of changing already-correct code. Also manually checked every JS file for DOM-XSS sinks — all dynamic content uses `textContent`, never `innerHTML`.
- HTML-entity double-encoding fixed (WordPress kses-encodes special characters like `&` in term names on save; several places were re-escaping already-encoded names, showing `&amp;` literally).

---

## What is still required / not yet done

- **Follows and member "service needs" matching are not built.** Two custom DB tables already exist (`rbn_follows`, `rbn_member_needs` — see `class-rbn-schema.php`) and are referenced only by the account-deletion cleanup code; nothing in the plugin currently reads or writes to them. The Services taxonomy docblock notes it's *shared* with `rbn_member_needs` specifically so a future "member needs this service" feature can be matched against "business offers this service" — that matching feature, and any follow-a-business/follow-a-member feature, would need to be designed and built from scratch.
- **No admin UI for pending account-deletion requests.** Administrators only find out via email; there's no wp-admin list of who's pending, and no admin-side way to cancel one (a member can always cancel their own).
- **WP-Cron timing caveat.** The 24-hour account-deletion delay depends on WP-Cron, which only fires on site traffic. On a low-traffic site (or one with WP-Cron disabled in favour of a system cron job that isn't yet configured), actual deletion could run later than 24 hours — never earlier. Worth confirming real cron is set up if this matters.
- **The public REST directory endpoints are intentionally unauthenticated** (`/wp-json/roomworks-business-networking/v1/businesses` and `/directory-filters`) — this was a deliberate existing design decision (only published/public fields are ever returned), but it does mean hiding the directory *page* from logged-out visitors doesn't hide the underlying data from someone who knows the endpoint URL. Flagging this as a known trade-off, not something broken — happy to lock it down further if that's wanted.
- **`readme.txt` is still the unedited Create Block scaffold boilerplate** — it doesn't describe the actual plugin. Worth writing properly before any public distribution.
- **No `uninstall.php`.** Deactivating the plugin deliberately leaves all data intact (documented as intentional in `class-rbn-deactivator.php`), but there's currently no clean "remove everything" path for a full uninstall either, if that's ever wanted.
- **No automated tests** (unit, integration, or otherwise).
- **Docblock/documentation-only coding-standard findings weren't addressed.** The security pass fixed everything security-relevant; a large number of `Squiz.Commenting.*` ("missing doc comment") findings remain if full WPCS-Docs compliance is wanted later — purely cosmetic, not a functional or security concern.
- **The site-wide DB connection in this dev environment couldn't be reached from WP-CLI** during this project (`Error establishing a database connection` — Local's MySQL instance isn't reachable from the system `wp` binary here), so a few assumptions (e.g. that the directory page's slug is exactly `business-networking`) were verified from the URL/screenshots given rather than the live database. Worth a quick manual check in wp-admin if anything in `RBN_Access_Control::RESTRICTED_PAGE_SLUG` seems off.
- **No plugin settings screen.** Things like the restricted-page slug and the default Business Type list are constants in code rather than configurable from wp-admin — fine for a single site with one developer maintaining it, but would need externalising if that changes.
- **Not tested against multisite**, beyond the `wpmu_delete_user()` code path being present for it in `RBN_Account_Deletion`.
