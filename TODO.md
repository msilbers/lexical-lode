# Lexical Lode — Remaining Fixes

## Completed

- [x] **#5 — REST errors silently corrupt block state** — apiFetch now checks response.ok, throws on error.
- [x] **#8 — setAttributes({mode:'locked'}) during editor render** — mode attribute and live mode toggle removed entirely.
- [x] **#9 — Format-switch silently truncates content** — Confirm dialog before truncating more than 1 line.
- [x] **#14 — Editor useEffect deps closure-stale** — All deps included, functional attribute access in timeout.
- [x] **#11 — Double-click scramble race** — Per-line scrambling Set prevents concurrent requests.
- [x] **src/ files added to repo** — Source files from lexicon-lode renamed and committed.
- [x] **All buttons have type="button"** — Prevents accidental form submission.

## Remaining items

- [ ] **#20 — Footnotes attribution misnamed** — It's a "Sources list" with no per-line citation. Either rename to "Sources list" or add numbered superscripts.
- [ ] **#21 — Hardcoded white popover bg** — Breaks in dark themes and Windows High Contrast. Fix: use system color tokens (`Canvas`/`CanvasText`) or theme.json CSS custom properties.
- [ ] **#23 — Empty stanza-break div is semantic noise** — Fix: use CSS margin on every Nth line, or wrap stanzas in `<div role="group" aria-label="Stanza N">`.
- [ ] **#26 — Pool transient cache key includes exclude_post_ids** — Invalidates per-edit. Fix: cache master pool per `(cats, tags)` only, do `array_diff` in PHP per request.
- [ ] **#29 — Pool transient 1-hour stale** — Newly published posts invisible until cache expires. Fix: hook `save_post`/`delete_post` to clear `lexical_lode_id_pool_*` transients.
- [ ] **#30 — Author field drift** — `Author: Zeppo` in main plugin file vs `Contributors: dollissa` in readme.txt. Pick one.
- [ ] **#32 — `load_plugin_textdomain` unnecessary** — Not needed for WP.org-hosted plugins on WP 4.6+. Remove or scope to a `/languages` path.
- [ ] **#33 — Random mode pool capped at 500** — Older posts never appear. Document or add filter `apply_filters('lexical_lode_post_pool_size', 500)`.
- [ ] **#36 — Popover link opens in new tab with no indication** — Fixed in view.js, verify screen-reader text renders correctly.
- [ ] **#37 — Popover handler closure state flicker** — Verify with rewritten view.js.
- [ ] **#38 — Settings page renders all categories/tags unbounded** — Could lag on sites with thousands. Consider capping at 200 with a notice.
