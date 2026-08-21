# AGF Listings

The plugin that owns the farms. Post type, seven taxonomies, the add-a-farm
form, and the combined filter — all registered here, never in a theme.

Switch theme tomorrow and every farm, photograph and filter term is still
exactly where it was. That is the deal-breaker in the brief, and this plugin is
how it is satisfied by construction rather than by luck.

Ships with the same self-contained **GitHub Releases updater** as the theme:
tag a release, and every site running the plugin sees a normal "Update
available" notice under Plugins.

## What it registers

| Thing | Detail |
|---|---|
| Post type | `listing` — archive at `/farms/`, exposed to the REST API |
| `listing_category` | Game farms, Cattle farms. **Two terms only.** |
| `province` | 9 SA provinces + Namibia + Botswana |
| `region` | Nested under province, filled during migration |
| `size_band` | 5 bands, assigned automatically from hectares |
| `price_band` | 4 bands, assigned automatically from price |
| `status` | New listing, Sold, Off market |
| `species` | Free-form, switched on from data the old site already holds |

### Three faults it corrects

1. **Western Cape was a category.** On the current site it sits in
   `listing_category` beside Game and Cattle, so a Western Cape farm can drop out
   of the Game Farms archive entirely. Here category has exactly two terms and
   Western Cape is a province like any other.
2. **"Above R10 Mill" did no work.** Price bands are terms the client can re-cut
   in the admin once he sees his own distribution. No developer involvement.
3. **Big Five was treated as a status.** A farm can be a new listing *and* a Big
   Five property, so Big Five is its own flag and status stays single-select.

## The add-a-farm form

One screen of labelled fields — never a page-builder canvas:

- Description (the main editor), plus **Improvements**, **Wildlife &
  vegetation** and **Land claims** as their own boxes
- Wildlife hides automatically on cattle farms
- Price and size in hectares, which drive the bands
- Big Five flag, YouTube link, and a drag-to-reorder photograph gallery

The VAT line is rendered from the price, so it cannot be left off by accident.

Written against core APIs — no CMB2, no ACF, nothing extra for the client to keep
updated.

## Adding farms

Three ways, depending on what you are doing:

**Quick add** (*Farms → Quick add*) — one screen with the eight things a farm
cannot go live without: name, kind, province, price, hectares, status, main
photograph, description. "Save and add another" keeps the province and category
you just used, because the next farm is usually next door.

**Duplicate** — a row action on any farm. Copies the content, every taxonomy term
and every custom field, and always lands as a **draft** so a half-edited copy can
never appear on the site.

**Full editor** — the classic editor with one labelled form: farm details, the
three extra sections, and the gallery. The block editor is switched off for farms
on purpose; it hides meta boxes in a collapsed drawer, which is the experience the
brief exists to avoid. Listings stay in the REST API regardless.

The **Before this goes live** panel lists what is still missing. It never blocks
publishing — the client knows his own inventory better than a checklist does.

The farms list gains Extent and Price columns (both sortable) and dropdown
filters for kind, province and status.

## Elementor widgets — on the FREE version

The mockups call for Loop Grid, the Form widget, Nav Menu and Taxonomy Filter.
Every one of those is Elementor **Pro**. Elementor's *widget API*, however, is
free, so this plugin ships the widgets the design needs instead. Nobody using
this template pays a licence fee.

| Widget | Replaces | What it does |
|---|---|---|
| **Farm Grid** | Loop Grid | Farm cards. Either a chosen set, or whatever the page is filtered to. |
| **Farm Search** | Form | The five-dropdown search. Plain GET, so results are linkable. |
| **Farm Filters** | Taxonomy Filter | Archive sidebar with live counts, reads and writes the URL. |
| **Province Tiles** | — | Browse by province, region, size or species, with counts. |
| **Category Cards** | — | Game / cattle cards with live listing counts. |
| **Farm Details** | Dynamic tags | One part of a farm at a time — facts, price, the four sections, species, gallery, video. Build a single-farm layout by stacking these. |
| **Enquiry Form** | Form | Carries the farm name into the subject line. Honeypot, nonce, rate limit, Reply-To set to the sender. |

They appear under a **Farms** category in the Elementor panel. Colours and columns
are Elementor controls, so the widgets take on whichever theme they land in.

What we cannot replace is **Theme Builder**, which is what puts a template onto an
archive or a single post. The theme solves that separately by rendering an
assigned Elementor page in those positions.

## The combined filter

Category, province, region, size and price apply **together** in one query, never
one at a time:

```
/?post_type=listing&province=limpopo&listing_category=game-farms&price_band=r10m-r20m
```

Sorting via `?sort=` — `latest`, `oldest`, `price-low`, `price-high`.

## Configure the updater

Edit `acreage-listings.php`:

```php
define( 'ACREAGE_CORE_GITHUB_REPO', 'sanket5257/acreage-listings' );
```

Private repo? Add a fine-grained token with *Contents: read* to `wp-config.php`:

```php
define( 'ACREAGE_CORE_GITHUB_TOKEN', 'github_pat_xxx' );
```

## Cutting a release

1. Bump **both** the `Version:` header and `ACREAGE_CORE_VERSION` in
   `acreage-listings.php`. The build refuses to run if they disagree.
2. Commit and push.
3. `.\build.ps1` → `dist\acreage-listings.zip`
4. Draft a GitHub release tagged `v1.0.1` and **attach `dist\acreage-listings.zip`**.

Install `dist\acreage-listings.zip`, never GitHub's "Source code (zip)" — that one is
named after the repo and tag, so the plugin lands in the wrong folder and the
updater stops matching it.

## Deactivating

Deactivation flushes rewrite rules and nothing else. Listings and terms stay in
the database — deactivating a plugin must never destroy a client's inventory.
