# Business Networking Plugin — Project Status

Custom WordPress plugin (`roomworks-business-networking`) for the Saffa Network site. Frontend-first: members manage everything from the public site, not wp-admin. wp-admin is only used by administrators (approving members, managing the Business Type/Service vocabularies, reviewing pending business listings, managing countries/communities, and directory/community settings).

Requires WordPress 6.8+, PHP 7.4+.

This is the single consolidated project document — it folds in what used to be three separate files (`PROJECT-STATUS.md`, `business-networking-plugin-scalability-spec.md`, `business-networking-plugin-scalability-tasklist.md`), so there's one place to check for current status. Jump to:

- [What the plugin does](#what-the-plugin-does)
- [What has been done](#what-has-been-done)
- [What is still required / not yet done](#what-is-still-required--not-yet-done)
- [Task List — Scalability & Multi-Country Work](#task-list--scalability--multi-country-work)
- [Task List — Notice Board / Requests Feature](#task-list--notice-board--requests-feature)
- [Appendix: Scalability & Multi-Country Architecture Specification](#appendix-scalability--multi-country-architecture-specification) (the original source requirements doc, kept intact for reference)

---

## What the plugin does

**Member accounts**
- Front-end registration and login forms (no wp-admin access needed). New accounts are created as `pending` and require an administrator to approve them via the Users screen before they can log in.
- Members can edit their own name/display name/bio from the front end.
- Members can request deletion of their own account. This is a two-step, delayed process — see "Account deletion" below — not an immediate self-delete.

**Business listings**
- A member may own **any number** of businesses (no cap — this was a hard one-business-per-member rule until the scalability work, relaxed after the user confirmed they personally own two unrelated businesses and wanted both usable). The dashboard shows a "My Businesses" list with an Edit link per business, sitting above an add/edit form that's blank by default or pre-filled when editing a specific one (`?rbn_edit_business={id}`, ownership re-verified server-side on every save — a business ID from the request is never trusted on its own).
- Members submit/edit a business from that shared form: name, logo, description, **Community** (which of the member's own joined communities this specific business belongs to — required for a new listing), Business Type, Services, location (town/city, county/region, postcode, service area), and contact details (website, phone, email). Every field is mandatory except Website and Logo.
- The logo is optional: a member can upload one (JPG/PNG/GIF/WEBP, up to 5MB), see a preview of the current one, or remove it. It's stored as the listing's featured image, and shown on both the single business profile page and the directory card once uploaded.
- New/edited listings from members are always saved as `pending` — members cannot self-publish. An administrator publishes them via wp-admin.
- **Business Type** is a single-select dropdown of admin-curated categories (seeded with ~30 common trade/business categories on activation), plus an "Other" option that lets a member add a type that isn't listed. New types go through a small REST endpoint that reuses an existing term instead of creating a duplicate if the name already matches one.
- **Services** is a multi-select "type ahead" field: members search existing services as they type, pick one, or create a new one on the fly, with the same duplicate-avoidance behaviour as Business Type. Unlike Business Type this stays free-typing, since members may offer several services and scrolling a long checkbox list isn't a great alternative.
- A public single-business profile page (via a dedicated block, for use in a Site Editor "Single Business" template) shows type, services, location, contact details, and the listing's owner.

**Directory**
- A searchable/filterable directory block lists published businesses (search by name/description, filter by Business Type, Service, and free-text location). Server-renders the first page (works without JS) and progressively enhances into an async, no-reload experience via a small REST API.
- The directory page itself is restricted to logged-in members — a logged-out visitor is redirected to the site's own front-end login page (not wp-login.php), and is sent back to the directory automatically after logging in.
- **On top of that login gate, results are further scoped per viewer**: a business only appears if the viewer has joined its community *and* currently lives in that community's destination country (see the multi-country/community architecture notes below). This is enforced in the shared query layer itself, not just the page gate, so it applies to both the server-rendered listing and the REST endpoint identically.

**Notice board (Requests)** — new, see [Task List — Notice Board / Requests Feature](#task-list--notice-board--requests-feature) for full detail
- Despite the internal naming (`rbn_job` post type, "Notice board" as the block/commit name), this is a **requests board**, not a jobs board: a member posts a task they need done (e.g. "need a new boiler installed"), and other members (typically tradespeople) contact them directly. All user-facing copy calls it a "Request".
- Members post/edit a Request: title, description, category (reuses the existing Business Type taxonomy), urgency, budget (free text), a closing date, and an optional contact phone number (with a per-listing "hide my phone number" toggle). Unlike business listings, **requests auto-publish** — no admin moderation step.
- A request can be posted into more than one of the member's joined communities at once (many-to-many, unlike a business's single community).
- Same community + current-country visibility scoping as the business directory, same server-rendered-first-page-then-async-REST pattern, and — unlike the directory's early REST-nonce bug (see Phase D below) — this endpoint required an authenticated nonce from the start, so that bug wasn't repeated.
- Requests past their closing date stop appearing in listings but are never trashed/archived automatically.

**Account deletion (self-service, delayed)**
- A member can request deletion of their own account from their profile page. They're shown a confirmation screen (not a JS popup — works without JS) before the request is recorded.
- Once confirmed, the account is scheduled for deletion **24 hours later** via WP-Cron, giving the member a window to change their mind. A "Cancel Deletion Request" button appears in its place.
- The administrator is emailed both when a request is made and when it's cancelled.
- Not offered to administrator accounts at all (client- and server-side).
- When the 24 hours elapse, the account (and its business listing) is deleted, the plugin's own tracking-table rows for that user are cleaned up, and the (now former) member is emailed a confirmation at the email address they registered with.

**Admin-side**
- No custom admin pages were built for member/account-status management — it deliberately reuses the existing wp-admin Users screen (a status column, and Approve/Reject row actions for pending members) rather than duplicating that UI.
- Business Type and Service vocabularies are managed as ordinary taxonomies in wp-admin (Categories-style screens), with members only able to assign existing terms or add new ones through the guarded front-end flow above — never edit or delete another member's terms.
- A **Communities** screen (Businesses → Communities) lets an admin add a community (name, origin/destination country, description, status), edit an existing one's name/description/status (origin/destination stay fixed once created — see below), and activate/deactivate — no code changes needed to add a new origin/destination pairing. Communities can also come into existence automatically (see below) — manual creation/editing here is for deliberate curation, not the only path a community comes from.
- A **Directory Settings** screen (Businesses → Settings) covers per-page count, sort order, notification email, account-deletion grace period, and whether a community's origin/destination pair must be unique.

---

## What has been done

- Custom post type (`rbn_business`) with capability-based ownership enforcement (`map_meta_cap` — a member can only ever edit their own businesses, never publish directly). Originally capped at one business per member; that cap was removed during the scalability work (see below) — a member may now own any number.
- Two taxonomies: Business Type (`rbn_business_category`, hierarchical, seeded with defaults on activation) and Services (`rbn_service`, flat, shared with a `rbn_member_needs` table for future member/service matching — see below).
- Custom capabilities (`RBN_Capabilities`) mapping "member can manage only their own business, never publish directly" onto WordPress's native capability system, rather than ad-hoc checks scattered through the code. Capabilities now version themselves (`CAPS_VERSION` + `maybe_upgrade()`, added for the Notice Board work) so a new capability granted to members gets applied to already-active installs, not just fresh ones.
- Front-end registration/login (`RBN_Auth_Forms`), with admin-approval gating (`RBN_Member_Approval`) blocking login for pending/rejected accounts and emailing the admin on new registrations.
- Front-end profile editing (`RBN_Profile_Forms`), now also capturing an optional phone number (`rbn_phone_number` user meta) used as a contact method on Requests.
- Front-end business create/edit form (`RBN_Business_Forms`) with full server-side validation of every mandatory field (not just client-side `required` attributes, which can be bypassed).
- Business Type dropdown-with-"Other" and Services type-ahead tag field, each backed by a small REST API (`RBN_REST_Business_Categories`, `RBN_REST_Services`) that finds-or-creates a term so duplicates can't be created either by a careless typo or a deliberate attempt.
- Optional business logo upload on the business form (validated server-side for real image type via `wp_check_filetype_and_ext()` and a 5MB size cap before anything is saved, uploaded through `media_handle_upload()` and set as the listing's featured image), with a preview and a "remove logo" option; displayed on the single business profile page and the directory cards.
- Business directory block: server-rendered query + REST-async filtering/pagination (`RBN_Business_Query`, `RBN_REST_Directory`).
- Single Business Profile block for Site Editor templates.
- Member Profile block: the full logged-in dashboard — now a tabbed UI (Profile / Communities / Businesses / Requests / Account), restructured from a single long page when the Requests feature was added — or the login/registration forms for logged-out visitors.
- Front-end-only access restriction on the directory page (`RBN_Access_Control`), redirecting logged-out visitors to the site's own login page (auto-discovered, cached) rather than wp-login.php, with return-to-original-page support after login. Extended to also cover the Notice Board page (`RESTRICTED_PAGE_SLUGS`, was previously a single slug).
- Self-service, delayed account deletion (`RBN_Account_Deletion`, `RBN_Account_Deletion_Forms`) with WP-Cron scheduling, admin notification emails, a member confirmation email on completion, and cleanup of the plugin's own custom-table rows.
- A full security/coding-standards pass: ran the project's own `vendor/wp-coding-standards` WPCS install against every PHP file (security-relevant sniffs — output escaping, nonce/CSRF verification, input sanitization, SQL query safety). Closed every real gap found (nonces not unslashed before verification, a REST arg missing an explicit sanitize callback, a misplaced `phpcs:ignore` that wasn't actually suppressing anything) and documented the handful of sniff false-positives (e.g. `wp_validate_redirect()` as the sanitizer, `get_block_wrapper_attributes()` returning pre-escaped markup) with justified ignore comments instead of changing already-correct code. Also manually checked every JS file for DOM-XSS sinks — all dynamic content uses `textContent`, never `innerHTML`.
- HTML-entity double-encoding fixed (WordPress kses-encodes special characters like `&` in term names on save; several places were re-escaping already-encoded names, showing `&amp;` literally).
- **Multi-country/community architecture (Phases A–D done, E mostly done, F/G partial)** — see [Task List — Scalability & Multi-Country Work](#task-list--scalability--multi-country-work) below for the full phase-by-phase breakdown, and the [Appendix](#appendix-scalability--multi-country-architecture-specification) for the original spec.
  - Phase B: a `countries` table, seeded with ~195 ISO 3166-1 countries (`class-rbn-countries.php`), a public search REST endpoint (`class-rbn-rest-countries.php`), and origin/current-country fields on both the registration form and the member profile form, validated server-side. A `CURRENCY_SYMBOLS` lookup was later added to this same class to prefix Request budgets with the poster's local currency symbol.
  - Phase C: `communities` and `community_memberships` tables (`class-rbn-communities.php`, `class-rbn-community-memberships.php`), a wp-admin "Communities" screen for creating/activating communities with zero code changes (`class-rbn-communities-admin.php`), and a "My Communities" / "Available Communities" section on the member dashboard with Join/Leave (`class-rbn-community-forms.php`), server-side-enforced to only allow joining a community whose destination country matches the member's own current country. **Addendum, added after the user hit a real dead end testing a country with no communities yet:** communities are no longer only admin-created — `RBN_Communities::get_or_create_for_pair()` auto-creates one (never a duplicate) the moment a real member registers or updates their profile with an origin/destination pairing nothing covers yet, named "{Origin} in {Destination}" as a safe fallback (not a proper demonym — "South Africans in Denmark" would need pluralisation logic this doesn't have). Auto-created, not auto-joined. **Second addendum, same session:** the Communities screen now supports editing a community's name/description/status after creation (`RBN_Communities::update()`), so those auto-generated fallback names can be tidied up — origin/destination country stay fixed once a community exists (members/businesses are already tied to it by ID) and the slug never changes on rename (it's already used for routing, per spec Section 19). A shared helper, `RBN_Community_Memberships::get_communities_for_user_in_country()`, factors the membership∩country-eligibility logic out so both the community-picker UI and the Request query-scoping below can reuse it.
  - Phase D: businesses now carry a `rbn_community_id` (member picks from their own joined communities when adding a business — pulled forward from Phase E, since Phase D needed something to scope by). The **directory listing** (both the server-rendered block and its REST endpoint, which share one query layer in `class-rbn-business-query.php`) shows a business only when the viewer both (a) has joined its community and (b) currently lives in that community's destination country — membership alone or country alone isn't enough. Moving away from a community's country doesn't lose the membership (still listed in full under "My Communities", never auto-removed) — it just goes dormant until the viewer's current country matches it again, with no need to rejoin. This two-factor model went through two live-testing-driven corrections before landing here (see the task list's Phase D section for the two intermediate versions and why each was wrong) — worth knowing if this area needs touching again, since the "obvious" one-factor version was tried twice and rejected both times. Verified live: switching an account's current country away from its only matching communities correctly dropped the directory to zero results, and switching back restored them immediately. **Scope decision (made with the user):** this only applies to the members-only browsing experience — an individual business's profile page and the public read API are still reachable by direct link, unchanged from the existing trade-off noted below. The **Notice Board / Requests feature reuses this exact pattern** (`class-rbn-job-query.php::community_scope_clause()` explicitly mirrors the business version), with one deliberate divergence: a Request can belong to multiple communities at once (multi-valued meta), since a member may want the same request visible in more than one community they've joined.
  - Phase E: the one-business-per-member cap is gone — a member can own any number of businesses (see "Business listings" above); business visibility levels (spec Section 15) and Phases F–G are not fully built yet.
- **Country seeding is self-healing** (`RBN_Countries::maybe_seed()`, called from `RBN_Schema::maybe_upgrade()` on every request) — found during live testing that the original one-shot seed tied to the schema-version bump could leave the `countries` table empty with no automatic retry (the version flag is set before the seed call, so a failed first attempt would never be retried by the version-mismatch check alone). The fix re-checks row count cheaply on every request and re-seeds if empty, regardless of cause.
- **Notice Board / Requests feature** — see [Task List — Notice Board / Requests Feature](#task-list--notice-board--requests-feature) below for the full breakdown. Shipped complete in a single commit (`8b8a4fc`, "Notice board component", 2026-09-21): new `rbn_job` CPT, front-end form (`RBN_Job_Forms`), query/scoping layer (`RBN_Job_Query`), repository (`RBN_Job_Repository`), REST endpoint (`RBN_REST_Jobs`), and the Notice Board block (server-rendered + async, same pattern as the business directory).

---

## What is still required / not yet done

- **Follows and member "service needs" matching are not built.** Two custom DB tables already exist (`rbn_follows`, `rbn_member_needs` — see `class-rbn-schema.php`) and are referenced only by the account-deletion cleanup code; nothing in the plugin currently reads or writes to them. The Services taxonomy docblock notes it's *shared* with `rbn_member_needs` specifically so a future "member needs this service" feature can be matched against "business offers this service" — that matching feature, and any follow-a-business/follow-a-member feature, would need to be designed and built from scratch.
- **No admin UI for pending account-deletion requests.** Administrators only find out via email; there's no wp-admin list of who's pending, and no admin-side way to cancel one (a member can always cancel their own).
- **WP-Cron timing caveat.** The 24-hour account-deletion delay depends on WP-Cron, which only fires on site traffic. On a low-traffic site (or one with WP-Cron disabled in favour of a system cron job that isn't yet configured), actual deletion could run later than 24 hours — never earlier. Worth confirming real cron is set up if this matters.
- **The public REST directory endpoints are intentionally unauthenticated** (`/wp-json/roomworks-business-networking/v1/businesses` and `/directory-filters`) — this was a deliberate existing design decision (only published/public fields are ever returned), but it does mean hiding the directory *page* from logged-out visitors doesn't hide the underlying data from someone who knows the endpoint URL. **Update:** since the Phase D country-scoping change, a logged-out (or nonce-less) request to `/businesses` now gets zero results rather than every published business — country-scoping treats "can't identify who's asking" the same as "no access", which incidentally narrows this trade-off. It isn't fully closed: a logged-in member with a valid REST nonce can still fetch the endpoint directly, bypassing the directory page's own gate, exactly as before, just now scoped to their own country instead of everything. Still flagging as a known trade-off, not something broken — happy to lock it down further if that's wanted. (The Notice Board's equivalent REST endpoint does not have this gap — it requires `is_user_logged_in()` outright, stricter than the business directory's public-but-scoped approach.)
- **`readme.txt` is still the unedited Create Block scaffold boilerplate** — it doesn't describe the actual plugin. Worth writing properly before any public distribution.
- **No `uninstall.php`.** Deactivating the plugin deliberately leaves all data intact (documented as intentional in `class-rbn-deactivator.php`), but there's currently no clean "remove everything" path for a full uninstall either, if that's ever wanted.
- **No automated tests** (unit, integration, or otherwise) — for either the original plugin or the new Notice Board / Requests code.
- **Docblock/documentation-only coding-standard findings weren't addressed.** The security pass fixed everything security-relevant; a large number of `Squiz.Commenting.*` ("missing doc comment") findings remain if full WPCS-Docs compliance is wanted later — purely cosmetic, not a functional or security concern.
- **The site-wide DB connection in this dev environment couldn't be reached from WP-CLI** during this project (`Error establishing a database connection` — Local's MySQL instance isn't reachable from the system `wp` binary here), so a few assumptions (e.g. that the directory page's slug is exactly `business-networking`) were verified from the URL/screenshots given rather than the live database. Worth a quick manual check in wp-admin if anything in `RBN_Access_Control::RESTRICTED_PAGE_SLUGS` seems off.
- **Some things are still constants in code rather than settings-screen options** — the restricted-page slugs (`RBN_Access_Control::RESTRICTED_PAGE_SLUGS`) and the default Business Type seed list, specifically. (A Directory Settings screen does exist — see Admin-side above — this is about the handful of things it doesn't yet cover.) Fine for a single site with one developer maintaining it, but would need externalising if that changes.
- **Not tested against multisite**, beyond the `wpmu_delete_user()` code path being present for it in `RBN_Account_Deletion`.
- **Remaining scalability items (Phases E–G)** — see the task list below for the itemised checklist. Summary: business/community country-match is handled architecturally rather than by an explicit validation check (a deliberate simplification, not a gap); business visibility levels are undecided/deferred; DB-index adequacy under real data volume hasn't been double-checked; the `LIKE`-based location search hasn't been revisited for scale; and a single consolidated Section 34 acceptance-test run across all eight existing communities hasn't been done as one exercise (individual pairs have been spot-checked live throughout).
- **Notice Board / Requests gaps** — see the task list below. Summary: no moderation/admin-review step exists at all (auto-publish, an intentional asymmetry with businesses — flagging in case a reviewer expects parity); no cap or rate-limiting on how many requests a member can post; the Budget field is free text so it can't be sorted/filtered numerically (deliberate — real budgets are often given as ranges); and the block's pagination-range logic is duplicated between PHP and JS with a comment flagging it needs to be kept in sync manually, the same risk pattern that already exists elsewhere in the plugin.

---

## Task List — Scalability & Multi-Country Work

Companion task list for the multi-country/community architecture. Phases match the numbering in the [Appendix spec](#appendix-scalability--multi-country-architecture-specification), Section 40. Work top to bottom — each phase depends on the one before it.

### Status as of 2026-09-18 (end of session)

**Done: Phases A–D, Phase E's main item, and a Phase C addendum.** Countries, communities, community membership, directory access control (community + current-country match), unlimited businesses per member, and auto-created communities (so a member is never blocked by an admin not having set up their specific country pair yet) are all built and verified live against the dev site (not just code review) — see each phase's section below for specifics and the real bugs that were caught along the way.

**Not started: Phase E's remaining items (business/community country-match validation note, business visibility levels), Phase F's DB-index review and `LIKE`-search revisit, and a single consolidated Phase G test run** (individual pairs have already been spot-checked live throughout, per Phase G's notes below).

**Test data on the dev site**, if picking this up later: 8 test accounts (password `TestPass123!`) spanning 6 origin countries and 5 destination countries, each with a business — see Phase B's section for the full list. 8 communities exist covering every origin/destination pair among them. The admin account owns two businesses ("Gary Kay Plumbing", "Roomworks Media") used to test multi-business support.

**Worth knowing before touching directory visibility again:** it went through two live-testing-driven corrections (see Phase D) before landing on "visible only if the viewer has joined the community AND currently lives in its destination country" — both simpler one-factor versions were tried and rejected. Don't re-simplify this without re-reading why.

---

### Phase A — Architecture Audit (done)

Findings, so Phase B doesn't re-discover them:

- **No country/community model exists today.** The plugin is currently built as if there is exactly one community. Location fields on `rbn_business` are `rbn_town_city`, `rbn_county_region`, `rbn_postcode`, `rbn_service_area` (`includes/class-rbn-post-type-business.php`) — UK-shaped free text, no `country` field anywhere in the schema.
- **Two custom tables already exist** — `rbn_follows`, `rbn_member_needs` (`includes/class-rbn-schema.php`), created via `dbDelta()` with a versioned `rbn_db_version` option and a `maybe_upgrade()` path. New tables (`countries`, `communities`, `community_memberships`) should follow this exact pattern.
- **Roles are administrator + subscriber only** (`includes/class-rbn-capabilities.php`). No country-admin/community-admin tier exists yet.
- **Access control is logged-in/out only** (`includes/class-rbn-access-control.php`). It restricts one page to logged-in users; it has no concept of country or community scoping.
- **"One business per member" is a hard rule**, enforced three ways: `capability_type`/`map_meta_cap` on the CPT (`class-rbn-post-type-business.php`), a `save_post` safety net (`class-rbn-business-repository.php::enforce_single_business`), and the front-end form. This is a product decision independent of the country model, but the two interact — see Decision 1 below.
- **Directory search is a single `WP_Query`** with `tax_query` (category/service) + `meta_query` (`LIKE` on town/county/postcode) — `includes/class-rbn-business-query.php`. Works fine at current scale; `LIKE` meta queries don't index well and should be revisited once multi-country data volume is real (Phase F).
- **A working wp-admin settings-page pattern already exists** (`includes/class-rbn-settings.php`, `register_setting`/`add_settings_field`) — reuse this for the community admin screen rather than inventing a new pattern.
- **The taxonomy seeding pattern already exists** (Business Type/Service: admin-curated defaults seeded on activation, members can add new terms via a find-or-create REST endpoint) — reusable model if countries end up as taxonomy terms rather than a custom table (Decision 2).

---

### Decisions (resolved)

1. **One business per member → no cap at all.** ~~Relaxed to one business per community membership~~ (superseded — see Phase E: the user directly confirmed they personally own two unrelated businesses and want both usable, with no natural cap tied to community count). A member may own any number of businesses; each is independently assigned to one of the member's joined communities, including more than one business in the same community.
2. **Storage model: custom `$wpdb` tables** for `countries`, `communities`, and `community_memberships` — not taxonomies. `community_memberships` needs relational fields (`status`, `role`, `approved_at`) and fast per-user/per-community lookups on every access-control check; taxonomies aren't a good fit for that. This follows the same pattern already used for `rbn_follows`/`rbn_member_needs` in `class-rbn-schema.php`.
3. **All existing data is dummy** except one admin account. No careful zero-downtime migration is needed — Phase C can do a clean activation-time seed of the first community rather than a production data migration.
4. **Community uniqueness is admin-configurable, not a hard DB constraint.** A setting (reusing the `class-rbn-settings.php` pattern) toggles whether a new community's `(origin_country_id, destination_country_id)` pair must be unique. Enforced in application code at community-creation time, not a database `UNIQUE` constraint — so flipping the setting later never requires a migration or risks orphaning data.

---

### Phase B — Country Abstraction (done)

- [x] Design `countries` table (`id`, `name`, `iso_code`, `iso3_code`, `flag_code`, `status`, timestamps) — added to `class-rbn-schema.php`, bumped `RBN_Schema::DB_VERSION` to `1.1.0`
- [x] Seed initial country list on activation — `RBN_Countries::seed_defaults()` (~195 ISO 3166-1 countries), called from `RBN_Activator::activate()` and from `RBN_Schema::maybe_upgrade()` (so existing installs get it too, not just fresh ones)
- [x] Build a country repository/lookup class — `class-rbn-countries.php` (`get_all()`, `get_by_id()`, `search()`, cached via `wp_cache_*`)
- [x] Add `origin_country_id` and `current_country_id` to the user profile — stored as user meta (`rbn_origin_country_id`/`rbn_current_country_id`) via `RBN_Countries::get/set_origin_country()` and `get/set_current_country()`, editable on the front-end profile form (`RBN_Profile_Forms`, `RBN_Templates::profile_edit_form()`), validated server-side before saving
- [x] Country-picker UI — shipped as a native `<select>` (`RBN_Templates::country_field()`) rather than a JS type-ahead: ~195 options is still fast with a native select (type-to-jump, no-JS-required, consistent with this plugin's progressive-enhancement approach elsewhere). `RBN_REST_Countries` (`GET /roomworks-business-networking/v1/countries?search=`) already exists so a JS-enhanced version can be added later without new backend work — not done in this pass, since Business Type/Services' async pattern is templated but not yet wired up here.

- [x] Registration-time country capture — `RBN_Auth_Forms::handle_register()` now validates and saves origin/current country the same way the profile form does; both selects added to the registration form.

**Not done in this pass (left for Phase C, where it's actually needed):** the full origin→current→available-communities onboarding wizard. Registration/profile just capture the two country fields for now; nothing yet uses them to gate access.

**Verified against the running dev site (not just code review):**
- Table creation, seeding, profile-form save/reload, and registration-form save all confirmed working end-to-end (native `<select>` populated with real data, values persisted and correctly re-rendered after save).
- **Found and fixed a real bug in the process:** the one-shot seed tied to the `DB_VERSION_OPTION` bump left the `countries` table empty on this site (`RBN_Schema::maybe_upgrade()` bumps the version flag as the last step of `install()`, and if the immediately-following seed call doesn't complete, the version-mismatch branch that would retry it never fires again). Fixed by adding `RBN_Countries::maybe_seed()` — a cheap, unconditional COUNT(*) check on every request that re-seeds if the table is ever found empty, regardless of why. Re-verified after the fix: seeding is now self-healing.

### Phase C — Community Abstraction (done)

- [x] `communities` table (`id`, `name`, `slug`, `origin_country_id`, `destination_country_id`, `description`, `logo_attachment_id`, `status`, timestamps) and `community_memberships` table (`id`, `user_id`, `community_id`, `role`, `status`, `joined_at`, `approved_at`) — added to `class-rbn-schema.php`, bumped `RBN_Schema::DB_VERSION` to `1.2.0`
- [x] `class-rbn-communities.php` — repository: `create()` (validates countries exist, enforces the uniqueness setting, generates a unique slug), `get_all()`/`get_by_id()`/`get_for_destination_country()`, self-healing `maybe_seed()` (same pattern as `RBN_Countries::maybe_seed()`)
- [x] `class-rbn-community-memberships.php` — repository: `join()`/`leave()`/`is_member()`/`get_for_user()`/`get_communities_for_user()`/`member_count()`
- [x] "Require unique origin/destination pair" admin setting added to `class-rbn-settings.php` (default on), enforced inside `RBN_Communities::create()`
- [x] "South Africa → United Kingdom" seeded as the first community on activation/upgrade (`RBN_Communities::seed_default()`)
- [x] `class-rbn-communities-admin.php` — wp-admin "Communities" screen (Businesses → Communities): add-community form + list with Activate/Deactivate. **Verified live**: used the actual admin form (not a script) to create 7 more communities spanning every origin/destination pair among the Phase B test accounts — zero code changes required, confirming the Phase G acceptance bar early.
- [x] `class-rbn-community-forms.php` + `RBN_Templates::communities_section()` — front-end "My Communities" / "Available Communities" (filtered by the member's current country, per spec Section 9) with Join/Leave, wired into the member dashboard

**Not done in this pass:** the guided multi-step onboarding wizard from spec Section 25 (origin → current → "you're in the UK, here are your communities" as a dedicated flow). Registration and profile already capture origin/current country (Phase B), and the dashboard now shows available/joined communities — but there's no dedicated first-time onboarding screen tying the two together yet. Revisit if the product needs it; not required for Phase D.

**Verified live, including a real bug found and fixed:**
- Schema/seed/admin-CRUD all confirmed via the running dev site, same as Phase B.
- Join → shows in "My Communities", disappears from "Available" → confirmed via a real logged-in session (curl, to avoid touching the admin browser session).
- **Found and fixed:** a joined community still appeared in "Available Communities" too. Cause: `wp_list_pluck()` returns raw (string) DB values, compared with a strict `in_array()` against an int-cast community ID — `1 !== '1'` under strict comparison, so the exclusion filter never matched. Fixed by casting the plucked IDs to int in `RBN_Templates::communities_section()`. Re-verified after the fix.
- **Verified the one real access-control rule that exists so far is actually enforced server-side, not just hidden in the UI**: crafted a raw POST to join a community outside the test user's current country (bypassing the dashboard, which never renders that option) — correctly rejected with `community_wrong_country` rather than silently succeeding.
- Leave confirmed working (removes the row, returns to "Available").
- All 8 Phase B test accounts joined their matching community, so Phase D has real membership data to test access control against.

### Phase D — Access Control (done)

**Scope decision, made with the user before starting:** country-based access control applies only to the members-only directory *browsing* experience (the listing block + its REST endpoint) — not to an individual business's profile page or the public read API, which stay reachable by direct link exactly as before (an existing, deliberate trade-off — see `PROJECT-STATUS.md`). Revisit if the product later needs the stricter "everywhere" version.

**Sequencing note:** Phase D needs something to scope *by*, so the minimal piece of Phase E (a `community_id` on `rbn_business`) was pulled forward here rather than done as a separate pass — see below. (Decision 1's original "one business per community membership" cap was later superseded entirely by the user's own requirements — see the Decisions section and Phase E below; a member can now own any number of businesses, not capped by community count.)

- [x] `rbn_community_id` added to `rbn_business` (`class-rbn-post-type-business.php`, integer, not REST-exposed - no current reader for it outside this plugin's own PHP)
- [x] "Add Your Business" form gained a required **Community** field, scoped to the member's own joined communities only (`RBN_Templates::business_community_field()`); a member with zero joined communities is blocked from creating a business with a clear message; editing an existing (legacy, pre-Phase-C) business without a community is still always possible, never locked out
- [x] `class-rbn-business-forms.php` validates the submitted community server-side via `RBN_Community_Memberships::is_member()` - a client-supplied `community_id` is never trusted, per the spec's Section 10 requirement
- [x] `class-rbn-business-query.php` — the shared query layer used by *both* the block's server render and the REST endpoint — now always applies `community_scope_clause()`: a business is visible only if the viewer (a) has actually joined its community **and** (b) currently lives in that community's destination country. Neither factor alone is enough. A visitor with no match on both gets zero results, never "everything" (spec Section 27). This single change point means the REST endpoint needed no separate check of its own - fixing the shared layer was enough, so `class-rbn-rest-business-categories.php`/`class-rbn-rest-services.php` needed no changes (they're user-scoped-by-nature term pickers on the member's own form, not cross-member data).
  - **Corrected twice after initial shipping, both times from live user testing, neither a code-review catch:**
    1. The first version scoped by destination-country match alone (no membership check) - technically defensible from the spec's own Section 9 wording, but not what the product wants: joining "Indians in the UK" should not also surface "South Africans in the UK" businesses just because they share a country.
    2. Fixing that (membership-only) then surfaced a second gap: membership alone means a business stays visible forever once joined, even after the viewer moves away from that community's country. Since this is a local-services directory (town/city, postcode, service area - a UK plumber is useless to someone who's moved to the US), the final model requires both: membership decides *what you've ever joined* (persists, shown in full on "My Communities", never auto-removed), current country decides *what's relevant to show right now* (a membership from a country you've since left goes dormant, not deleted - move back and it's visible again immediately, no rejoining).
  - Re-verified live after each fix with a real account: joined two of three UK communities, current country US → zero results; switched current country back to UK → all matching businesses reappeared immediately without rejoining anything.
- [x] `class-rbn-access-control.php` needed **no changes** given the scope decision above - it still just gates the directory *page* to logged-in visitors; the actual country filtering now lives in the query layer.
- [x] Community-admin/country-admin roles — **intentionally skipped**, per spec Section 17 ("don't implement every role immediately") - no delegation need has come up yet.
- [x] Spec Section 34's acceptance test actually run against real data (see below), not just described.

**Verified live, including two real bugs found and fixed - one in this feature, one pre-existing and unrelated:**
- Logged in as **Thabo (South Africa → UK)**: directory correctly shows exactly 3 businesses — his own, Priya's (India → UK), Chidi's (Nigeria → UK) — and nothing from Portugal/Germany/Canada/Australia. Confirmed identical results from both the server-rendered first page and the async REST fetch.
- Logged in as **Lindiwe (South Africa → Australia)**: correctly shows exactly 2 — her own and Maria's (Philippines → Australia) — confirming cross-country isolation isn't a fluke of one test case.
- Checked the admin's own view (current country: UK, from earlier Phase B testing): sees the same 3 UK businesses; his own legacy business (no `rbn_community_id` - it predates this field) correctly does **not** appear, exactly as designed, until assigned a community.
- **Bug found (this feature):** the REST endpoint's async path (filter/pagination re-fetch via `view.js`) was never sending an `X-WP-Nonce` header - by original, pre-Phase-D design, since the endpoint was meant to be fully public. Once `community_scope_clause()` started depending on `is_user_logged_in()`, that same lack of a nonce meant WordPress's REST cookie-auth silently treated every real logged-in member as logged-out, breaking the async directory with empty results for actual users, not just in testing. Fixed by embedding a `wp_rest` nonce in `render.php`'s markup and sending it as `X-WP-Nonce` from `view.js`, the same pattern already used for the Business Type/Services fields.
- **Bug found (pre-existing, unrelated to Phase D):** the production build was completely broken - `npm run build` failed on a syntax error in `src/single-business-profile/edit.js`. Someone/something had corrupted the file's JSX and optional-chaining syntax with stray spaces (`context ? .postId` instead of `context?.postId`, `< div` instead of `<div`, etc.) - not something introduced this session, but it blocked verifying the Phase D JS fix, so it was fixed (whitespace only, no logic change) as part of this pass.
- Backfilled `rbn_community_id` on the 8 Phase B/C test businesses to match each owner's joined community, so this was tested against real, varied data rather than a single case.

### Phase E — Business Association (mostly done)

- [x] `community_id` on `rbn_business` — done as part of Phase D above (needed to have anything to scope by)
- [x] **Multiple businesses per member, uncapped.** Prompted by the user directly ("I own 2 businesses... I would like both to show in the communities I belong to"), which superseded the original Decision 1 (see above). Rebuilt:
  - `class-rbn-business-repository.php`: `get_for_user()`/`user_has_business()`/`enforce_single_business()` removed entirely (including its `save_post` hook in the main plugin file) and replaced with `get_all_for_user()` (every business a member owns) and `get_by_id_for_user()` (one specific business, only if actually owned by that user - never trust a bare ID from a request).
  - `class-rbn-business-forms.php`: the save handler now targets a business by a submitted `rbn_business_id` (0 = create new), re-verified server-side via `get_by_id_for_user()` before anything is read or written - **verified live** that a different member cannot edit someone else's business by tampering with this ID, even with a otherwise-valid nonce (correctly rejected with `business_not_permitted`).
  - `class-rbn-templates.php`: the dashboard's single business form became a **"My Businesses" list** (name, status, assigned community, Edit link) sitting above an add/edit form that's blank by default or pre-filled when reached via that Edit link (`?rbn_edit_business={id}`, a read-only GET toggle stripped from the hidden redirect target the same way `?rbn_confirm_deletion=1` already was - no new pattern invented).
  - **Verified live end-to-end**: added a second real business ("Roomworks Media") to the admin's account alongside the existing "Gary Kay Plumbing", assigned to a different joined community, approved it, and confirmed both appear correctly in the directory (each governed independently by the Phase D community+country visibility rule) and both list correctly under "My Businesses" with working Edit links.
- [ ] Validate a business's country always matches its community's destination country (spec Section 12) — **architectural note:** rather than storing a separate, redundant `country_id` on the business and validating it stays in sync with the community's own destination country, the business's country is derived from `community_id` (via `RBN_Communities::get_by_id($community_id)->destination_country_id`) wherever needed. This makes the two impossible to drift apart by construction, rather than needing an ongoing validation check - a deliberate simplification, not an oversight.
- [ ] Business visibility levels (spec Section 15) — implement only if the product needs it for launch, otherwise defer

### Phase F — Search and Directory

- [x] Scope `class-rbn-business-query.php` filters by community/country server-side — never trust a client-supplied `community_id` alone. **Done as part of Phase D** (`community_scope_clause()`) — never ended up a separate pass, since Phase D's access-control work and this item turned out to be the same piece of code.
- [ ] Add DB indexes for the new foreign keys (`communities.origin_country_id`, `communities.destination_country_id`, `community_memberships.user_id`, `community_memberships.community_id` — all already indexed via `class-rbn-schema.php`'s `KEY` definitions, worth double-checking under real data volume). A business's community (`rbn_community_id`) is `wp_postmeta`, not a dedicated column, so "indexing" that one means confirming `wp_postmeta`'s existing `meta_key`/`meta_value` index is adequate at scale, not adding a new column index.
- [ ] Revisit the `LIKE`-based location search once real multi-country data volume exists

### Phase C addendum — Auto-Created Communities (done, added after initial Phase C ship)

Raised by the user after testing a country with no communities yet (South Africa → Denmark): a real member should never hit a dead end just because no admin has manually created their specific origin/destination pairing. Manual admin creation (the original Phase C screen) still exists for deliberate curation, but is no longer the *only* way a community comes into being.

- [x] `RBN_Communities::get_for_pair()` — finds an existing community for a pair regardless of the uniqueness setting (always "is there already one", not "is duplication currently allowed")
- [x] `RBN_Communities::get_or_create_for_pair()` — returns the existing community for a pair, or creates one (status active) if none exists. Never creates a duplicate no matter how `require_unique_community_pair` is configured, since it always checks `get_for_pair()` first.
- [x] Wired into both places a member's origin/current country is saved: `RBN_Auth_Forms::handle_register()` and `RBN_Profile_Forms::handle_request()` — registering or updating your profile with a new country combination auto-creates the matching community if needed.
- [x] Auto-created, not auto-joined — the community exists and appears in "Available Communities" immediately, but joining it is still a deliberate click, consistent with every other community.
- **Naming limitation, called out to the user rather than silently shipped:** auto-created communities are named "{Origin} in {Destination}" (e.g. "South Africa in Denmark"), not a proper demonym ("South Africans in Denmark") — pluralising nationalities correctly (Poland → Poles, not "Polands") isn't something that can be generated reliably. **Follow-up, same session:** the admin Communities screen now supports renaming (see below), so this is no longer a dead end.

**Verified live:**
- Registered a real account (South Africa → Denmark, a country with zero prior communities) → "South Africa in Denmark" auto-created, active, 0 members, correctly excluded from the new member's own community count (they weren't auto-joined).
- Registered a second account with the identical pair → confirmed no duplicate was created; the second account correctly resolved to the same existing community.

#### Follow-up: Edit/rename communities in wp-admin

- [x] `RBN_Communities::update()` — updates name/description/status only. Origin/destination country are intentionally **not** editable once a community exists: members and businesses are already tied to it by ID, and silently changing which country pair it represents would move all of them without anyone choosing that.
- [x] Renaming deliberately never touches the slug — it's already used to route to the community (spec Section 19), and changing it to match a new name would break any existing link.
- [x] `class-rbn-communities-admin.php`: the "Add Community" form and list now double as an edit view — each row got an **Edit** button (`?rbn_edit_community={id}`, a read-only GET toggle re-verified server-side, same pattern as `?rbn_edit_business={id}` on the front end) that pre-fills the form for that one community, with origin/destination shown as read-only text and a note explaining why.
- **Verified live**: renamed the auto-created "South Africa in Denmark" to "South Africans in Denmark" — slug stayed `south-africa-in-denmark`, unchanged. Also verified the empty-name validation still rejects a crafted request with a blank name (bypassing the HTML5 `required` attribute via a raw POST), and that the rejected attempt left the community's name untouched rather than partially saving.

### Phase G — Scalability Testing

- [x] Add a second community end-to-end: South Africa → Australia — must require zero code changes, only admin/data entry. **Already done and verified during Phase C** — created via the live "Communities" admin form (not a script) alongside 6 other origin/destination pairs, no code touched.
- [x] Add a third community with a different origin: India → United Kingdom — same requirement. **Also already done during Phase C**, same batch of 7 communities created live.
- [ ] Re-run the Section 34 acceptance test across all three (now eight) communities in one pass, through frontend, REST and DB access paths. Individual pairs have each been spot-checked live during Phases C/D/E (see those sections above), but a single consolidated run across every community/test account together hasn't been done as one exercise.


---

## Task List — Notice Board / Requests Feature

### Status as of 2026-09-21

**Done:** the whole feature — CPT, forms, query/REST, block, dashboard integration — shipped in a single commit (`8b8a4fc`, "Notice board component"). Despite the internal name (`rbn_job` CPT, "Notice board" as the block/commit name), all user-facing copy calls this a **Request**: a member posts a task they need done (e.g. "need a new boiler installed"), and other members (typically tradespeople) contact them directly — not a job-vacancy board.

**Not started / open questions:** no moderation/admin-review workflow (may be intentional — see below, worth confirming with the product owner since it's the opposite of how businesses work), no automated tests, no per-member cap or rate-limiting on posting requests, no numeric budget field (sorting/filtering by budget isn't possible by design).

- [x] `rbn_job` custom post type (`includes/class-rbn-post-type-job.php`) — `map_meta_cap: true`, capability_type `rbn_job`/`rbn_jobs`, slug `/job/`, `show_in_rest: false` deliberately (a public REST posts controller would bypass the members-only gating that `RBN_REST_Jobs` and `RBN_Access_Control` enforce; businesses accept that trade-off, jobs don't need to)
- [x] **Requests auto-publish — no admin review step**, unlike business listings. Members are granted `publish_rbn_jobs` directly (`RBN_Capabilities::JOB_MEMBER_CAPS`). Ownership-only enforcement via `map_meta_cap`, verified in `class-rbn-job-forms.php` (only the request's owner can edit it, checked server-side, never trusting a submitted ID alone)
- [x] Reuses the existing Business Type taxonomy (`rbn_business_category`) for the Request's category — attached to `rbn_job` too (`class-rbn-taxonomy-business-category.php`) rather than inventing a separate jobs taxonomy
- [x] Community scoping matches Phase D's pattern closely: `class-rbn-job-query.php::community_scope_clause()` explicitly mirrors `RBN_Business_Query::community_scope_clause()`, same "logged out or no eligible community → force zero results" approach. One real divergence: Requests are many-to-many with communities (multi-valued `rbn_community_id` meta, `compare => IN`), businesses are single-valued — needed because a member can post the same request into every community they've joined at once
- [x] New shared helper `RBN_Community_Memberships::get_communities_for_user_in_country()` factors out the membership∩country-eligibility logic used by both the Request form's community checkboxes and the query scope clause, ordered oldest-joined-first (the form defaults to this community when none are explicitly checked)
- [x] REST: `RBN_REST_Jobs` (`includes/class-rbn-rest-jobs.php`) — both `/jobs` and `/job-filters` routes require `is_user_logged_in()` (stricter than the business directory's public-but-scoped REST). This commit already applied the fix for the REST-nonce bug documented in Phase D above (`render.php` embeds a `wp_rest` nonce, `view.js` sends it as `X-WP-Nonce`) — the bug was not repeated here
- [x] Block (`src/roomworks-community-notice-board/`) — full async pattern matching the business directory: `render.php` server-renders page 1 from `$_GET` (works without JS, no nonce needed for GET reads), `view.js` progressively enhances the filter form + pagination into `fetch()` calls against the REST endpoint
- [x] Capability versioning: `RBN_Capabilities::maybe_upgrade()` + `CAPS_VERSION` option — self-healing re-run of `add_caps()` on already-active installs, same self-healing idea as `RBN_Schema::maybe_upgrade()` / `RBN_Countries::maybe_seed()`. Needed because `add_caps()` previously only ran once, at activation, so an existing install would never have picked up the new job-posting capability otherwise
- [x] Currency-aware budget display: `RBN_Countries::CURRENCY_SYMBOLS` + `currency_symbol()` prefixes the free-text Budget field with the poster's current-country currency symbol at display time (`class-rbn-job-query.php::format_budget()`)
- [x] Contact info: new optional `rbn_phone_number` user-meta field (profile form), shown on the request's single page as a contact method unless the poster ticks a per-listing "hide my phone number" checkbox (`rbn_hide_phone` post meta)
- [x] Dashboard restructured into a tabbed UI (Profile / Communities / Businesses / Requests / Account) to fit the new "My Requests" section + add/edit form (`class-rbn-templates.php`) — the only tangential piece of this large diff is `profile_header()` replacing `profile_summary()` (a cosmetic cover-banner redesign, not functionally required by Requests)
- [x] Single Request page: content appended via the `the_content` filter (`RBN_Post_Type_Job::append_details_to_content`) rather than a dedicated Site-Editor block/template, reusing the Notice Board block's compiled CSS rather than shipping a duplicate stylesheet
- [x] Page-level gating: `RBN_Access_Control::restrict_business_directory()` renamed to `restrict_members_only_pages()`, now covers both the `business-networking` and `notice-board` page slugs (`RESTRICTED_PAGE_SLUGS`, was a single slug before) — same login-gate treatment as the directory
- [x] Closing-date logic: requests past `rbn_closing_date` are excluded from listings via `not_closed_clause()`, but never trashed/archived — no cleanup job, they just stop appearing
- [ ] **No moderation/approval workflow at all** — by design (auto-publish), the opposite of businesses' pending-review gate. Worth an explicit product decision on whether that's intended long-term, since it's an asymmetry a future reviewer could easily assume is a bug
- [ ] No per-member cap or rate-limiting on posting requests — a member could theoretically spam requests since there's no admin review step to catch it (mirrors businesses' current no-cap state, but businesses at least get admin review before going live)
- [ ] No automated tests for any of the new classes (consistent with the rest of the plugin — see "What is still required" above)
- [ ] Budget field is free text (not numeric) — deliberate, since real-world budgets are often given as ranges ("200–400"), but means sort/filter-by-budget isn't possible; worth flagging if that's ever requested
- [ ] `urgency_options()` does a raw `$wpdb->get_col()` against postmeta to populate its filter dropdown — same pattern/cost profile as the equivalent business-filter code (not a regression), scales linearly with total request count, no caching
- [ ] The block's `paginationRange()` in `view.js` is a manual JS port of `RBN_Templates::pagination_range()` in PHP, with a comment flagging "keep in sync manually" — no shared source of truth; same risk pattern that already exists elsewhere in this plugin, not new here, but worth knowing if pagination logic ever needs to change

---

## Appendix: Scalability & Multi-Country Architecture Specification

The original technical specification that drove the scalability work above, kept verbatim (headings nested one level deeper to fit under this document) for reference. This is the source requirements document — read it before touching country/community architecture again.


#### Purpose

This document is a technical specification for expanding an existing WordPress business networking plugin into a scalable, multi-community, multi-nationality, multi-country platform.

The original concept is a business network for immigrant communities, for example:

- South Africans in the United Kingdom
- South Africans in Australia
- Indians in the United Kingdom
- Poles in Germany
- Filipinos in Canada
- Brazilians in Portugal

The plugin must **not** be hard-coded around any specific nationality or country.

The goal is to create a reusable architecture in which countries and communities are data, not code.

---

### 0. Deployment Architecture — Single Installation, Single Domain

This is an overriding architectural decision that constrains everything below. Read this before any other section.

#### Decision

> The initial and preferred architecture is **one global WordPress installation, one database, one codebase, and one primary global domain.** Countries and communities are data-driven entities within that single platform, not separate sites.

```text
YOURGLOBALDOMAIN.COM
        │
ONE WORDPRESS INSTALLATION
        │
BUSINESS NETWORK PLUGIN
        │
   ┌────┼────┐
   │    │    │
  🇬🇧UK  🇦🇺AU  🇨🇦CA   ← data, not sites
   │    │    │
 ┌─┼─┐ ...  ...
🇿🇦 🇮🇳 🇳🇬
```

#### WordPress Multisite must NOT be used

Do not build this on WordPress Multisite. Multisite solves a different problem — many independently administered sites (separate content, plugins, admins) under one WordPress core. This product is the opposite: **one application, many communities**, all sharing the same code, admin system, and (initially) the same database.

Do not introduce Multisite unless a future requirement specifically and explicitly justifies it. It must not be assumed, defaulted to, or introduced "for scalability" — the country/community data model in this spec is how scalability is achieved instead.

#### Country-specific domains/TLDs are a presentation concern, not an architectural one

Do not build country detection, access control, or routing logic that depends on the domain or TLD (`.co.uk`, `.com.au`, etc.). The single source of truth for a user's country is the explicit `current_country_id` captured at onboarding (see Section 25), never the domain they arrived on or their IP address.

Countries should be reachable as routes/paths within the one installation, e.g.:

```text
yourdomain.com/uk/
yourdomain.com/uk/south-africans/
yourdomain.com/australia/south-africans/
```

(see Section 19 for full routing guidance). A country-specific domain like `yourbrand.co.uk` may be pointed at this same installation later purely as a marketing/vanity layer that resolves to `/uk/` — that is a routing/DNS decision, not a reason to fork the codebase or database.

#### Keep the door open for future regional splits — without building for it now

Do not implement multi-region/multi-server deployment now. But avoid decisions that would make it impossible later, specifically:

- Keep country/community data cleanly partitionable by `destination_country_id` (already required by the data model in this spec) so that, in principle, a future "UK instance" and "US instance" of the same plugin/codebase could each run against their own database if the business ever needs geographically separated infrastructure.
- Do not build features that silently assume global cross-country data access (e.g. a single global search index spanning all countries with no scoping) — see Section 27.
- If/when a genuine need arises to move a user or business between countries, or to browse another country's directory, that must be an explicit, permissioned feature (Section 27), not a side effect of shared infrastructure.
- No global identity/federation layer between installations is required now. Do not build one speculatively. If regional splits are ever pursued, that layer would be designed at that time, informed by real requirements.

#### Summary for the coding agent

> Build one WordPress installation, one plugin, one database, one primary domain. Country and community isolation is enforced by the data model and access-control rules in this spec, not by separate sites, separate installs, or separate domains. Do not introduce WordPress Multisite. Do not make the domain/TLD part of any authorization or country-detection logic.

---

### 1. Core Product Concept

The fundamental model should be:

> **Origin country → Destination country = Community**

Examples:

```text
South Africa → United Kingdom
South Africa → Australia
India → United Kingdom
Poland → Germany
Brazil → Portugal
```

A user's current/destination country determines the country environment they can access.

Their origin country determines which immigrant community or communities they can belong to within that destination country.

The system should therefore distinguish clearly between:

1. Platform
2. Country
3. Community
4. User/member
5. Business
6. Business/network interactions

---

### 2. Most Important Architectural Principle

#### Do NOT hard-code countries or nationalities

Avoid logic such as:

```php
if ($country === 'South Africa') {
    ...
}
```

Avoid variables, database tables, routes, templates, or permissions that are specific to South Africa or the UK.

Instead, countries must be records in a reusable country data model.

For example:

```text
countries

id
name
iso_code
iso3_code
flag_code
status
created_at
updated_at
```

Then:

```text
South Africa | ZA
United Kingdom | GB
Australia | AU
Canada | CA
Germany | DE
India | IN
Nigeria | NG
Poland | PL
Portugal | PT
...
```

The exact country list can be seeded initially and expanded later.

Use ISO country codes rather than relying on country names for internal logic.

---

### 3. Recommended Core Data Model

Before making major changes, inspect the existing plugin and determine whether its current database structure can be extended safely.

Do not rewrite working functionality unnecessarily.

The desired conceptual model is:

```text
PLATFORM
   |
   +-- COUNTRIES
   |
   +-- COMMUNITIES
   |      |
   |      +-- origin_country
   |      +-- destination_country
   |
   +-- USERS / MEMBERS
   |      |
   |      +-- current_country
   |      +-- origin_country
   |      +-- community memberships
   |
   +-- BUSINESSES
   |      |
   |      +-- owner/member
   |      +-- community
   |      +-- location
   |
   +-- NETWORK INTERACTIONS
          |
          +-- referrals
          +-- recommendations
          +-- introductions
          +-- messages
          +-- events
          +-- jobs
          +-- requests
```

---

### 4. Countries

Create or adapt a reusable country entity/table.

Suggested fields:

```text
id
name
iso_code
iso3_code
flag_code
status
created_at
updated_at
```

Requirements:

- ISO 3166 country codes should be used.
- Country names must be editable where appropriate.
- Do not use country names as database keys.
- Country records should have stable IDs.
- Country selection should use searchable dropdowns where appropriate.
- The country system should be reusable by users, businesses, communities and locations.

---

### 5. Communities

A community represents an immigrant/business community in a destination country.

Suggested structure:

```text
communities

id
name
slug
origin_country_id
destination_country_id
description
logo
status
created_at
updated_at
```

Example:

```text
id: 1
name: South Africans in the UK
slug: south-africans-in-the-uk
origin_country_id: South Africa
destination_country_id: United Kingdom
```

Another:

```text
id: 2
name: South Africans in Australia
slug: south-africans-in-australia
origin_country_id: South Africa
destination_country_id: Australia
```

Another:

```text
id: 3
name: Indians in the UK
slug: indians-in-the-uk
origin_country_id: India
destination_country_id: United Kingdom
```

The plugin must not assume that a community is always represented by a particular nationality.

The community name should be configurable.

---

### 6. Community Uniqueness

Consider enforcing a unique constraint around:

```text
origin_country_id + destination_country_id
```

if the product model permits only one primary community for each origin/destination pair.

However, do NOT automatically enforce this if the product needs to support multiple organisations serving the same community in the same country.

Before implementing this constraint, inspect the existing plugin/business requirements.

If multiple communities can exist for the same origin/destination pair, use a unique slug instead and allow multiple community records.

Example:

```text
South Africans in the UK — Dorset
South Africans in the UK — London
South Africans in the UK — National
```

This is an important future scalability consideration.

---

### 7. Users / Members

Use normal WordPress users for authentication.

Do not create a second authentication system unless there is a compelling reason.

The user profile should contain or reference:

```text
origin_country_id
current_country_id
```

Potential additional profile data:

```text
city
region
postcode
profile_visibility
business_member_status
```

Avoid storing country names directly on the user.

Use foreign keys/references to the country records.

---

### 8. Community Membership

Do not assume that one user can only belong to one community.

Use a membership relationship.

Conceptually:

```text
community_memberships

id
user_id
community_id
role
status
joined_at
approved_at
```

Possible roles:

```text
member
business_owner
community_admin
moderator
country_admin
platform_admin
```

Possible statuses:

```text
pending
active
suspended
rejected
```

This allows the system to evolve.

For example, someone from South Africa living in the UK might belong to:

```text
South Africans in the UK
```

and potentially a regional community such as:

```text
South Africans in Dorset
```

if the product later supports regional communities.

---

### 9. Country-Based Access Control

This is a critical requirement.

The user's **current country/destination country** should determine the country environment they can access.

Example:

User:

```text
origin_country = South Africa
current_country = United Kingdom
```

They may access UK communities such as:

```text
South Africans in the UK
Indians in the UK
Nigerians in the UK
Poles in the UK
...
```

They should not automatically access:

```text
South Africans in Australia
South Africans in Canada
South Africans in Portugal
```

unless an explicit cross-country feature is later introduced.

---

### 10. Security Rule

Never rely solely on hiding UI elements.

Access restrictions must be enforced server-side.

Every relevant request should verify:

```text
authenticated user
        ↓
current country
        ↓
community destination country
        ↓
membership / permission
        ↓
requested resource
```

For example:

```php
$userCountryId = get_user_current_country_id($userId);
$community = get_community($communityId);

if ($community->destination_country_id !== $userCountryId) {
    deny_access();
}
```

The exact implementation must match the existing plugin architecture.

The important requirement is:

> A user must not be able to bypass country restrictions by manually changing a URL, REST API parameter, AJAX request, shortcode attribute, post ID, or database identifier.

---

### 11. WordPress Capability Model

Use WordPress capabilities where appropriate.

Do not rely entirely on custom `if` statements scattered throughout the plugin.

Potential capabilities:

```text
manage_network
manage_country
manage_community
manage_members
manage_businesses
moderate_businesses
verify_business
edit_own_business
```

However, do not blindly create all of these if the existing plugin already has a role/capability system.

First inspect and document the current permissions model.

The new architecture should extend it rather than create conflicting authorization systems.

---

### 12. Businesses

Businesses should be separate from users.

A user can own/manage one or multiple businesses unless the existing product explicitly restricts this.

Suggested conceptual fields:

```text
id
owner_user_id
community_id
business_name
slug
description
category_id
website
phone
email
address
city
region
postcode
country_id
latitude
longitude
status
verification_status
created_at
updated_at
```

Do not store the business's country purely as text.

Use a country reference.

The business's destination country should normally correspond to the community's destination country.

Validate this relationship.

---

### 13. Business Categories

Business categories should also be data-driven.

Do not hard-code categories into templates.

Example:

```text
Accountant
Builder
Plumber
Electrician
Solicitor
Mortgage Adviser
Insurance
Restaurant
Marketing
IT
Transport
Trades
Property
Healthcare
Retail
Professional Services
...
```

Use a category table/taxonomy that can be extended later.

The exact implementation should depend on whether the current plugin uses custom tables, custom post types, or WordPress taxonomies.

---

### 14. Business Location

Separate country from geographic location.

Do not store everything as one string such as:

```text
Poole, Dorset, UK
```

Instead model location as appropriate:

```text
country
region/state
city
postcode
address
latitude
longitude
```

This allows future searches such as:

```text
South African businesses near me
```

or:

```text
South African accountants in London
```

or:

```text
Immigrant-owned businesses in Dorset
```

The exact level of geographic precision should be configurable for privacy.

---

### 15. Business Visibility

Build visibility rules into the data model.

Potential levels:

```text
public
country
community
members_only
private
```

Do not assume every business should be globally visible.

For the initial product, the default should likely be:

```text
community
```

or the destination-country environment, depending on the existing product design.

---

### 16. Platform Hierarchy

Design the plugin so that it can eventually support:

```text
PLATFORM
│
├── United Kingdom
│   ├── South Africans
│   ├── Indians
│   ├── Nigerians
│   └── Poles
│
├── Australia
│   ├── South Africans
│   ├── Indians
│   └── Filipinos
│
├── Canada
│   ├── South Africans
│   ├── Indians
│   └── Nigerians
│
└── Portugal
    ├── Brazilians
    └── South Africans
```

This hierarchy should be achievable without separate plugin codebases.

---

### 17. Administration Hierarchy

Plan for different administrator levels.

Potential hierarchy:

```text
Platform Administrator
    ↓
Country Administrator
    ↓
Community Administrator
    ↓
Moderator
    ↓
Business Member
```

Responsibilities:

### Platform Administrator

Can manage:

- all countries
- all communities
- all users
- all businesses
- global plugin settings

### Country Administrator

Can manage:

- their destination country
- communities in that country
- users/businesses subject to permissions

### Community Administrator

Can manage:

- their community
- community members
- businesses
- community content

### Business Member

Can manage:

- their own profile
- their own business
- permitted networking activity

Do not implement every role immediately unless required.

The architecture should simply avoid making future roles difficult.

---

### 18. Multi-Tenant Consideration

If this plugin may eventually be licensed/sold to organisations, consider a multi-tenant architecture.

Possible future model:

```text
Tenant / Organisation
    ↓
Countries
    ↓
Communities
    ↓
Members
    ↓
Businesses
```

Do not implement a complex multi-tenant system unnecessarily at this stage.

But avoid architecture that makes it impossible later.

Specifically:

- Do not assume there is only one community.
- Do not assume there is only one country.
- Do not use global singleton business records without ownership/context.
- Avoid hard-coded admin menus that assume one network.
- Avoid hard-coded branding/text that assumes South Africans or the UK.

---

### 19. URLs and Routing

URLs should be generated dynamically from slugs.

Good:

```text
/community/south-africans-in-the-uk/
```

or, if country hierarchy is desired:

```text
/uk/south-africans/
```

Potential future structure:

```text
/uk/
/uk/south-africans/
/uk/south-africans/businesses/
/uk/south-africans/events/

/australia/
/australia/south-africans/
/australia/south-africans/businesses/
```

Do not hard-code routes for South Africa or the UK.

Use WordPress rewrite rules, REST routes, shortcodes, blocks, or templates according to the existing architecture.

---

### 20. REST API / AJAX Security

If the plugin has REST API endpoints or AJAX handlers, country/community authorization must also be enforced there.

Do not assume that protecting the frontend is sufficient.

For every endpoint, consider:

```text
Who is requesting this?
What country are they assigned to?
What community are they requesting?
Are they a member?
Do they have the required capability?
Is the requested resource within their permitted scope?
```

Use WordPress nonces where appropriate for authenticated browser requests, but remember:

> A nonce is not an authorization system.

Authorization must still be checked server-side.

---

### 21. Database Design

Before changing the schema:

1. Inspect the existing database structure.
2. Identify whether the plugin uses:
   - custom post types
   - custom database tables
   - user meta
   - options
   - taxonomies
   - a mixture
3. Document current relationships.
4. Identify existing fields that contain country/nationality as free text.
5. Identify duplicate data.
6. Create a migration strategy.

Do not destroy existing production data.

If new tables are required:

- use `$wpdb->prefix`
- use `dbDelta()` where appropriate
- add indexes
- add foreign-key-like integrity at the application layer if WordPress conventions make actual FK constraints unsuitable
- use prepared SQL
- use stable IDs
- avoid querying by display names

---

### 22. Indexing and Scalability

Design for the possibility of thousands or millions of records.

Likely important indexes include:

```text
countries.iso_code
communities.origin_country_id
communities.destination_country_id
communities.slug
community_memberships.user_id
community_memberships.community_id
businesses.owner_user_id
businesses.community_id
businesses.country_id
businesses.status
businesses.category_id
```

If geographic search is later introduced, plan appropriately for location indexes/search infrastructure.

Do not prematurely optimise, but do not create obviously unindexed relationship queries.

---

### 23. Avoid N+1 Queries

When displaying a business directory or community list, do not load country/category/community information individually for every item.

For example, avoid:

```text
business 1 → query country
business 2 → query country
business 3 → query country
...
```

Prefer efficient joins, batching, caching, or WordPress APIs appropriate to the chosen storage model.

---

### 24. Caching

Country lists, categories and relatively static community information are good candidates for caching.

However:

- Never cache permission decisions in a way that could expose another user's data.
- Include country/community/user context in cache keys where required.
- Clear/invalidate caches when relevant records change.

---

### 25. Onboarding Flow

The desired user experience should eventually be something like:

### Step 1

```text
Where are you originally from?

[ Search country... ]
```

### Step 2

```text
Where do you currently live?

[ Search country... ]
```

### Step 3

System determines available communities.

Example:

```text
You are in the United Kingdom.

Communities available to you:

🇿🇦 South Africans in the UK
🇮🇳 Indians in the UK
🇳🇬 Nigerians in the UK
...
```

### Step 4

User joins a community or is automatically assigned according to the product's rules.

The user should not manually choose a destination country that conflicts with their verified/current country without an explicit workflow.

---

### 26. Verification

Country membership may eventually need verification.

Do not build a system that assumes self-declared information is always accurate.

Possible future verification mechanisms include:

- manual community administrator approval
- business verification
- location verification
- identity verification
- email/domain verification
- community invitation

Do not implement invasive verification unless needed.

The architecture should support a verification status.

Example:

```text
country_verified
community_verified
business_verified
```

---

### 27. Cross-Country Access

Initially:

> Users are restricted to their current/destination country.

However, architect the system so that a future explicit feature could allow:

```text
Cross-country communities
International business directory
International referrals
Travel/business connections
Moving-to-another-country resources
```

Do not accidentally create global visibility now.

If cross-country access is introduced later, it should be an explicit permission/feature rather than a side effect.

---

### 28. Privacy

Because this platform deals with people's location and business information:

- Collect only information required for the feature.
- Avoid exposing precise home addresses by default.
- Separate business address from personal address.
- Provide sensible profile visibility settings.
- Do not expose email/phone information unless the member chooses to.
- Ensure REST/AJAX endpoints respect the same privacy rules as page rendering.

If exact location is not required, use city/region/postcode-level information instead.

---

### 29. GDPR / UK Data Protection

The plugin may be used in the UK and potentially other jurisdictions.

Build with privacy compliance in mind.

Consider:

- privacy policy integration
- consent where required
- data export
- data deletion
- account deletion
- retention policies
- lawful basis for processing
- third-party integrations
- cookies/tracking
- business contact information versus personal information

Do not claim legal compliance automatically.

The plugin should provide technical mechanisms that help site operators meet their obligations.

---

### 30. Internationalisation

The plugin must be translation-ready.

Do not hard-code user-facing text.

Use WordPress internationalisation functions such as:

```php
__();
_e();
esc_html__();
esc_html_e();
```

and a proper text domain.

The architecture should support future translations.

Country names should come from data rather than being embedded in translated strings wherever possible.

---

### 31. Avoid Nationality Assumptions

The plugin should not equate:

```text
country of origin
```

with every possible meaning of:

```text
nationality
ethnicity
culture
citizenship
language
heritage
```

For the first version, use a clearly defined concept such as:

> Country/community of origin

Document exactly what the field means.

If later requirements need nationality, citizenship, language or cultural affiliation, model those independently rather than repurposing origin_country.

---

### 32. Recommended Terminology

Use consistent terminology throughout the codebase.

Suggested:

```text
Country
Origin Country
Destination Country
Community
Member
Business
Membership
Business Category
```

Avoid mixing terms such as:

```text
nationality
country
ethnicity
community
location
```

when they mean different things.

---

### 33. Migration Strategy

If the current plugin already contains South Africa/UK-specific data:

### Phase 1 — Audit

Identify:

- hard-coded South Africa references
- hard-coded UK references
- database fields
- user metadata
- business records
- templates
- JavaScript
- REST endpoints
- AJAX
- shortcodes
- admin screens
- emails
- notifications
- URLs
- permissions

### Phase 2 — Introduce Country Model

Create country records.

Migrate existing country strings into country IDs.

### Phase 3 — Introduce Community Model

Create:

```text
South Africa → United Kingdom
```

as the first community.

Associate existing businesses/users with it.

### Phase 4 — Replace Hard-Coded Logic

Replace assumptions with relationships.

### Phase 5 — Add Access Control

Enforce destination-country restrictions server-side.

### Phase 6 — Test Existing Functionality

Existing South African/UK functionality must continue working.

### Phase 7 — Add Second Community

Test with:

```text
South Africa → Australia
```

If this requires code changes specific to Australia, the architecture is not sufficiently generic.

### Phase 8 — Add Different Origin

Test:

```text
India → United Kingdom
```

The same code should support this without another development cycle.

---

### 34. Acceptance Test for Scalability

The coding agent should consider the architecture successful only if the following can be achieved through data/admin configuration rather than code changes.

Create:

```text
South Africa → United Kingdom
South Africa → Australia
India → United Kingdom
India → Canada
Poland → Germany
Brazil → Portugal
```

Then verify:

### User A

```text
Origin: South Africa
Current country: UK
```

Can access UK communities permitted to them.

Cannot access Australian or Canadian community data.

### User B

```text
Origin: South Africa
Current country: Australia
```

Can access Australian communities.

Cannot access UK-only community data.

### User C

```text
Origin: India
Current country: UK
```

Can access Indian/UK community data.

Cannot access India/Canada community data.

The frontend, API and database access paths must all respect these rules.

---

### 35. Admin Experience

The administrator should eventually be able to create a community with something like:

```text
Add Community

Community Name:
[ South Africans in the UK ]

Origin Country:
[ South Africa ]

Destination Country:
[ United Kingdom ]

Description:
[ ... ]

Logo:
[ Upload ]

Status:
[ Active ]
```

No coding should be required to create:

```text
South Africans in Australia
```

or:

```text
Indians in the UK
```

---

### 36. Do Not Overbuild the First Release

The immediate objective is not to build every possible feature.

Prioritise the architectural foundation:

### Priority 1

Countries

### Priority 2

Origin/destination communities

### Priority 3

User current-country association

### Priority 4

Community membership

### Priority 5

Country/community access control

### Priority 6

Business-to-community relationship

### Priority 7

Efficient directory/search

Only after those are robust should the plugin expand into:

- referrals
- messaging
- events
- jobs
- recommendations
- reviews
- advertising
- subscriptions
- payments
- analytics
- international networking

---

### 37. Coding Agent Instructions

Before modifying code:

1. Inspect the complete existing plugin.
2. Identify the plugin entry point.
3. Identify all existing custom tables.
4. Identify all custom post types.
5. Identify all user meta fields.
6. Identify all options/settings.
7. Identify all shortcodes/blocks/widgets.
8. Identify all REST/AJAX endpoints.
9. Identify all permission/capability checks.
10. Identify all country/nationality hard-coding.
11. Identify existing business/member relationships.
12. Produce a concise architecture assessment before making destructive changes.

Do not rewrite the entire plugin merely to implement this specification.

Prefer incremental refactoring.

---

### 38. Code Quality Requirements

Maintain WordPress coding conventions appropriate to the existing project.

Use:

- sanitisation
- escaping
- prepared database statements
- capability checks
- nonce verification where appropriate
- input validation
- output escaping
- secure REST permission callbacks
- proper error handling
- meaningful class/function names
- namespaced or uniquely prefixed functions/classes
- translation-ready strings

Do not introduce global functions/classes with generic names.

---

### 39. Backwards Compatibility

Existing functionality must continue working.

Before migrations:

- back up data
- use versioned database migrations
- make migrations idempotent where practical
- do not assume the plugin is installed on a clean database
- gracefully handle existing installations
- record the database/schema version

Example:

```text
plugin_db_version = 2
```

Future schema changes should be handled through explicit migrations.

---

### 40. Suggested Development Phases

#### Phase A — Architecture Audit

Output:

```text
Current architecture
Current data model
Current dependencies
Hard-coded assumptions
Risks
Recommended changes
```

Do not modify code yet.

---

#### Phase B — Country Abstraction

Implement the reusable country model.

Migrate existing country data.

---

#### Phase C — Community Abstraction

Implement:

```text
origin_country
destination_country
```

and community membership.

---

#### Phase D — Access Control

Implement destination-country isolation.

Test frontend, admin, REST and AJAX access.

---

#### Phase E — Business Association

Associate businesses with communities and countries.

Ensure business visibility follows the relevant permissions.

---

#### Phase F — Search and Directory

Implement efficient filtering by:

```text
country
community
category
city
region
postcode
```

without exposing unauthorised records.

---

#### Phase G — Scalability Testing

Test with multiple origin/destination combinations.

The same codebase must support all combinations.

---

### 41. Important Product Principle

The plugin should be thought of as:

> **A platform for location-specific immigrant business communities**

rather than:

> **A South African business networking plugin.**

South Africans in the UK are simply the **first community**.

The architecture must make it easy to add the next community without changing the underlying code.

---

### 42. Desired End State

The ideal end state is:

```text
One Plugin
    ↓
One Platform
    ↓
Many Destination Countries
    ↓
Many Origin Communities
    ↓
Many Members
    ↓
Many Businesses
    ↓
Local, permission-controlled business networking
```

For example:

```text
GLOBAL PLATFORM
│
├── 🇬🇧 UNITED KINGDOM
│   │
│   ├── 🇿🇦 South Africans
│   ├── 🇮🇳 Indians
│   ├── 🇳🇬 Nigerians
│   ├── 🇵🇱 Poles
│   └── 🇵🇭 Filipinos
│
├── 🇦🇺 AUSTRALIA
│   │
│   ├── 🇿🇦 South Africans
│   ├── 🇮🇳 Indians
│   └── 🇵🇭 Filipinos
│
├── 🇨🇦 CANADA
│   │
│   ├── 🇿🇦 South Africans
│   ├── 🇮🇳 Indians
│   └── 🇳🇬 Nigerians
│
└── 🇵🇹 PORTUGAL
    │
    ├── 🇧🇷 Brazilians
    └── 🇿🇦 South Africans
```

A user sees the communities and businesses relevant to their permitted destination country.

---

### 43. Final Instruction to the Coding Agent

**Do not start by coding.**

First inspect the existing plugin and map its current architecture against this specification.

Then provide:

1. Current architecture summary
2. Current database/data model
3. Existing hard-coded assumptions
4. Proposed target architecture
5. Migration plan
6. Security/access-control plan
7. Risks
8. Files/classes that need modification
9. New files/classes/tables that are required
10. Recommended implementation order

Only then begin implementation.

The primary objective is:

> **Make the existing plugin genuinely data-driven and scalable across any origin country and any destination country, while ensuring users only access the country/network data they are authorised to access.**

Do not compromise the existing working functionality unnecessarily.

