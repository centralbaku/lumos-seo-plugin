# Changelog — Lumos SEO

All notable changes to this project are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.7.0] — 2026-04-28

### Fixed
- **`lumosSEO is not defined`** — `wp_localize_script` in `class-meta-box.php` and `class-elementor.php` was still passing the JS object under the old name `lumoSEO` after the Lumo → Lumos rename. Corrected to `lumosSEO` to match the JS variable name.
- **Old SEO data not visible** — renaming meta keys from `_lumo_*` to `_lumos_*` left existing post data inaccessible. A one-time migration now runs on activation and on the first `init` after upgrade, copying all `_lumo_*` values to `_lumos_*` keys (only where no new key already exists, so no data is overwritten).

---

## [1.6.0] — 2026-04-28

### Added
- **Auto-updater** (`class-updater.php`) — WordPress now detects new versions automatically via the GitHub Releases API. When a new release is tagged on GitHub, the standard WordPress "Update available" notice appears in wp-admin. One-click update downloads and installs the new zip, with the extracted folder renamed to match the installed plugin folder.

---

## [1.5.0] — 2026-04-28

### Fixed
- **Modal scroll UX** — modal now uses a sticky header + sticky footer (Cancel / Apply buttons always visible); only the body area scrolls. Background page no longer scrolls while modal is open (body scroll-lock).
- **Advisory text overflow** — long `content_notes` / suggested headings sections are capped at a scrollable height instead of pushing buttons off-screen.

### Improved
- **"Copy report for GPT" prompt rewritten** — every check is now tagged `[META]` (fixable in JSON) or `[CONTENT]` (requires editing the page body). The GPT prompt explicitly instructs the model to fix only `[META]` items in the JSON and describe `[CONTENT]` fixes inside `content_notes`.
- **Fix-summary badge row** added below the score — shows "✦ N fixable via JSON import" and "✎ N require content edits" at a glance so users understand why re-importing JSON alone won't raise all scores.

---

## [1.4.0] — 2026-04-23

### Added
- **Media library picker** on all image URL fields (og:image, twitter:image). A "Select image" button opens the WordPress media library — users can upload a new image or pick from existing media. Selected image fills the URL field and shows a thumbnail preview. A "✕" remove button clears the field.

---

## [1.3.0] — 2026-04-23

### Added
- **Copy report for GPT** button appears after SEO analysis. Copies a structured plain-text report (score, all checks with priority labels) plus a prompt asking GPT to return a corrected JSON. User can paste the JSON back via Import JSON.
- **File picker in Import JSON modal** — users can now drop a `.json` file onto the drop zone or click "browse" to select one from disk. File contents are loaded into the textarea automatically; existing paste flow is unchanged.

---

## [1.2.0] — 2026-04-22

### Added
- **Import JSON modal** — paste or drop AI-generated JSON to fill all SEO fields in one click.
  - Two-step flow: Validate & Preview → Apply Import.
  - Advisory sections (suggested headings, related keywords, content notes) displayed in modal but not saved to meta.
  - Flash highlight animation on imported fields.
  - "Copy example JSON" button copies the full template to clipboard.
- **Open Graph tab** — all seven OG fields (og:title, og:description, og:image, og:url, og:type, og:site_name, og:locale) with live Facebook-style card preview.
- **Twitter / X tab** — twitter:card select, twitter:title, twitter:description, twitter:image with live Twitter card preview.
- **Advanced tab** — canonical URL field, noindex checkbox.
- Live OG and Twitter card previews update as fields are typed.

### Changed
- Meta box now shows in **both classic and block (Gutenberg) editors** — removed `is_block_editor()` guard.
- Meta box redesigned with tabbed layout (SEO / Open Graph / Twitter / Advanced).
- Import JSON banner added at the top of the meta box for discoverability.

---

## [1.1.0] — 2026-04-21

### Added
- **Gutenberg sidebar panel** (`gutenberg.js`) — PluginSidebar with SEO analysis, readability, social fields, and Import JSON inside the block editor.
- **Elementor floating panel** (`elementor.js`) — FAB button + slide-in panel with 5 tabs (SEO, Readability, Preview, Social, Advanced) for Elementor editor.
- **Priority framework** — every SEO/readability check carries a priority (HIGH / MEDIUM / LOW); weighted scoring (HIGH×3, MEDIUM×2, LOW×1).
- **Checks grouped** into Problems / Improvements / Good results with collapsible `<details>` sections.
- **Full OG + Twitter meta output** on the front end (`class-front-end.php`) with smart fallback chains (twitter → og → seo → post default).
- **JSON-LD schema** (Article / WebPage) output on the front end.
- `ajax_import_json` AJAX handler for bulk JSON import with server-side validation and length warnings.
- `ajax_save_meta` AJAX handler for single-field saves (used by Elementor live edits).

### Changed
- `class-analyzer.php` — `analyze()` now accepts live field values so analysis runs without saving the post first.
- Score calculation updated to use priority-weighted algorithm.

---

## [1.0.0] — 2026-04-20

### Added
- Initial release.
- On-page SEO analysis: keyphrase in title, meta description, introduction, subheadings, URL slug, image alt, density, internal/external links, content length, previously used keyword check.
- Readability analysis: Flesch Reading Ease, paragraph length, subheading distribution, passive voice %, transition words, sentence length, consecutive sentences.
- Google snippet preview with live title/description update.
- Character counters with color-coded progress bars for title (30–60) and description (120–158).
- Classic editor meta box with SEO title, meta description, focus keyword.
- XML sitemap at `/sitemap.xml` (auto-excludes noindex posts).
- Admin settings page: title separator, site name, OG image, noindex archives, Google verification tag.
- SEO score column in post/page list screen.
