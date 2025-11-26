# WC Product Gallery

Advanced product gallery for WordPress with mobile swipe support, responsive thumbnail navigation, and lazy loading. Built to work seamlessly with Advanced Custom Fields (ACF).

## Features

*   📱 **Mobile First**: Native-like swipe gestures for mobile users.
*   🖱️ **Desktop Navigation**: Clickable thumbnail grid.
*   ⚡ **Performance**: Built-in lazy loading and optimized asset loading (only loads when shortcode is used).
*   🎨 **Customizable**: Clean CSS variables for easy theming.
*   🔄 **Auto-Updates**: Automatic updates directly from GitHub.

## Requirements

*   WordPress 5.0+
*   Advanced Custom Fields (ACF) plugin (Free or Pro)

## Installation

1.  Download the latest release `.zip` file.
2.  Go to **Plugins > Add New > Upload Plugin**.
3.  Upload and activate the plugin.
4.  Ensure you have ACF installed and a Gallery field created.

## Usage

### Basic Usage
Add the shortcode to any post, page, or template:

```shortcode
[product_gallery]
```

By default, this looks for an ACF field named `gallery_produk`.

### Custom Field Name
If your ACF gallery field has a different name:

```shortcode
[product_gallery field="my_custom_gallery"]
```

### Disable Lazy Loading
Lazy loading is enabled by default. To disable it:

```shortcode
[product_gallery lazy="false"]
```

## Configuration

Go to **Settings > Product Gallery** to configure global defaults:
*   **Default ACF Field**: Set the default field name so you don't have to add `field=""` every time.

## Developer Hooks

### Filters

**`wc_gallery_max_images`**
Limit the number of images displayed (default: 20).

```php
add_filter('wc_gallery_max_images', function($max) {
    return 30;
});
```

## Changelog

### 1.0.2
*   Added auto-update support via GitHub.
*   Performance improvements.

### 1.0.0
*   Initial release.
