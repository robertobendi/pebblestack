# Pebblestack

A small, no-bloat PHP CMS. WordPress without the marketplace.

**Stack:** PHP 8.2 + SQLite + Twig. One folder. No plugin store, no theme store, no block editor, no multisite. The "no" list is the product.

## Why this exists

WordPress runs on the cheap $3 shared hosts you find on Hostinger, Namecheap, etc. — but it brings 20 years of marketplace-driven complexity with it. Pebblestack is what you'd build today if you only needed the 10 features that 90% of sites actually use.

- **Single-folder install.** Unzip into your webroot, visit `/install.php`, done.
- **One file is the database.** SQLite — no MySQL provisioning, copy the file to back up.
- **Typed content collections.** Define `pages`, `posts`, or whatever you need in `config/collections.php`. No "Custom Post Types UI" plugin.
- **Markdown by default.** No block editor. Write content, render it.
- **Auto-escaped templates.** Twig means you don't ship XSS by accident.
- **Built-in media library.** Upload images and PDFs, get a markdown snippet you can paste into any post.
- **SEO baked in.** `/sitemap.xml` and `/robots.txt` are served automatically.
- **Auto-migrating.** Drop in a new release, refresh the page, and any pending schema migrations run on the next request.

## Requirements

- PHP **8.2+** with `pdo_sqlite`, `json`, `mbstring` extensions (default on every modern shared host)
- Apache with `mod_rewrite`, or LiteSpeed (Hostinger), or nginx with rewrite config
- Composer (only for installing dependencies — once installed, no Composer needed in production)

## Install

1. **Get the code & deps locally:**
   ```bash
   git clone https://github.com/your/pebblestack.git
   cd pebblestack
   composer install --no-dev --optimize-autoloader
   ```

2. **Upload everything to your webroot** (e.g. `public_html/` on Hostinger). Include the `vendor/` folder. Skip `.git`.

3. **Visit `https://yourdomain.com/install.php`** in a browser. Enter site name, your name, email, and a password. The installer creates the SQLite database and your admin user.

4. After install, the admin lives at `/admin`. The public site renders at `/`. Edit `config/collections.php` to change your content shape; the admin UI updates automatically.

## Project layout

```
index.php           # front controller
install.php         # first-run installer entry point
.htaccess           # rewrites + security
src/                # framework + app code (PSR-4: Pebblestack\)
templates/admin/    # admin UI Twig templates
templates/theme/    # public-facing themes (default theme included)
config/             # app.php + collections.php
data/               # SQLite database + migrations (blocked by .htaccess)
uploads/            # user-uploaded media
vendor/             # Composer dependencies (created by composer install)
```

## Field types

Available in `config/collections.php`:

| Type        | Stored as | UI                    |
|-------------|-----------|-----------------------|
| `text`      | string    | single-line input     |
| `textarea`  | string    | multi-line text       |
| `markdown`  | string    | markdown editor (rendered with CommonMark) |
| `slug`      | string    | URL-safe slug input   |
| `boolean`   | bool      | checkbox              |
| `number`    | number    | numeric input         |
| `select`    | string    | dropdown (provide `options`) |
| `datetime`  | string    | datetime picker       |
| `url`       | string    | URL input (validated) |

## Customising the look

Default theme lives in `templates/theme/default/`. Each collection can specify its own template (`template:` key in `config/collections.php`). Twig syntax — see [twig.symfony.com](https://twig.symfony.com).

To make a homepage: create a Page with the slug `home`. It replaces the default landing screen.

## What's NOT in Pebblestack (and never will be)

- Plugin marketplace
- Theme marketplace
- Block / Gutenberg editor
- Multisite
- Comments (use Disqus / Cusdis / Giscus if you need them)
- A WP-style admin bar on the public site

If you need those, use WordPress. Pebblestack stops where WordPress's bloat starts.

## License

MIT — see [LICENSE](LICENSE).
