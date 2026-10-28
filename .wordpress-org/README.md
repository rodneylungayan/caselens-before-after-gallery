# WordPress.org directory assets

These files are for the plugin's page in the WordPress.org directory. They are **not** part of the plugin, and they are kept out of the release zip by `.gitattributes` and `.distignore`.

## Where they go

In the plugin's SVN repository they belong in the top-level `assets/` folder, which sits beside `trunk/` rather than inside it:

```
caselens-before-and-after-gallery/
├── assets/      <- these files
├── trunk/       <- the plugin itself
└── tags/
```

The folder is called `assets/` in SVN but `.wordpress-org/` here, because the plugin already has its own `assets/` folder for CSS and JavaScript.

```bash
svn co https://plugins.svn.wordpress.org/caselens-before-and-after-gallery
cp .wordpress-org/* caselens-before-and-after-gallery/assets/
cd caselens-before-and-after-gallery
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

## Icon and banner

| File | Size | Where it shows |
|---|---|---|
| `icon-256x256.png` | 256×256 | The plugin card, and search results |
| `icon-128x128.png` | 128×128 | The smaller icon slot |
| `banner-772x250.png` | 772×250 | The header on the plugin page |
| `banner-1544x500.png` | 1544×500 | The same banner on retina screens |

Both are derived from `logo-source.webp`, the stacked lockup. The icon is the mark on its own, centred with a tenth of the frame as padding on each side so it survives being masked into a rounded shape. The banners re-lay the artwork horizontally — mark on the left, wordmark and tagline beside it — because a stacked logo dropped into a 3:1 frame either shrinks to a thumbnail or crops.

## How they were produced

The screenshots were captured at 1280 wide from a WordPress Playground site running the plugin with sample content. The photos in them are generated placeholders, not real cases.

```bash
node shoot.mjs http://127.0.0.1:9450 ./.wordpress-org        # all six
node shoot.mjs http://127.0.0.1:9450 ./.wordpress-org 1,3    # just those two
```

The icon and banners are composed from the logo. The script finds the mark, the wordmark and the tagline by scanning the artwork for bands of ink, so it keeps working if the logo is re-exported at another size.

```bash
node make-assets.mjs ./.wordpress-org/logo-source.webp ./.wordpress-org
```

Both scripts need `puppeteer`.
