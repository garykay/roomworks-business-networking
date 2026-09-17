# Region/language files

This plugin ships to several servers (UK, USA, Australia, Ireland/EU) that each
need slightly different wording for the same English fields — e.g. "Postcode"
in the UK is "ZIP Code" in the US and "Eircode" in Ireland. Rather than a
custom settings screen, this reuses WordPress's own translation system:
every user-facing string in the plugin is already wrapped in `__()` /
`esc_html_e()` etc. under the `roomworks-business-networking` text domain, and
`roomworks-business-networking.php` calls `load_plugin_textdomain()` pointing
at this folder.

**Each server just needs its own Site Language set** (Settings → General →
Site Language) to the matching locale below. WordPress then automatically
loads that locale's `.mo` file from this folder — no code changes, no per-site
plugin config.

## Locales included

| Locale  | Server / market  | What's different from the UK source        |
|---------|-------------------|----------------------------------------------|
| (none)  | UK (source text)  | "Postcode", "County / Region" — no `.mo` needed, this is the text already in the code |
| `en_US` | USA               | "Postcode" → "ZIP Code", "County / Region" → "State" |
| `en_AU` | Australia         | "County / Region" → "State / Territory" (Postcode stays the same) |
| `en_IE` | Ireland / EU      | "Postcode" → "Eircode" |

Only the handful of strings that actually differ per region are translated in
each `.po` file — every other string is left blank, which means it falls back
to the source (UK) English text automatically. This is deliberate: it keeps
each region file small and makes future string changes in the plugin apply
everywhere by default, without needing to update every locale file.

## Adding another region

1. Copy the template: `cp roomworks-business-networking.pot roomworks-business-networking-en_XX.po`
   (use the real WordPress locale code — see https://make.wordpress.org/polyglots/teams/ —
   e.g. `en_CA` for Canada, `en_NZ` for New Zealand, `fr_FR` for a French translation).
2. Edit the `msgstr ""` line under whichever `msgid` needs to change for that
   region (the current set to check is "Postcode", "County / Region", "Town /
   City", and "Town, county or postcode" — search the source for
   `rbn_postcode`/`rbn_county_region` if new location fields are ever added).
   Leave every other `msgstr` blank.
3. Compile it: `wp i18n make-mo . .` (run from this `languages/` folder) —
   or use a tool like Poedit, which compiles `.mo` automatically on save.
4. Set that server's Site Language to the matching locale.

## Regenerating the template after code changes

If new translatable strings are added to the plugin (or existing ones are
reworded), regenerate the `.pot` from the plugin root:

```
wp i18n make-pot . languages/roomworks-business-networking.pot --domain=roomworks-business-networking --exclude=node_modules,build
```

Then re-run `wp i18n update-po` for each existing `.po` file if you want new
strings merged in (they'll just default to the fallback until translated),
and re-compile with `wp i18n make-mo . .`.

## Full non-English translations

If a future deployment needs a genuinely different language (not just
regional English terminology), the same `.pot` file is the starting point —
every string in the plugin needs a real `msgstr` in that case, and should be
reviewed by a native speaker rather than machine-translated.
