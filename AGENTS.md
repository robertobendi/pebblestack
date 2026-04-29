# AGENTS.md

Guidance for AI assistants helping a user build a website on top of Pebblestack.

## What Pebblestack is

A self-contained PHP 8.2 + SQLite + Twig CMS. The user has dropped this repo into their project as a runtime base. Their goal is **a complete website**: content shape + visual design + working public site that they can upload to a cheap shared host.

You are not building Pebblestack. You are building **a website that runs on Pebblestack**.

## What you should edit

| File / dir | What to do |
|---|---|
| `config/collections.php` | Define the content shape: pages, posts, custom collections, forms. **Edit freely.** |
| `config/app.php` | App-level config including which theme is active. **Edit freely.** |
| `templates/theme/default/` | The visual design — Twig templates and the inline CSS in `layout.twig`. **This is the frontend.** Edit freely, or copy to a new theme dir and switch `app.theme`. |
| `uploads/.gitkeep` | Add starter image assets here if the design needs them. |

## What you should NOT edit

| File / dir | Why |
|---|---|
| `src/` | Framework code (router, auth, models, controllers). Touching this couples the user's site to a fork they'll have to maintain forever. If you think you need a feature here, add it as Twig logic in the theme or as a new collection in `config/collections.php` instead. |
| `templates/admin/` | Admin UI. The user never asked for a custom admin. Don't reskin it. |
| `vendor/` | Composer deps. Never edit by hand. |
| `data/migrations/` | Schema migrations are versioned and additive. Don't rewrite existing ones. If you genuinely need a new table for a feature, ask the user first — usually a JSON field on an existing collection is enough. |
| `index.php`, `install.php`, `.htaccess` | Deployment plumbing. Stable. |

## How the pieces fit together

1. **Collections** define content types (e.g. `pages`, `posts`, `case_studies`, `team_members`). Each collection has typed fields. Adding a collection automatically adds it to the admin sidebar and gives it CRUD pages.
2. **Forms** are collections marked `is_form: true`. They accept public submissions at `POST /forms/{name}` instead of being editable as content.
3. **The public site** is rendered by Twig templates in the active theme. The router maps:
   - `/` → `home.twig` (or a Page with slug `home` if it exists)
   - Each non-form collection's `route` config (e.g. `/blog/{slug}`, `/projects/{slug}`, `/{slug}`) → its `template`
   - The path prefix of any collection that also defines a `list_template` (e.g. `/blog` for posts, `/projects` for projects) → that list template
4. Each collection can specify its own `route`, `template`, and `list_template` keys — that's how you wire a custom collection to its own URL and templates with no `src/` changes.

## Doing common things

### "Make the site look like X"

Edit `templates/theme/default/layout.twig`. The CSS lives inline in a `<style>` block at the top — change colors, fonts, spacing there. The header/nav/footer are in the same file.

For per-page layouts, edit/add `home.twig`, `page.twig`, `post.twig`, `post-list.twig`, `404.twig` (and the form result page `form-result.twig`). Each extends `layout.twig` and overrides `{% block content %}`.

### "Add a {custom_thing} to the site"

Add a collection in `config/collections.php`:

```php
'projects' => [
    'label'          => 'Projects',
    'label_singular' => 'Project',
    'route'          => '/projects/{slug}',
    'template'       => 'project.twig',
    'list_template'  => 'project-list.twig',
    'order_by'       => 'updated_at DESC',
    'list_limit'     => 100,           // optional; defaults to 100 on the public list page
    'fields' => [
        'title'        => ['type' => 'text', 'required' => true],
        'slug'         => ['type' => 'slug', 'required' => true],
        'summary'      => ['type' => 'textarea'],
        'body'         => ['type' => 'markdown'],
        'cover_image'  => ['type' => 'url', 'help' => 'URL from /admin/media'],
        'featured'     => ['type' => 'boolean'],
    ],
],
```

Then create `templates/theme/default/project.twig` and `project-list.twig`. The admin UI updates automatically; the user fills in entries; the public site renders them.

### "Add a contact form"

The default config already ships a `contact` form. To add another:

```php
'job_application' => [
    'label'   => 'Job Applications',
    'is_form' => true,
    'fields'  => [
        'name'       => ['type' => 'text',     'required' => true],
        'email'      => ['type' => 'text',     'required' => true],
        'role'       => ['type' => 'select',   'options' => ['Engineer', 'Designer'], 'required' => true],
        'cover'      => ['type' => 'textarea', 'required' => true],
    ],
],
```

In a theme template, render the form with `<form method="post" action="/forms/job_application">` and `<input>` tags whose `name` attributes match the field keys. Add `<input type="text" name="_hp" style="display:none">` for honeypot bot protection.

### "Build a homepage with hero + features + recent posts"

1. Create or replace `templates/theme/default/home.twig`.
2. The default home.twig already has access to `recent_posts`. The site name is in `site.name`. Add hero/features sections by editing the Twig.
3. Don't try to fetch arbitrary data here — if you need extra data, add a collection (e.g. `homepage_features`) and reference it via `config/collections.php`.

## Field types you can use in collections

`text`, `textarea`, `markdown`, `slug`, `boolean`, `number`, `select` (with `options`), `datetime`, `url`.

There's no `image` or `relation` field type yet. For images, use a `url` field and have the user paste a URL from `/admin/media`. For relations, store the related entry's ID/slug in a `text` field and look it up in the template.

## Useful Twig helpers

In any template:
- `{{ entry.field('body')|markdown }}` — render markdown content as HTML.
- `{{ nav() }}` — list of `{label, url}` items: Blog (if `posts` exists) plus up to 5 most recent published pages. Lazy: themes that hardcode their own nav skip the query entirely.
- `{{ csrf_field()|raw }}` inside admin forms (already handled in admin templates).
- `{{ flash('success') }}` for one-shot success messages (admin only).
- `{{ current_user() }}` for the logged-in user (admin only).
- Standard Twig: `|date`, `|slice`, `|number_format`, `|escape`, `|raw` (rarely — autoescape is the safety belt).

## Deploy

When the user is ready: upload the entire repo into `public_html/` on their host. Hit `/install.php`. That's it. They can edit content via `/admin` from then on.

## When in doubt

The wedge is **"WordPress without the bloat."** If a request would push Pebblestack toward a plugin marketplace, a block editor, multisite, or other WordPress complexity — push back, suggest a simpler shape. Three files of Twig is better than a new framework feature.
