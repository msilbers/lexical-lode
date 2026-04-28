=== Lexical Lode ===
Contributors: zeppo
Tags: poetry, generative, found poetry, creative writing, block editor
Requires at least: 6.7
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Mine your site's blog posts for found poetry.

== Description ==

Lexical Lode is a plugin that adds a Gutenberg block to pull short phrases from published posts on your site, then assembles them into poetry or structured text. Each phrase is from a different post. Choose a format and scramble lines until you like the result. Exclude posts via tag or category. Mine your site's backlog for found poetry.

**Formats:**

* Sonnet — 14 lines
* Free Verse — choose your own line count
* Couplets — paired lines with stanza breaks
* Prose Paragraph — phrases stitched into continuous text
* List / Aphorisms — numbered list of observations

**Features:**

* Scramble button on each line to re-roll phrases
* Attribution options: hidden, on hover, or footnotes
* Exclude posts by category or tag in plugin settings
* Format picker is configurable — enable only the formats you want

== Installation ==

1. Upload the `lexical-lode` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins menu.
3. Go to Settings > Lexical Lode to configure formats and exclusions.
4. Add the Lexical Lode block to any post or page.

== REST API Endpoints ==

Lexical Lode registers REST API endpoints. Site administrators may want to apply server-level rate limiting.

= POST /wp-json/lexical-lode/v1/generate =

Generates a set of lines from published posts. Requires `edit_posts` capability (authenticated editors/admins only).

**Parameters:**
* `line_count` (integer, required) — number of lines to generate (1-50)
* `order` (string, optional) — `random`, `newest`, or `oldest`
* `exclude_post_ids` (array of integers, optional) — post IDs to exclude

= POST /wp-json/lexical-lode/v1/scramble =

Returns a new random phrase from a specific post.

**Parameters:**
* `post_id` (integer, required) — the post to pull a new phrase from

**Rate limiting recommendation:** If you want to protect plugin endpoints from abuse, add a rate limit at the server level. Examples:

For Nginx:
`limit_req_zone $binary_remote_addr zone=lexical-lode:10m rate=10r/s;`

Then in your location block:
`location /wp-json/lexical-lode/v1/ { limit_req zone=lexical-lode burst=5; }`

For Apache with mod_ratelimit:
`<Location "/wp-json/lexical-lode/v1/">`
`SetOutputFilter RATE_LIMIT`
`SetEnv rate-limit 400`
`</Location>`

Most managed WordPress hosts and CDNs (Cloudflare, WP Engine, etc.) also offer rate limiting that can be applied to specific URL paths.

== Changelog ==

= 1.0.0 =
* Initial release.
