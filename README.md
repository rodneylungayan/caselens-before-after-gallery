# CaseLens Before and After Gallery

A filterable before-and-after case gallery for WordPress, with its own post type, procedure categories, and case detail fields you define yourself.

Built for any site that shows paired results — clinics and practices, dentists, hair and beauty studios, renovation and landscaping firms, detailers, restorers, cleaners, trainers.

- **Author:** Rodney Lungayan — <http://rodneylungayan.com/wordpress-plugins>
- **Licence:** GPL-2.0-or-later
- **Requires:** WordPress 6.2+, PHP 7.4+
- **Tested on:** WordPress 7.1.3, PHP 8.2

Standalone by design: no Advanced Custom Fields, no page builder, no particular theme.

---

## Contents

- [What you get](#what-you-get)
- [Install](#install)
- [Quick start](#quick-start)
- [The shortcode](#the-shortcode)
- [URLs](#urls)
- [Case fields](#case-fields)
- [Photo pairs](#photo-pairs)
- [Settings reference](#settings-reference)
- [Things to know](#things-to-know)
- [Overriding the templates](#overriding-the-templates)
- [Hooks](#hooks)
- [REST API](#rest-api)
- [Uninstalling](#uninstalling)
- [Development](#development)

---

## What you get

- A **Gallery Cases** post type (`caselens_case`) with its own menu, list columns and editor.
- A hierarchical **Procedures** taxonomy (`caselens_procedure`) for grouping cases, with parents and children.
- **Before/after photo pairs** on each case, picked from the media library and reorderable by drag.
- **Case fields you define yourself** — text, number, single choice, multiple choice — grouped however you like.
- A front-end gallery with a procedure menu, a filter dialog built from your own fields, a case-number search, and "Load more".
- Clean URLs, with 301 redirects from older query-string links.
- Titles, descriptions and canonical tags for procedure pages, on plain WordPress and through Yoast SEO.
- Templates you can override from your theme.

The first page of cases is rendered by PHP, so the gallery is visible to visitors and search engines **without JavaScript**. The script then adds procedure switching without a reload, the filter dialog, and "Load more", all through the plugin's own REST routes.

---

## Install

**From the zip**

1. Plugins → Add New → Upload Plugin.
2. Choose `caselens-before-and-after-gallery-1.0.0.zip`.
3. Install, then Activate.

**From source**

```bash
git clone https://github.com/rodneylungayan/caselens-before-after-gallery.git
```

Place the folder in `wp-content/plugins/` and activate. The folder should be named `caselens-before-and-after-gallery` (the WordPress.org slug), which matches the text domain.

On activation the plugin creates a page called **Gallery** holding the `[caselens_gallery]` shortcode, and points its settings at that page.

---

## Quick start

1. **Procedures** — Gallery Cases → Procedures. Add the groups your cases belong to. Nest them if you want a two-level menu (for example *Body* → *Tummy Tuck*).
2. **Case Fields** — Gallery Cases → Case Fields. Set the details each case should hold. Tick **Filter** on the ones visitors should be able to narrow by.
3. **Add a case** — Gallery Cases → Add Case. Assign procedures, add before/after pairs, fill in the details, publish.
4. **Check the page** — visit `/gallery/`.
5. **Settings** — Gallery Cases → Settings for the URL base, wording, accent colour and grid options.

---

## The shortcode

Put the gallery on any page:

```
[caselens_gallery]
```

| Attribute | Default | What it does |
|---|---|---|
| `procedure` | — | Show only one procedure, by slug |
| `per_page` | `12` | Cases per page (3–60) |
| `columns` | `3` | Grid columns (1–6) |
| `sidebar` | `yes` | Show the procedure menu |
| `filters` | `yes` | Show the filter button |
| `search` | `yes` | Show the case-number search |
| `heading` | from settings | Heading above the grid |

Examples:

```
[caselens_gallery procedure="tummy-tuck" sidebar="no" columns="2"]
[caselens_gallery per_page="24" filters="no"]
```

You can place several galleries on one page; each runs independently.

---

## URLs

With the URL base set to `gallery`:

| URL | What it shows |
|---|---|
| `/gallery/` | Every case |
| `/gallery/tummy-tuck/` | The gallery filtered to that procedure |
| `/gallery/tummy-tuck/tummy-tuck-901/` | One case |

These older forms 301-redirect to the clean ones:

- `/gallery/?procedure=tummy-tuck` → `/gallery/tummy-tuck/`
- `/gallery/tummy-tuck-901/` → `/gallery/tummy-tuck/tummy-tuck-901/`
- A case opened under the wrong procedure segment → its canonical URL

The procedure segment comes from the case's Yoast primary term when one is set, otherwise its most specific procedure.

---

## Case fields

Gallery Cases → Case Fields. Each field has a **Label**, a **Key**, a **Group**, a **Type**, and an optional **Suffix**.

| Type | Editor input | As a filter |
|---|---|---|
| Text | Single-line box | Checkbox list of the values in use |
| Number | Number box | Minimum and maximum boxes |
| Choice (one) | Dropdown | Checkbox list |
| Choice (several) | Checkboxes | Checkbox list, matching any |

Choices are entered one per line as `value : Label`:

```
silicone : Silicone
saline : Saline
gummy : Highly Cohesive Silicone Gel
```

A line with no colon uses the same text for both.

Two tick boxes per field:

- **Show on case** — include it in the details table beside the photos.
- **Filter** — offer it in the filter dialog.

The filter dialog is built from **values that published cases actually have**, with a count beside each. Options nobody matches never appear, so visitors can't filter their way to an empty grid. Within one field the choices are OR'd; across fields they're AND'd.

---

## Photo pairs

Each case holds any number of before/after pairs. Open a case and use **Before and After Photos**:

- **Choose image** on either side opens the media library.
- The caption is optional and shows under that pair.
- Drag the handle on the left to reorder rows.
- **The first row is the card** shown in the gallery grid.

A pair can have only one side filled; the empty side renders as a placeholder. If a case has no pairs at all, its featured image is used for the grid card.

---

## Settings reference

Gallery Cases → Settings.

| Setting | Default | Notes |
|---|---|---|
| Gallery page | auto-created | The page holding the shortcode; procedure links point here |
| URL base | `gallery` | Cases live under this |
| Heading | Before and After Photo Gallery | Replaced by the procedure name on a procedure page |
| Disclaimer | Individual results may vary | Shown above the grid and on each case; empty hides it |
| Cases per page | `12` | 3–60 |
| Accent colour | `#4fb3b3` | Drives cards, links, detail labels |
| Show the filter button | on | |
| Show the case number search | on | |
| Hide procedures with no cases | on | A parent stays visible while a child has cases |
| 404 for an empty procedure URL | on | Off shows an empty gallery instead |
| Previous and next links | on | Within the same procedure, by date |
| Open photos full size | on | Click-to-zoom overlay on a case |
| Procedure order | — | One slug per line; the rest follow A–Z |
| Word for one case | Patient | Used in auto-generated headings |
| Button text / link | — | Call to action under the details; empty link hides it |
| Delete data on uninstall | **off** | See [Uninstalling](#uninstalling) |

---

## Things to know

Worth reading before you put this on a live site.

**Flush permalinks after changing the URL base.** The plugin refreshes its rewrite rules automatically, but if a gallery URL 404s after a change, go to Settings → Permalinks and click Save Changes. Nothing needs filling in — loading that screen rebuilds the rules.

**Keep the gallery page slug and the URL base the same.** If the base is `gallery`, the page should live at `/gallery/`. They are separate settings, so they can drift apart, and procedure URLs resolve against the base.

**A case with no procedure keeps the plain URL.** Until you assign at least one procedure it stays at `/gallery/{case-slug}/`. Assign one and the permalink becomes `/gallery/{procedure}/{case-slug}/` — WordPress does not redirect old links it never knew about, so set procedures before you publish and share a case.

**Changing a field key orphans its data.** Field values are stored per key. Rename the *Label* freely, but changing the *Key* makes existing cases look empty for that field, because the old values are still filed under the old key. Pick keys before you enter data.

**Leave the title blank for an auto-generated heading.** A case with no title is shown as `{Procedure} {Word for one case} #{number}` — for example *Tummy Tuck Patient #901*. Give it a title and that title is used instead, everywhere.

**The case number comes from a field.** The number search and the auto-generated heading look for a field keyed `case_number`, `patient_number` or `number`, in that order. With none of those defined, the plugin falls back to a `#123` in the title or a purely numeric slug.

**Empty procedures 404 by default.** A procedure with no published cases returns 404 rather than an empty page, so thin pages stay out of the index. Turn it off in Settings if you'd rather show the empty gallery.

**A theme template wins outright.** If your theme has `single-caselens_case.php`, the plugin steps aside completely and leaves the content alone. Use the `caselens/` override folder instead if you want the plugin's layout with changes.

**Filters only reflect published cases.** Drafts and private cases are excluded from the grid, the counts and the filter options.

**Multisite:** activate per site. Each site keeps its own cases, fields and settings.

**Patient photos are public.** Nothing in the plugin restricts access — every published case is public content, like any post. Make sure you have the consent you need before publishing, and keep identifying detail out of file names and alt text.

---

## Overriding the templates

Copy a template from the plugin into a `caselens` folder inside your theme and edit the copy:

```
wp-content/themes/your-theme/caselens/gallery.php
wp-content/themes/your-theme/caselens/single-case.php
```

Your copy is used instead of the plugin's, and plugin updates won't touch it. The variables each template receives are listed in the docblock at the top of the file.

---

## Hooks

**`caselens_procedure_meta`** — change the generated title and description of a procedure page.

```php
add_filter( 'caselens_procedure_meta', function ( $meta, $term ) {
	$meta['title'] = $term->name . ' Results | My Clinic';
	return $meta;
}, 10, 2 );
```

Standard WordPress hooks apply throughout: `post_type_link`, `the_content`, the `register_post_type` and `register_taxonomy` arguments, and the Yoast filters the plugin already hooks into.

---

## REST API

Three read-only routes under `caselens/v1`, serving published cases only.

| Route | Parameters |
|---|---|
| `GET /wp-json/caselens/v1/cases` | `procedure`, `number`, `page`, `per_page`, `f[key][]`, `r[key][min|max]` |
| `GET /wp-json/caselens/v1/filters` | `procedure` |
| `GET /wp-json/caselens/v1/procedures` | — |

```bash
# Female cases with an open technique, within one procedure
curl "https://example.com/wp-json/caselens/v1/cases?procedure=tummy-tuck&f[gender][]=female&f[technique][]=open"

# Cases where age is 40 or over
curl "https://example.com/wp-json/caselens/v1/cases?r[age][min]=40"
```

`/cases` also sets `X-WP-Total` and `X-WP-TotalPages`.

---

## Uninstalling

Deactivating changes nothing but the rewrite rules.

Deleting the plugin removes its settings and field definitions. **Your cases, procedures and photos are kept** unless you tick *Also delete every case, procedure and setting* under Settings → Uninstalling first. That box is off by default, and there is no undo once it runs.

---

## Development

The plugin is plain PHP with no build step. Clone it into `wp-content/plugins/` and work directly.

To run it on a disposable WordPress with [WordPress Playground](https://wordpress.github.io/wordpress-playground/):

```bash
npx @wp-playground/cli@latest server --auto-mount . --login
```

Syntax checks:

```bash
find . -name "*.php" -exec php -l {} \;
node --check assets/js/gallery.js
```

Coding standards follow the WordPress PHP and JavaScript standards; classes are prefixed `CaseLens_`, functions and options `caselens_`.

---

## Licence

GPL-2.0-or-later. See [LICENSE.txt](LICENSE.txt).
