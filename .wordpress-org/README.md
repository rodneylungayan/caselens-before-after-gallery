# WordPress.org directory assets

These files are for the plugin's page in the WordPress.org directory. They are **not** part of the plugin, and they are kept out of the release zip by `.gitattributes` and `.distignore`.

## Where they go

In the plugin's SVN repository they belong in the top-level `assets/` folder, which sits beside `trunk/` rather than inside it:

```
caselens-before-after-gallery/
├── assets/      <- these files
├── trunk/       <- the plugin itself
└── tags/
```

The folder is called `assets/` in SVN but `.wordpress-org/` here, because the plugin already has its own `assets/` folder for CSS and JavaScript.

```bash
svn co https://plugins.svn.wordpress.org/caselens-before-after-gallery
cp .wordpress-org/* caselens-before-after-gallery/assets/
cd caselens-before-after-gallery
svn add assets/* --force
svn ci -m "Add directory screenshots"
```

## Screenshots

Numbered to match the `== Screenshots ==` list in `readme.txt`; the captions come from there, in order.

| File | Caption in readme.txt |
|---|---|
| `screenshot-1.png` | The gallery: procedure menu, filter bar and the grid of before/after cards. |
| `screenshot-2.png` | The filter dialog, built from the case fields you defined. |
| `screenshot-3.png` | One case: before and after pairs beside the case details. |
| `screenshot-4.png` | The case editor, with photo pairs and detail fields. |
| `screenshot-5.png` | The Case Fields screen. |
| `screenshot-6.png` | Gallery settings. |

If you reorder or reword that list, rename these to match — the directory pairs them by number alone.

## How they were produced

Captured at 1280×960 from a WordPress Playground site running the plugin with sample content. The photos are generated placeholders, not real cases.

```bash
# from the test harness
node shoot.mjs http://127.0.0.1:9450 ./.wordpress-org
```

## Still to add

Optional but recommended for the directory listing:

- `icon-256x256.png` — the icon on search results and the plugin card
- `banner-772x250.png` — the header on the plugin page
- `banner-1544x500.png` — the same banner at 2x
