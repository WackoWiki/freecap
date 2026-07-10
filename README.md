# freeCap

A modern, object-oriented CAPTCHA library for PHP 8.2+, originally created by Howard Yeend and maintained by the WackoWiki Team. freeCap uses GD to generate highly obfuscated, OCR-resistant CAPTCHA images.

This version has been completely refactored to support Composer, PSR-4 autoloading, custom session adapters, and strict standards. It retains all of the original's educational inline comments and OCR circumvention logic.

## Features

* **OCR Circumvention**: Implements character morphing (sine wave deviations), random fonts, background noise (grid, squiggles, morphed blocks), and blurring.
* **Brute Force Protection**: Locks out users after a configurable number of refresh attempts.
* **Dictionary & Random Words**: Supports pulling words from a dictionary file or generating pseudo-random consonant-vowel strings.
* **Multiple Formats**: Supports outputting `avif`, `gif`, `jpg`, `png`, and `webp` images.
* **Session Adapter API**: Decouples session handling from the core logic, making it easy to integrate with any framework (`$_SESSION`, custom session objects, PSR-16 caches, etc.).
* **Graceful Fallbacks**: If custom GD fonts or dictionary files are missing, it safely falls back to built-in fonts and random word generation.

## Requirements

* PHP >= 8.2
* GD Extension

## Installation

Install via Composer:

```bash
composer require wackowiki/freecap
```

## Directory Structure

Ensure your `.ht_freecap_*` assets (fonts, dictionaries, background images) are placed in a `resources/` directory at the root of your project or package.

```text
freecap/
├── resources/
│   ├── .ht_freecap_font1.gdf
│   ├── .ht_freecap_font2.gdf
│   ├── .ht_freecap_font3.gdf
│   ├── .ht_freecap_font4.gdf
│   ├── .ht_freecap_font5.gdf
│   ├── .ht_freecap_im1.jpg
│   ├── ...
│   └── .ht_freecap_words
├── src/
│   ├── AdapterInterface.php
│   ├── FreeCap.php
│   └── SessionAdapter.php
├── tests/
├── composer.json
└── LICENSE
```

## Basic Usage

### 1. Rendering the CAPTCHA Image

In your routing or controller logic, initialize the adapter and call `render()`. This outputs the image directly to the browser with the correct `Content-Type` headers.

```php
use FreeCap\FreeCap;
use FreeCap\SessionAdapter;

// 1. Initialize the adapter with your session mechanism
//    SessionAdapter accepts native arrays or objects with magic properties (like WackoWiki's)
$adapter = new SessionAdapter($_SESSION);

// 2. Optional: Pass custom configuration
$config = [
    'use_dict' => 1,
    'bg_type'  => 1,
    'output'   => 'webp',
];

// 3. Generate and render
$captcha = new FreeCap($adapter, $config);
$captcha->render(); // Exits script after sending image
```

### 2. Displaying in HTML

```html
<form method="POST" action="/submit">
    <!-- Display the image -->
    <img src="/captcha-endpoint.php" id="freecap" alt="CAPTCHA">
    
    <!-- Reload button (requires custom JS to append a random query string) -->
    <a href="#" id="reload-captcha">Reload</a>
    
    <!-- Input field -->
    <input type="text" id="captcha" name="captcha" maxlength="6" required>
</form>
```

### 3. Validating User Input

In your form processing logic, verify the submitted word against the session hash. **Make sure to set the `freecap_shown` flag when the form page loads so the validator knows a CAPTCHA was issued.**

```php
use FreeCap\FreeCap;
use FreeCap\SessionAdapter;

// When the form page is loaded, flag that the CAPTCHA is active:
// $adapter = new SessionAdapter($_SESSION);
// $adapter->set('freecap_shown', 1);

// On form submission:
$adapter = new SessionAdapter($_SESSION);
$captcha = new FreeCap($adapter);

$input = $_POST['captcha'] ?? '';

if ($captcha->validate($input)) {
    echo "CAPTCHA passed!";
} else {
    echo "CAPTCHA failed. Please try again.";
}
```

## Configuration Options

You can override default behaviors by passing an array to the `FreeCap` constructor.

```php
$config = [
    'asset_path'       => '/path/to/resources',  // Defaults to ../resources
    'site_tags'        => null,                   // Array of strings to display on the image
    'tag_pos'          => 1,                      // 0: top, 1: bottom, 2: both
    'algo'             => 'sha1',                 // 'sha1', 'sha256', 'sha512'
    'output'           => 'webp',                 // 'avif', 'gif', 'jpg', 'png', 'webp'
    'use_dict'         => 1,                      // 0: random string, 1: dictionary file
    'max_word_length'  => 6,                      // Maximum characters in the CAPTCHA
    'col_type'         => 1,                      // 0: one color, 1: different color per letter
    'max_attempts'     => 15,                    // Max refresh attempts before lockout
    'bg_type'          => 1,                      // 0: transparent, 1: grid, 2: squiggles, 3: morphed blocks
    'blur_bg'          => true,                   // Blur the background?
    'morph_bg'         => true,                   // Morph the background?
    'merge_type'       => 0,                      // 0: merge CAPTCHA with bg, 1: write over bg
];

$captcha = new FreeCap($adapter, $config);
```

## Custom Session Adapters

If you are not using native PHP sessions, you can create your own adapter by implementing the `FreeCap\AdapterInterface`.

```php
use FreeCap\AdapterInterface;

class MyCacheAdapter implements AdapterInterface
{
    private Cache $cache;

    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->cache->get('captcha_' . $key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        $this->cache->set('captcha_' . $key, $value);
    }

    public function delete(string $key): void
    {
        $this->cache->delete('captcha_' . $key);
    }
}
```

## Testing

The package includes PHPUnit 11 tests. To run the test suite:

```bash
composer install
vendor/bin/phpunit tests
```

## Credits

* **Howard Yeend** (solidred.co.uk) - Original author of freeCap v1.4.1
* **WackoWiki Team** - Modernization, OOP refactor, and maintenance (2008 - 2025)

## License

This project is licensed under the GNU General Public License v2.0 or later. See the `LICENSE` file for more details.
