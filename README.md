# Lumo SEO

A lightweight but powerful WordPress SEO plugin — on-page analysis, meta management, Open Graph / Twitter Cards, AI JSON import, and more.

## Features

- **SEO analysis** — keyphrase in title, description, intro, subheadings, slug, density, image alts, internal/outbound links, content length
- **Readability analysis** — Flesch Reading Ease, passive voice, transition words, sentence/paragraph length
- **Priority scoring** — checks weighted HIGH / MEDIUM / LOW; color-coded score donut
- **Google snippet preview** — live title + description preview as you type
- **Open Graph tab** — og:title, og:description, og:image, og:url, og:type, og:site_name, og:locale with live Facebook card preview
- **Twitter / X tab** — twitter:card, twitter:title, twitter:description, twitter:image with live card preview
- **Media library picker** — select or upload images for OG / Twitter directly from WordPress media library
- **AI JSON import** — paste or drop a `.json` file to fill all SEO fields in one click; two-step Validate → Apply flow
- **Copy report for GPT** — exports analysis as a structured prompt; checks tagged `[META]` (JSON-fixable) vs `[CONTENT]` (requires editing)
- **Gutenberg sidebar** — PluginSidebar with all fields + import inside the block editor
- **Elementor panel** — floating FAB + slide-in panel for Elementor editor
- **Front-end output** — full OG + Twitter meta tags, JSON-LD schema (Article / WebPage), canonical URL
- **XML sitemap** — auto-generated at `/sitemap.xml`, excludes noindex posts
- **Admin settings** — title separator, site name, default OG image, noindex archives, Google verification

## Installation

1. Download the latest release `.zip`
2. In WordPress admin go to **Plugins → Add New → Upload Plugin**
3. Upload the zip and click **Install Now → Activate**

## JSON Import Format

Paste AI-generated JSON to fill all fields at once. Full field reference:

```json
{
  "focus_keyword": "your main keyword",
  "meta_title": "Page Title With Keyword — Site Name (30–60 chars)",
  "meta_description": "120–158 character description with your focus keyword.",
  "og_title": "Social share title",
  "og_description": "Short social description.",
  "og_image": "https://yoursite.com/images/social.jpg",
  "og_url": "https://yoursite.com/page-slug/",
  "og_type": "article",
  "og_site_name": "Your Brand",
  "og_locale": "en_US",
  "twitter_card": "summary_large_image",
  "twitter_title": "Twitter-specific title (optional)",
  "twitter_description": "Twitter-specific description (optional).",
  "twitter_image": "https://yoursite.com/images/twitter.jpg",
  "canonical": "https://yoursite.com/canonical-url/",
  "noindex": false,
  "related_keywords": ["variant 1", "variant 2"],
  "suggested_headings": ["H2 idea 1", "H2 idea 2"],
  "content_notes": "Advisory notes for the writer — not saved to meta."
}
```

## Requirements

- WordPress 6.0+
- PHP 8.0+

## Changelog

See [CHANGELOG.md](CHANGELOG.md)

## Author

**Orkhan Hasanov**

## License

GPL-2.0+
