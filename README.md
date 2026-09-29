# WP Block Boosty

**WP Block Boosty** is a WordPress plugin that enhances the default Gutenberg blocks with modern, customizable visual styles.

It provides a centralized settings panel for styling blockquotes, lists, tables, images, galleries, headings, lead paragraphs, alternating sections, Latest Posts, and Pros / Cons layouts — without requiring custom code for every page.

> **Version:** 1.6.0
> **Requires WordPress:** 6.0+
> **Requires PHP:** 8.0+

---

## ✨ Features

WP Block Boosty extends the WordPress block editor with a collection of ready-to-use visual enhancements.

### Supported Sections

| Section                  | Description                                                               |
| ------------------------ | ------------------------------------------------------------------------- |
| **Blockquotes**          | Add author photos, names, roles, and quote icons                          |
| **Ordered Lists**        | Custom numbered markers and styled headings inside list items             |
| **Unordered Lists**      | Card-style lists with optional decorative numbering                       |
| **Tables**               | Zebra rows, colored headers, shadows, borders, and rounded corners        |
| **Lead Paragraph**       | Automatically highlight the first paragraph of an article                 |
| **Hero Skins**           | Aurora, Glass, and Editorial hero styles for lead paragraphs              |
| **Heading Decor**        | Add lines, squares, or Dashicons to H1–H4 headings                        |
| **Images**               | Border radius, shadows, and hover effects for images                      |
| **Gallery Carousel**     | Convert Gutenberg galleries into horizontal CSS Scroll Snap sliders       |
| **Latest Posts**         | Card-based post layouts with hover effects                                |
| **Alternating Sections** | Automatically group content between H2 headings into alternating sections |
| **Pros / Cons**          | Dedicated styling for Pros / Cons layouts based on Gutenberg Columns      |

---

## ⚙️ Settings

After activating the plugin, open:

**WordPress Dashboard → Settings → Block Boosty**

Direct URL:

```text
/wp-admin/options-general.php?page=wp-block-boosty-settings
```

The settings page contains multiple tabs for configuring individual sections.

### Global Settings

The **Global** tab provides the main styling and behavior options:

* Primary and secondary colors
* Primary color cascading
* Border radius
* Border width for normal and hover states
* Shadows
* CSS class prefix
* Display rules
* Animation transition speed
* Backup & Restore tools

### Backup & Restore

Settings can be exported and imported as JSON.

This makes it easy to:

* back up your configuration;
* move settings between WordPress installations;
* restore previous settings;
* reset the plugin to its default configuration.

---

# 📝 Lead Paragraph & Hero Sections

WP Block Boosty can automatically detect the first meaningful text paragraph of an article and apply a custom Lead Paragraph style.

The plugin ignores empty paragraphs and headings inside supported structures such as quotes and Pros / Cons blocks.

## Hero Skins

The **Lead P** settings include three predefined Hero styles.

### Aurora

A dark hero layout featuring:

* gradient mesh effects;
* animated decorative orbs;
* modern visual styling.

### Glass

A light glassmorphism-inspired design featuring:

* blur effects;
* translucent surfaces;
* subtle borders;
* glowing accents.

### Editorial

A dark editorial-style hero featuring:

* premium typography;
* accent lighting;
* a strong visual introduction.

When a Hero Skin is selected, it overrides the manual Lead Paragraph background, spacing, and shadow settings.

---

# 👍 Pros / Cons Columns

WP Block Boosty provides a dedicated styling system for Pros / Cons sections built on the standard Gutenberg **Columns** block.

## How to Use

1. Add a **Columns** block.
2. Add the following additional CSS class:

```text
col-pros-cons
```

3. Add a heading inside each column.
4. Use a regular unordered list below the heading.

The heading can be:

```html
<p>Pros</p>
```

or:

```html
<h3>Pros</h3>
```

or:

```html
<h4>Pros</h4>
```

The same structure can be used for the Cons column.

---

## Pros / Cons Skins

A default skin can be selected from the plugin settings.

Alternatively, a skin can be assigned directly to an individual block using an additional CSS class.

### Available Skins

#### Soft

```text
pc-skin-soft
```

Soft cards with a large decorative icon positioned in the corner.

#### Good / Bad

```text
pc-skin-goodbad
```

Large uppercase headings with a compact tag-style element.

#### Editorial

```text
pc-skin-editorial
```

A gradient background surrounding the section with individual inner cards.

#### Minimal

```text
pc-skin-minimal
```

A clean layout with minimal decorative elements.

### Example

```text
wp-block-columns col-pros-cons pc-skin-editorial
```

If no `pc-skin-*` class is specified, the default skin from the plugin settings is used.

---

## Pros / Cons Customization

The Pros / Cons settings allow separate customization for both sides.

### Pros

* Background color
* Heading background
* List icon color
* Decorative element color
* List icon
* Decorative icon
* Decoration size
* Decoration opacity

### Cons

The same options are available independently for the Cons column.

### List Icons

Pros:

* `check`
* `plus`

Cons:

* `cross`
* `minus`

### Decorative Icons

Pros:

* `thumb-up`
* `check`
* `plus`
* `star`
* `spark`

Cons:

* `thumb-down`
* `cross`
* `minus`
* `alert`
* `ban`

---

# 🎨 Heading Decorations

Heading Decor adds optional visual elements to Gutenberg headings.

Supported heading levels:

```text
H1
H2
H3
H4
```

Depending on the configuration, headings can use:

* decorative lines;
* squares;
* Dashicons;
* highlighted words.

---

# 🖼️ Images

The Images / Figure section adds visual styling to Gutenberg images.

Available effects include:

* border radius;
* normal and hover shadows;
* hover transitions;
* customizable visual presentation.

The styling is applied automatically according to the plugin's display rules.

---

# 🖼️ Gallery Carousel

Standard Gutenberg galleries can be transformed into horizontal carousels.

The carousel uses modern CSS features, including:

* horizontal scrolling;
* CSS Scroll Snap;
* responsive behavior;
* lightweight JavaScript enhancements.

No jQuery dependency is required on the frontend.

---

# 📋 Lists

## Ordered Lists

Ordered lists support custom numbered markers.

The plugin can also style `<strong>` elements inside list items as visual headings.

## Unordered Lists

Unordered lists can be displayed as modern card-style layouts.

Optional decorative numbering is available:

```text
01
02
03
...
```

The dedicated Pros / Cons layout has its own list processing and is intentionally excluded from the standard unordered-list card styling.

---

# 📊 Tables

Gutenberg tables can be enhanced with:

* zebra row styling;
* custom header colors;
* borders;
* rounded corners;
* shadows;
* hover states.

All visual settings can be controlled through the plugin options.

---

# 📰 Latest Posts

The Latest Posts section adds a card-based visual style to the WordPress Latest Posts block.

Available interactions include:

* Lift Up hover effect;
* Scale hover effect;
* responsive card layouts.

---

# 🔄 Alternating Sections

WP Block Boosty can automatically organize article content into alternating sections based on H2 headings.

Content between H2 headings is grouped into sections and can receive alternating styling for a more structured editorial layout.

This is especially useful for:

* long-form articles;
* guides;
* tutorials;
* comparison pages;
* editorial content.

---

# 🎯 Display Rules

WP Block Boosty includes display controls that allow the frontend styles to be limited to specific content.

You can configure the plugin to work with:

* Pages;
* Posts;
* specific post/page IDs.

This makes it possible to enable the visual system only where it is needed.

---

# 🧩 CSS Prefix

The plugin uses a configurable CSS prefix to reduce the chance of conflicts with themes and other plugins.

The default prefix is:

```text
be-
```

The prefix can be changed from the Global settings.

---

# 🛠️ Technical Details

WP Block Boosty is designed to keep the frontend lightweight while providing flexible styling controls.

### Settings

Plugin settings are stored in a single option:

```text
wp_block_boosty_options
```

This reduces the number of database queries required to retrieve configuration values.

### Caching

Settings are cached in memory during the current request.

Generated dynamic CSS is cached using a WordPress transient:

```text
wpbb_dynamic_css
```

The dynamic CSS cache is automatically cleared when plugin settings are saved.

### Dynamic CSS

The plugin generates dynamic CSS in `wp_head` and uses CSS variables for global design tokens.

### JavaScript

The frontend uses vanilla JavaScript and does not require jQuery.

JavaScript is used for features such as:

* gallery carousel behavior;
* Latest Posts interactions;
* scroll-based animations.

### Scroll Animations

Micro-animations use the native browser:

```text
IntersectionObserver
```

This allows elements to animate as they enter the viewport without requiring a third-party animation library.

### Pros / Cons Processing

The Pros / Cons section is processed with `DOMDocument` only when the content contains:

```text
wp-block-columns.col-pros-cons
```

This keeps additional content processing limited to the blocks that actually use the feature.

---

# 📁 Plugin Structure

```text
wp-block-boosty/
├── wp-block-boosty.php
├── uninstall.php
├── includes/
│   ├── class-settings.php
│   ├── class-admin.php
│   └── class-frontend.php
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── frontend.css
│   └── js/
│       ├── admin.js
│       └── frontend.js
└── README.md
```

### Main Files

**`wp-block-boosty.php`**
Main plugin entry point.

**`uninstall.php`**
Removes plugin options and cached dynamic CSS when the plugin is uninstalled.

**`includes/class-settings.php`**
Handles default settings, sanitization, and option management.

**`includes/class-admin.php`**
Creates the WordPress administration interface and settings tabs.

**`includes/class-frontend.php`**
Handles frontend content processing, dynamic CSS, and Pros / Cons skins.

**`assets/css/admin.css`**
Styles for the WordPress admin interface.

**`assets/css/frontend.css`**
Base frontend styles.

**`assets/js/admin.js`**
Admin tabs, color picker, and media upload functionality.

**`assets/js/frontend.js`**
Carousel behavior, Latest Posts interactions, and scroll animations.

---

# 🔒 Security & WordPress Standards

The plugin follows standard WordPress practices, including:

* WordPress Settings API;
* nonce protection through `settings_fields`;
* input sanitization;
* output escaping;
* configurable CSS prefixes;
* cleanup on uninstall.

Common WordPress escaping and sanitization functions include:

```php
sanitize_text_field()
absint()

esc_attr()
esc_html()
esc_url()
```

---

# 📦 Installation

## From the WordPress Dashboard

1. Download or package the plugin.
2. Open **Plugins → Add New → Upload Plugin**.
3. Upload the plugin ZIP file.
4. Install the plugin.
5. Activate **WP Block Boosty**.
6. Go to **Settings → Block Boosty**.

## Manual Installation

1. Download the repository.
2. Upload the `wp-block-boosty` directory to:

```text
/wp-content/plugins/
```

3. Activate the plugin from **Plugins → Installed Plugins**.
4. Open:

```text
Settings → Block Boosty
```

---

# 💡 Requirements

* **WordPress:** 6.0 or newer
* **PHP:** 8.0 or newer
* **Editor:** Gutenberg / WordPress Block Editor

---

# 🚀 Use Cases

WP Block Boosty is useful for websites that rely heavily on Gutenberg and want a consistent visual system without adding a page builder.

Typical use cases include:

* blogs;
* editorial websites;
* product reviews;
* comparison websites;
* tutorials and guides;
* affiliate websites;
* content-heavy WordPress projects;
* magazine-style websites.

---

# 📌 Compatibility

WP Block Boosty works with standard WordPress Gutenberg blocks and is designed to complement existing WordPress themes.

Because the plugin uses configurable CSS prefixes and scoped block styles, it can be integrated into existing WordPress projects with less risk of global style conflicts.

---

# 🤝 Contributing

Contributions, bug reports, feature requests, and improvements are welcome.

If you find a bug or have an idea for a new feature, please open an issue in the GitHub repository.

Pull requests are also welcome.

---

# 📄 License

See the repository for the current license information.

---

## Author

**Marina Polypen**

GitHub: [@marina-polypen](https://github.com/marina-polypen)

---

## 🔗 Repository

[WP Block Boosty on GitHub](https://github.com/marina-polypen/WP-Block-Boosty)
