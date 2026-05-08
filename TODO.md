# Lexical Lode — Remaining Fixes

These require the `src/` folder (editor JS source files) to be added to the repo from the other computer.

## Needs src/ files

- [ ] **#5 — REST errors silently corrupt block state** — "Regenerate all" writes empty lines on failed responses (nonce expiry, 403, 5xx). Fix: check `response.ok`, throw on error, never overwrite attrs on failed responses.
- [ ] **#8 — setAttributes({mode:'locked'}) during editor render** — React rule violation. Fix: move into `useEffect`, or derive a local `effectiveMode` without persisting back to attributes. Can also remove the mode toggle from the editor UI entirely now that live mode is gone.
- [ ] **#9 — Format-switch silently truncates content** — Switching from free verse (7 lines) to couplets slices to 4 lines, destroying lines 5-7 with no undo. Fix: prompt before truncating, or stash overflow lines until explicit discard.
- [ ] **#14 — Editor useEffect deps closure-stale on auto-fill** — Depends only on `[lineCount]` but closes over `lines`, `postOrder`, `format`, `setAttributes`. Fix: include all referenced attrs in deps, cancel in-flight on relevant changes, use functional updates.

## Other remaining items (no src/ needed)

- [ ] **#20 — Footnotes attribution misnamed** — It's a "Sources list" with no per-line citation. Either rename to "Sources list" or add numbered superscripts.
- [ ] **#21 — Hardcoded white popover bg** — Breaks in dark themes and Windows High Contrast. Fix: use system color tokens (`Canvas`/`CanvasText`) or theme.json CSS custom properties. Check `build/view.css`.
- [ ] **#23 — Empty stanza-break div is semantic noise** — Fix: use CSS margin on every Nth line, or wrap stanzas in `<div role="group" aria-label="Stanza N">`.
- [ ] **#26 — Pool transient cache key includes exclude_post_ids** — Invalidates per-edit. Fix: cache master pool per `(cats, tags)` only, do `array_diff` in PHP per request.
- [ ] **#29 — Pool transient 1-hour stale** — Newly published posts invisible until cache expires. Fix: hook `save_post`/`delete_post` to clear `lexical_lode_id_pool_*` transients.
- [ ] **#30 — Author field drift** — `Author: Zeppo` in main plugin file vs `Contributors: dollissa` in readme.txt. Pick one.
- [ ] **#32 — `load_plugin_textdomain` unnecessary** — Not needed for WP.org-hosted plugins on WP 4.6+. Remove or scope to a `/languages` path.
- [ ] **#33 — Random mode pool capped at 500** — Older posts never appear. Document or add filter `apply_filters('lexical_lode_post_pool_size', 500)`.
- [ ] **#36 — Popover link opens in new tab with no indication** — Fixed in view.js but verify the screen-reader text renders correctly.
- [ ] **#37 — Popover handler closure state flicker** — Fast mouse movement between hover targets can cause flicker. Verify with the rewritten view.js.
- [ ] **#38 — Settings page renders all categories/tags unbounded** — Could lag on sites with thousands. Consider capping at 200 with a notice, or switching to autocomplete.

## Step 1: Get src/ into the repo

1. On the other computer, copy the `src/` folder into the lexical-lode project root
2. Commit and push: `git add src/ && git commit -m "Add editor source files" && git push`
3. On this computer: `git pull`
4. Then fix items #5, #8, #9, #14 and rebuild with `npm run build`
