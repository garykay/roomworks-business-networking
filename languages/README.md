# Region/language files

This plugin runs as a single multi-country deployment (one site, members from
many countries — see the Communities/Countries features), not one deployment
per market. Address-field wording that differs by country — "Postcode" in the
UK is "ZIP Code" in the US and "Eircode" in Ireland, "County / Region" is
"State" in the US — is therefore resolved **per member**, from their own
"Country You Currently Live In" profile field, not per site.

That logic lives in code, not in these `.po`/`.mo` files:
`RBN_Countries::postcode_label( $country_id )` and
`RBN_Countries::county_region_label( $country_id )` in
`includes/class-rbn-countries.php`, called from the business/request forms and
directory filters in `includes/class-rbn-templates.php`. A member with no
country set (or logged out) gets the UK default wording. To add another
country's term, add a `case` to the `switch` in each method.

**Do not add per-region overrides for these terms to the `.po` files below** —
WordPress's `.mo` translation is applied to every string with that exact
English text regardless of which country's branch produced it, so an
`en_US` override of "Postcode" → "ZIP Code" would relabel a UK member's field
too if Site Language were ever set to `en_US`. That's exactly the bug this
split fixes.

## What the `.po`/`.mo` files here are still for

This is WordPress's standard translation system: every user-facing string is
wrapped in `__()` / `esc_html_e()` etc. under the `roomworks-business-networking`
text domain, and `roomworks-business-networking.php` calls
`load_plugin_textdomain()` pointing at this folder. Use it if a deployment
ever needs a genuinely different **language** (not just regional English
terminology) — Settings → General → Site Language loads the matching locale's
`.mo` automatically.

- `en_US`, `en_AU`, `en_IE`, `en_CA`, `en_NZ`, `en_ZA`, `en_IN` exist as
  starting skeletons (one per English-speaking market) but should otherwise
  be left blank — every string falls back to the source (UK) English text,
  which is correct for a single-language English deployment.
- `fr_FR`, `es_ES`, `de_DE`, `it_IT` have real, fully-translated `msgstr`
  entries for every string — AI-drafted as a starting point and **not yet
  reviewed by a native speaker**. Have a native speaker check each one before
  setting a live site's Site Language to any of these locales.
- If a future deployment needs another language, that same `.pot` file is
  the starting point — every string needs a real `msgstr`, and it should be
  reviewed by a native speaker rather than machine-translated.

## Regenerating after code changes

If new translatable strings are added to the plugin (or existing ones are
reworded), regenerate the `.pot` from the plugin root:

```
wp i18n make-pot . languages/roomworks-business-networking.pot --domain=roomworks-business-networking --exclude=node_modules,build
```

Then re-run `wp i18n update-po` for each existing `.po` file to merge new
strings in, and re-compile with `wp i18n make-mo . .` (run from this
`languages/` folder).
