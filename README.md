# Pebblestack

A small, no-bloat PHP CMS. WordPress without the marketplace.

**Stack:** PHP 8.2 + SQLite + Twig. One folder. No plugin store, no theme store, no block editor, no multisite. The "no" list is the product.

## Install on Hostinger (or any shared PHP host) in 60 seconds

1. **Download** this repo as a ZIP (green "Code → Download ZIP" button on GitHub) or `git clone` it.
2. **Upload** the entire folder contents into `public_html/` on your host (Hostinger, Namecheap, Bluehost — any PHP 8.2+ shared plan). Include the `vendor/` folder. Yes, all of it.
3. **Visit `https://yourdomain.com/install.php`**. Pick a site name, admin email, password.
4. You're done. Public site is at `/`. Admin is at `/admin`.

No Composer to run. No MySQL to provision. No Docker. No Node.js. The repo is drop-in deployable as-is.

## Why this exists

WordPress runs on every cheap shared host on the planet, but it brings 20 years of marketplace-driven complexity with it. Pebblestack is what you'd build today if you only needed the 10 things 90% of sites actually use.

- **Single-folder install.** Unzip into your webroot. That's the install.
- **One file is the database.** SQLite — back up by copying `data/pebblestack.sqlite`.
- **Typed content collections.** Define `pages`, `posts`, or anything else in `config/collections.php`. No "Custom Post Types UI" plugin.
- **Markdown by default.** No block editor. Write content, render it.
- **Auto-escaped templates.** Twig means you don't ship XSS by accident.
- **Built-in media library** with MIME-sniffed uploads + alt text + markdown snippets.
- **Roles + multi-user** — admin/editor/viewer with sane gating.
- **Forms** — mark a collection `is_form: true` and it accepts public submissions at `POST /forms/{name}`.
- **Revisions + restore** — every save snapshots the prior version.
- **Privacy-friendly metrics** — server-side page views, no JS pixel, no cookies, no IPs.
- **SEO baked in** — `/sitemap.xml` and `/robots.txt` work out of the box.
- **Auto-migrating** — drop in a new release, refresh the page, the DB updates itself.

## Building a website with Pebblestack + an AI assistant

The intended workflow:

1. Clone Pebblestack into your project directory.
2. Tell your AI assistant: *"Build the frontend for {site type} using Pebblestack as the base."*
3. The AI edits **`templates/theme/default/`** (the theme) and **`config/collections.php`** (the content shape). Everything else stays untouched.
4. Upload the whole thing to `public_html/`. Visit `/install.php`. Done.

See [`AGENTS.md`](AGENTS.md) — it tells the AI exactly which files to touch, which to leave alone, and how the system fits together.

## Project layout

```
index.php           # front controller
install.php         # first-run installer entry
.htaccess           # rewrites + security
config/             # app.php + collections.php — edit these
templates/admin/    # admin UI Twig templates — leave these
templates/theme/    # public-facing themes — edit these
src/                # framework — leave this
data/               # SQLite + migrations (blocked from web)
uploads/            # media (PHP execution disabled here)
vendor/             # Composer deps (shipped in repo for shared hosting)
```

## Field types available in `config/collections.php`

| Type        | UI                       |
|-------------|--------------------------|
| `text`      | single-line input        |
| `textarea`  | multi-line text          |
| `markdown`  | markdown editor (CommonMark) |
| `slug`      | URL-safe slug input      |
| `boolean`   | checkbox                 |
| `number`    | numeric input            |
| `select`    | dropdown (provide `options`) |
| `datetime`  | datetime picker          |
| `url`       | URL input (validated)    |

## What's NOT in Pebblestack (and never will be)

- Plugin marketplace
- Theme marketplace
- Block / Gutenberg editor
- Multisite
- Comments (use Disqus / Cusdis / Giscus)
- A WP-style admin bar on the public site

If you need those, use WordPress. Pebblestack stops where WordPress's bloat starts.

## License

MIT — see [LICENSE](LICENSE).
