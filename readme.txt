=== Business Networking ===
Contributors:      The WordPress Contributors
Tags:              business directory, networking, community, member directory, block
Tested up to:      6.8
Requires PHP:      7.4
Stable tag:        0.1.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Frontend-first business networking platform: member accounts, business listings, and a searchable directory, all managed from the public site.

== Description ==

Business Networking turns a WordPress site into a member-only business directory and networking platform. Members handle everything — registration, profile edits, business listings, and account deletion — from the front end. wp-admin is reserved for administrators: approving members, curating the Business Type and Services vocabularies, and reviewing pending business listings.

**Member accounts**

* Front-end registration and login, no wp-admin access required.
* New accounts start as `pending` and require admin approval before they can log in.
* Members can edit their own name, display name, and bio.
* Self-service account deletion with a 24-hour delayed, cancellable window.

**Business listings**

* One business listing per member, enforced by WordPress capabilities and a server-side safety net.
* A single front-end form for name, logo, description, Business Type, Services, location, and contact details.
* All submissions are saved as `pending`; an administrator publishes them.
* Business Type is a curated single-select dropdown with an "Other" option; Services is a multi-select type-ahead field. Both find-or-create matching terms instead of allowing duplicates.
* Optional logo upload (JPG/PNG/GIF/WEBP, up to 5MB) shown on the profile page and directory card.

**Directory**

* Searchable, filterable directory of published businesses (name/description search, Business Type, Service, and location filters).
* Server-rendered first page (works without JavaScript), progressively enhanced into an async experience via REST.
* Restricted to logged-in members, with redirect-and-return around the site's own login page.

**Blocks included**

* Business Directory — the searchable/filterable directory listing.
* Single Business Profile — for a Site Editor "Single Business" template.
* Member Profile — the logged-in member dashboard, or login/registration forms when logged out.
* Roomworks Stats Counter — live counts of members, businesses, and cities covered.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/roomworks-business-networking` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress.
1. Add the desired blocks (Business Directory, Single Business Profile, Member Profile, Roomworks Stats Counter) to the relevant pages/templates via the Block Editor or Site Editor.
1. New member registrations will appear as "Pending" on the Users screen — approve or reject them from there.
1. Curate the Business Type and Services vocabularies from their taxonomy screens, and review pending business listings from the plugin's Approvals screen.

== Frequently Asked Questions ==

= Can members publish their own business listing directly? =

No. Every new or edited listing is saved as `pending`. An administrator must publish it from wp-admin.

= Can a member have more than one business listing? =

No, this is enforced by both WordPress capabilities and a server-side check on save.

= What happens when a member requests account deletion? =

They see a confirmation screen, then the account is scheduled for deletion 24 hours later via WP-Cron, during which they can cancel. The administrator is emailed on both request and cancellation, and the member is emailed a confirmation once deletion completes.

= Are the directory REST endpoints publicly accessible? =

Yes, by design — they only ever return already-public fields of published listings. The directory *page* itself is restricted to logged-in members.

== Screenshots ==

1. Business directory with search and filters.
2. Front-end business listing form.
3. Member profile dashboard.

== Changelog ==

= 0.1.0 =
* Initial release: member accounts with admin approval, business listings with a single-listing-per-member enforcement, Business Type/Services taxonomies with duplicate-avoiding find-or-create REST endpoints, searchable/filterable directory, self-service delayed account deletion, and supporting blocks.
