# YouTube AI Cover Generator

[![Tests](https://github.com/artryazanov/yt-cover-gen/actions/workflows/tests.yml/badge.svg)](https://github.com/artryazanov/yt-cover-gen/actions/workflows/tests.yml)
[![codecov](https://codecov.io/gh/artryazanov/yt-cover-gen/graph/badge.svg?token=CODECOV_TOKEN)](https://codecov.io/gh/artryazanov/yt-cover-gen)
[![Latest Stable Version](https://poser.pugx.org/artryazanov/yt-cover-gen/v)](https://packagist.org/packages/artryazanov/yt-cover-gen)
[![Total Downloads](https://poser.pugx.org/artryazanov/yt-cover-gen/downloads)](https://packagist.org/packages/artryazanov/yt-cover-gen)
[![License](https://poser.pugx.org/artryazanov/yt-cover-gen/license)](https://packagist.org/packages/artryazanov/yt-cover-gen)

## Introduction

**YouTube AI Cover Generator** (`artryazanov/yt-cover-gen`) is a framework-agnostic PHP package designed to automatically generate viral, high-CTR (Click-Through Rate) YouTube thumbnails from gameplay screenshots using generic AI models.

It leverages powerful AI vision and image editing capabilities (Google Gemini) to analyze a screenshot, understand the context, and generate a stylized, professional-looking thumbnail with compelling text overlays and branding, strictly adhering to the game's art style.

## Examples

| Source | Result | Model |
| :---: | :---: | :---: |
| <img src="assets/example1-source.jpg" width="300"> | <img src="assets/example1-gemini-result.jpg" width="300"> | **Gemini**<br>`gemini-3-pro-image-preview` |

## Features

- **Framework Agnostic**: Can be used in any PHP 8.2+ project.
- **Laravel Integration**: Includes a Service Provider, Facade-friendly architecture, and configuration publishing.
- **Configurable Models**: Supports various Gemini models (`gemini-3.1-flash-image`, `gemini-3-pro-image`, etc.).
- **Smart Text Generation**: If the input title is short (<= 5 words), it is used directly to save API tokens and time. If it is longer, a text LLM condenses it into a short, punchy 2-5 word clickbait phrase. Then a vision model renders the final thumbnail.
- **Smart Logo Extraction**: Optionally pass an official game cover to accurately reproduce the game's logo in the thumbnail. The generator uses AI to extract a "clean" logo from the cover and caches it to prevent unwanted cover art elements from bleeding into the final thumbnail (supported by Gemini).
- **Smart Image Processing**: Handles image resizing, format conversion, and Base64 encoding/decoding automatically using GD (no external binaries required).
- **Prompt Engineering**: Built-in, battle-tested prompt templates optimized for high CTR.

## Requirements

- PHP 8.2 or higher
- `ext-gd` extension
- `ext-json` extension
- `gemini-api-php/client` (for Gemini driver)
- PSR-17 and PSR-18 compatible HTTP client/factory (for Gemini driver)

## Installation

Install the package via Composer:

```bash
composer require artryazanov/yt-cover-gen
```

## Configuration

### Laravel

1.  **Publish the configuration file:**

    ```bash
    php artisan vendor:publish --tag=yt-cover-gen-config
    ```

2.  **Configure environment variables (`.env`):**

    ```env
    # Gemini Configuration
    GEMINI_API_KEY=AIza...
    YT_COVER_GEN_GEMINI_MODEL=gemini-3.1-flash-image
    YT_COVER_GEN_GEMINI_ASPECT_RATIO=16:9
    YT_COVER_GEN_GEMINI_RESOLUTION=1K
    ```

### Generic PHP

For non-Laravel projects, you can use the `CoverGeneratorFactory` to instantiate generators directly.

## Usage

### Basic Usage (Laravel)

Inject the `CoverGeneratorInterface` into your class (Controller, Command, Job, etc.):

```php
use Artryazanov\YtCoverGen\Contracts\CoverGeneratorInterface;

class CreateThumbnail
{
    public function __construct(
        private CoverGeneratorInterface $generator
    ) {}

    public function handle()
    {
        $pathToScreenshot = '/path/to/screenshot.jpg';
        $gameName = 'Elden Ring';
        $videoTitle = 'NO HIT RUN PART 1';
        $gameCover = '/path/to/official_cover.png'; // Optional: for accurate logo generation

        // Returns absolute path to the generated image
        $coverPath = $this->generator->generate(
            $pathToScreenshot, 
            $gameName, 
            $videoTitle,
            $gameCover
        );
        
        echo "Thumbnail generated at: $coverPath";
    }
}
```

### Advanced Usage (Generic PHP / Custom Configuration)

You can use the Factory to create generators with specific configurations on the fly.

#### Google Gemini Example

Gemini requires PSR-18 HTTP Client dependencies (e.g., Guzzle).

```php
use Artryazanov\YtCoverGen\CoverGeneratorFactory;
use Artryazanov\YtCoverGen\Enums\GeminiImageModelEnum;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

$client = new Client();
$httpFactory = new HttpFactory();

$generator = CoverGeneratorFactory::createGemini(
    'your-gemini-api-key',
    $client,        // PSR-18 Client
    $httpFactory,   // PSR-17 Request Factory
    $httpFactory,   // PSR-17 Stream Factory
    '/path/to/output/dir',
    GeminiImageModelEnum::GEMINI_3_1_FLASH_IMAGE->value, // Optional custom image model
    \Artryazanov\YtCoverGen\Enums\GeminiTextModelEnum::GEMINI_3_1_PRO_PREVIEW->value, // Optional custom text model
    '16:9',         // Optional custom aspect ratio
    '1K'            // Optional custom resolution
);

$path = $generator->generate('screenshot.jpg', 'My Game', 'Awesome Video', 'cover.jpg');
```

## Supported Models

### Gemini Models
The package includes an Enum `Artryazanov\YtCoverGen\Enums\GeminiImageModelEnum`:
- `gemini-3.1-flash-image` (Default)
- `gemini-3-pro-image`

The package also includes an Enum `Artryazanov\YtCoverGen\Enums\GeminiTextModelEnum` for text generation:
- `gemini-3.1-pro-preview`

## Testing

Run the tests with:

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
