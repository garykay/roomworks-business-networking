# Business Networking Plugin — Scalability Task List

Companion to `business-networking-plugin-scalability-spec.md`. That document is the spec; this is the ordered, checkable work breakdown against the plugin as it actually exists today. Phases match spec Section 40.

Work top to bottom — each phase depends on the one before it. Tick items off as they land; re-read the relevant spec section before starting a phase.

---

## Status as of 2026-09-18 (end of session)

**Done: Phases A–D, Phase E's main item, and a Phase C addendum.** Countries, communities, community membership, directory access control (community + current-country match), unlimited businesses per member, and auto-created communities (so a member is never blocked by an admin not having set up their specific country pair yet) are all built and verified live against the dev site (not just code review) — see each phase's section below for specifics and the real bugs that were caught along the way.

**Not started: Phase E's remaining items (business/community country-match validation note, business visibility levels), Phase F's DB-index review and `LIKE`-search revisit, and a single consolidated Phase G test run** (individual pairs have already been spot-checked live throughout, per Phase G's notes below).

**Test data on the dev site**, if picking this up later: 8 test accounts (password `TestPass123!`) spanning 6 origin countries and 5 destination countries, each with a business — see Phase B's section for the full list. 8 communities exist covering every origin/destination pair among them. The admin account owns two businesses ("Gary Kay Plumbing", "Roomworks Media") used to test multi-business support.

**Worth knowing before touching directory visibility again:** it went through two live-testing-driven corrections (see Phase D) before landing on "visible only if the viewer has joined the community AND currently lives in its destination country" — both simpler one-factor versions were tried and rejected. Don't re-simplify this without re-reading why.

---

## Phase A — Architecture Audit (done)

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

## Decisions (resolved)

1. **One business per member → no cap at all.** ~~Relaxed to one business per community membership~~ (superseded — see Phase E: the user directly confirmed they personally own two unrelated businesses and want both usable, with no natural cap tied to community count). A member may own any number of businesses; each is independently assigned to one of the member's joined communities, including more than one business in the same community.
2. **Storage model: custom `$wpdb` tables** for `countries`, `communities`, and `community_memberships` — not taxonomies. `community_memberships` needs relational fields (`status`, `role`, `approved_at`) and fast per-user/per-community lookups on every access-control check; taxonomies aren't a good fit for that. This follows the same pattern already used for `rbn_follows`/`rbn_member_needs` in `class-rbn-schema.php`.
3. **All existing data is dummy** except one admin account. No careful zero-downtime migration is needed — Phase C can do a clean activation-time seed of the first community rather than a production data migration.
4. **Community uniqueness is admin-configurable, not a hard DB constraint.** A setting (reusing the `class-rbn-settings.php` pattern) toggles whether a new community's `(origin_country_id, destination_country_id)` pair must be unique. Enforced in application code at community-creation time, not a database `UNIQUE` constraint — so flipping the setting later never requires a migration or risks orphaning data.

---

## Phase B — Country Abstraction (done)

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

## Phase C — Community Abstraction (done)

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

## Phase D — Access Control (done)

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

## Phase E — Business Association (mostly done)

- [x] `community_id` on `rbn_business` — done as part of Phase D above (needed to have anything to scope by)
- [x] **Multiple businesses per member, uncapped.** Prompted by the user directly ("I own 2 businesses... I would like both to show in the communities I belong to"), which superseded the original Decision 1 (see above). Rebuilt:
  - `class-rbn-business-repository.php`: `get_for_user()`/`user_has_business()`/`enforce_single_business()` removed entirely (including its `save_post` hook in the main plugin file) and replaced with `get_all_for_user()` (every business a member owns) and `get_by_id_for_user()` (one specific business, only if actually owned by that user - never trust a bare ID from a request).
  - `class-rbn-business-forms.php`: the save handler now targets a business by a submitted `rbn_business_id` (0 = create new), re-verified server-side via `get_by_id_for_user()` before anything is read or written - **verified live** that a different member cannot edit someone else's business by tampering with this ID, even with a otherwise-valid nonce (correctly rejected with `business_not_permitted`).
  - `class-rbn-templates.php`: the dashboard's single business form became a **"My Businesses" list** (name, status, assigned community, Edit link) sitting above an add/edit form that's blank by default or pre-filled when reached via that Edit link (`?rbn_edit_business={id}`, a read-only GET toggle stripped from the hidden redirect target the same way `?rbn_confirm_deletion=1` already was - no new pattern invented).
  - **Verified live end-to-end**: added a second real business ("Roomworks Media") to the admin's account alongside the existing "Gary Kay Plumbing", assigned to a different joined community, approved it, and confirmed both appear correctly in the directory (each governed independently by the Phase D community+country visibility rule) and both list correctly under "My Businesses" with working Edit links.
- [ ] Validate a business's country always matches its community's destination country (spec Section 12) — **architectural note:** rather than storing a separate, redundant `country_id` on the business and validating it stays in sync with the community's own destination country, the business's country is derived from `community_id` (via `RBN_Communities::get_by_id($community_id)->destination_country_id`) wherever needed. This makes the two impossible to drift apart by construction, rather than needing an ongoing validation check - a deliberate simplification, not an oversight.
- [ ] Business visibility levels (spec Section 15) — implement only if the product needs it for launch, otherwise defer

## Phase F — Search and Directory

- [x] Scope `class-rbn-business-query.php` filters by community/country server-side — never trust a client-supplied `community_id` alone. **Done as part of Phase D** (`community_scope_clause()`) — never ended up a separate pass, since Phase D's access-control work and this item turned out to be the same piece of code.
- [ ] Add DB indexes for the new foreign keys (`communities.origin_country_id`, `communities.destination_country_id`, `community_memberships.user_id`, `community_memberships.community_id` — all already indexed via `class-rbn-schema.php`'s `KEY` definitions, worth double-checking under real data volume). A business's community (`rbn_community_id`) is `wp_postmeta`, not a dedicated column, so "indexing" that one means confirming `wp_postmeta`'s existing `meta_key`/`meta_value` index is adequate at scale, not adding a new column index.
- [ ] Revisit the `LIKE`-based location search once real multi-country data volume exists

## Phase C addendum — Auto-Created Communities (done, added after initial Phase C ship)

Raised by the user after testing a country with no communities yet (South Africa → Denmark): a real member should never hit a dead end just because no admin has manually created their specific origin/destination pairing. Manual admin creation (the original Phase C screen) still exists for deliberate curation, but is no longer the *only* way a community comes into being.

- [x] `RBN_Communities::get_for_pair()` — finds an existing community for a pair regardless of the uniqueness setting (always "is there already one", not "is duplication currently allowed")
- [x] `RBN_Communities::get_or_create_for_pair()` — returns the existing community for a pair, or creates one (status active) if none exists. Never creates a duplicate no matter how `require_unique_community_pair` is configured, since it always checks `get_for_pair()` first.
- [x] Wired into both places a member's origin/current country is saved: `RBN_Auth_Forms::handle_register()` and `RBN_Profile_Forms::handle_request()` — registering or updating your profile with a new country combination auto-creates the matching community if needed.
- [x] Auto-created, not auto-joined — the community exists and appears in "Available Communities" immediately, but joining it is still a deliberate click, consistent with every other community.
- **Naming limitation, called out to the user rather than silently shipped:** auto-created communities are named "{Origin} in {Destination}" (e.g. "South Africa in Denmark"), not a proper demonym ("South Africans in Denmark") — pluralising nationalities correctly (Poland → Poles, not "Polands") isn't something that can be generated reliably. **Follow-up, same session:** the admin Communities screen now supports renaming (see below), so this is no longer a dead end.

**Verified live:**
- Registered a real account (South Africa → Denmark, a country with zero prior communities) → "South Africa in Denmark" auto-created, active, 0 members, correctly excluded from the new member's own community count (they weren't auto-joined).
- Registered a second account with the identical pair → confirmed no duplicate was created; the second account correctly resolved to the same existing community.

### Follow-up: Edit/rename communities in wp-admin

- [x] `RBN_Communities::update()` — updates name/description/status only. Origin/destination country are intentionally **not** editable once a community exists: members and businesses are already tied to it by ID, and silently changing which country pair it represents would move all of them without anyone choosing that.
- [x] Renaming deliberately never touches the slug — it's already used to route to the community (spec Section 19), and changing it to match a new name would break any existing link.
- [x] `class-rbn-communities-admin.php`: the "Add Community" form and list now double as an edit view — each row got an **Edit** button (`?rbn_edit_community={id}`, a read-only GET toggle re-verified server-side, same pattern as `?rbn_edit_business={id}` on the front end) that pre-fills the form for that one community, with origin/destination shown as read-only text and a note explaining why.
- **Verified live**: renamed the auto-created "South Africa in Denmark" to "South Africans in Denmark" — slug stayed `south-africa-in-denmark`, unchanged. Also verified the empty-name validation still rejects a crafted request with a blank name (bypassing the HTML5 `required` attribute via a raw POST), and that the rejected attempt left the community's name untouched rather than partially saving.

## Phase G — Scalability Testing

- [x] Add a second community end-to-end: South Africa → Australia — must require zero code changes, only admin/data entry. **Already done and verified during Phase C** — created via the live "Communities" admin form (not a script) alongside 6 other origin/destination pairs, no code touched.
- [x] Add a third community with a different origin: India → United Kingdom — same requirement. **Also already done during Phase C**, same batch of 7 communities created live.
- [ ] Re-run the Section 34 acceptance test across all three (now eight) communities in one pass, through frontend, REST and DB access paths. Individual pairs have each been spot-checked live during Phases C/D/E (see those sections above), but a single consolidated run across every community/test account together hasn't been done as one exercise.
