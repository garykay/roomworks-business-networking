# Business Networking Plugin — Scalability & Multi-Country Architecture Specification

## Purpose

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

# 0. Deployment Architecture — Single Installation, Single Domain

This is an overriding architectural decision that constrains everything below. Read this before any other section.

## Decision

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

## WordPress Multisite must NOT be used

Do not build this on WordPress Multisite. Multisite solves a different problem — many independently administered sites (separate content, plugins, admins) under one WordPress core. This product is the opposite: **one application, many communities**, all sharing the same code, admin system, and (initially) the same database.

Do not introduce Multisite unless a future requirement specifically and explicitly justifies it. It must not be assumed, defaulted to, or introduced "for scalability" — the country/community data model in this spec is how scalability is achieved instead.

## Country-specific domains/TLDs are a presentation concern, not an architectural one

Do not build country detection, access control, or routing logic that depends on the domain or TLD (`.co.uk`, `.com.au`, etc.). The single source of truth for a user's country is the explicit `current_country_id` captured at onboarding (see Section 25), never the domain they arrived on or their IP address.

Countries should be reachable as routes/paths within the one installation, e.g.:

```text
yourdomain.com/uk/
yourdomain.com/uk/south-africans/
yourdomain.com/australia/south-africans/
```

(see Section 19 for full routing guidance). A country-specific domain like `yourbrand.co.uk` may be pointed at this same installation later purely as a marketing/vanity layer that resolves to `/uk/` — that is a routing/DNS decision, not a reason to fork the codebase or database.

## Keep the door open for future regional splits — without building for it now

Do not implement multi-region/multi-server deployment now. But avoid decisions that would make it impossible later, specifically:

- Keep country/community data cleanly partitionable by `destination_country_id` (already required by the data model in this spec) so that, in principle, a future "UK instance" and "US instance" of the same plugin/codebase could each run against their own database if the business ever needs geographically separated infrastructure.
- Do not build features that silently assume global cross-country data access (e.g. a single global search index spanning all countries with no scoping) — see Section 27.
- If/when a genuine need arises to move a user or business between countries, or to browse another country's directory, that must be an explicit, permissioned feature (Section 27), not a side effect of shared infrastructure.
- No global identity/federation layer between installations is required now. Do not build one speculatively. If regional splits are ever pursued, that layer would be designed at that time, informed by real requirements.

## Summary for the coding agent

> Build one WordPress installation, one plugin, one database, one primary domain. Country and community isolation is enforced by the data model and access-control rules in this spec, not by separate sites, separate installs, or separate domains. Do not introduce WordPress Multisite. Do not make the domain/TLD part of any authorization or country-detection logic.

---

# 1. Core Product Concept

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

# 2. Most Important Architectural Principle

## Do NOT hard-code countries or nationalities

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

# 3. Recommended Core Data Model

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

# 4. Countries

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

# 5. Communities

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

# 6. Community Uniqueness

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

# 7. Users / Members

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

# 8. Community Membership

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

# 9. Country-Based Access Control

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

# 10. Security Rule

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

# 11. WordPress Capability Model

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

# 12. Businesses

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

# 13. Business Categories

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

# 14. Business Location

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

# 15. Business Visibility

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

# 16. Platform Hierarchy

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

# 17. Administration Hierarchy

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

# 18. Multi-Tenant Consideration

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

# 19. URLs and Routing

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

# 20. REST API / AJAX Security

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

# 21. Database Design

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

# 22. Indexing and Scalability

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

# 23. Avoid N+1 Queries

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

# 24. Caching

Country lists, categories and relatively static community information are good candidates for caching.

However:

- Never cache permission decisions in a way that could expose another user's data.
- Include country/community/user context in cache keys where required.
- Clear/invalidate caches when relevant records change.

---

# 25. Onboarding Flow

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

# 26. Verification

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

# 27. Cross-Country Access

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

# 28. Privacy

Because this platform deals with people's location and business information:

- Collect only information required for the feature.
- Avoid exposing precise home addresses by default.
- Separate business address from personal address.
- Provide sensible profile visibility settings.
- Do not expose email/phone information unless the member chooses to.
- Ensure REST/AJAX endpoints respect the same privacy rules as page rendering.

If exact location is not required, use city/region/postcode-level information instead.

---

# 29. GDPR / UK Data Protection

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

# 30. Internationalisation

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

# 31. Avoid Nationality Assumptions

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

# 32. Recommended Terminology

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

# 33. Migration Strategy

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

# 34. Acceptance Test for Scalability

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

# 35. Admin Experience

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

# 36. Do Not Overbuild the First Release

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

# 37. Coding Agent Instructions

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

# 38. Code Quality Requirements

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

# 39. Backwards Compatibility

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

# 40. Suggested Development Phases

## Phase A — Architecture Audit

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

## Phase B — Country Abstraction

Implement the reusable country model.

Migrate existing country data.

---

## Phase C — Community Abstraction

Implement:

```text
origin_country
destination_country
```

and community membership.

---

## Phase D — Access Control

Implement destination-country isolation.

Test frontend, admin, REST and AJAX access.

---

## Phase E — Business Association

Associate businesses with communities and countries.

Ensure business visibility follows the relevant permissions.

---

## Phase F — Search and Directory

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

## Phase G — Scalability Testing

Test with multiple origin/destination combinations.

The same codebase must support all combinations.

---

# 41. Important Product Principle

The plugin should be thought of as:

> **A platform for location-specific immigrant business communities**

rather than:

> **A South African business networking plugin.**

South Africans in the UK are simply the **first community**.

The architecture must make it easy to add the next community without changing the underlying code.

---

# 42. Desired End State

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

# 43. Final Instruction to the Coding Agent

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

