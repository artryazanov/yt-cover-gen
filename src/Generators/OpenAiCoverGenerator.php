<?php

namespace Artryazanov\YtCoverGen\Generators;

use Artryazanov\YtCoverGen\Contracts\CoverGeneratorInterface;
use Artryazanov\YtCoverGen\Enums\OpenAiModelEnum;
use Artryazanov\YtCoverGen\Enums\OpenAiQualityEnum;
use Artryazanov\YtCoverGen\Enums\OpenAiSizeEnum;
use Artryazanov\YtCoverGen\Support\ImageProcessor;
use OpenAI\Contracts\ClientContract;
use RuntimeException;

class OpenAiCoverGenerator implements CoverGeneratorInterface
{
    private const DEFAULT_IMAGE_SIZE = OpenAiSizeEnum::SIZE_1536x1024->value;

    private const DEFAULT_MODEL = OpenAiModelEnum::GPT_IMAGE_1_5->value;

    private ClientContract $client;

    private ImageProcessor $imageProcessor;

    private string $outputPath;

    private string $model;

    private string $size;

    private string $quality;

    public function __construct(
        ClientContract $client,
        ImageProcessor $imageProcessor,
        string $outputPath = '/tmp',
        ?string $model = null,
        ?string $size = null,
        ?string $quality = null
    ) {
        $this->client = $client;
        $this->imageProcessor = $imageProcessor;
        $this->outputPath = $outputPath;
        $this->model = $model ?? self::DEFAULT_MODEL;
        $this->size = $size ?? self::DEFAULT_IMAGE_SIZE;
        $this->quality = $quality ?: getenv('YT_COVER_GEN_OPENAI_QUALITY') ?: OpenAiQualityEnum::AUTO->value;
    }

    public function generate(string $imagePath, string $gameName, string $videoDescription): string
    {
        $prompt = $this->buildPrompt($gameName, $videoDescription);

        // Ensure image is locally available
        if (! file_exists($imagePath)) {
            throw new RuntimeException("Image file not found: $imagePath");
        }

        // Use a temporary local file handle for the request
        // The original implementation sends the file directly.
        // We assume the environment supports the format provided (e.g. JPEG) as per OpenAiAssistant reference.

        $response = $this->client->images()->edit([
            'model' => $this->model,
            'image' => fopen($imagePath, 'r'),
            'prompt' => $prompt,
            'n' => 1,
            'size' => $this->size,
            'output_format' => 'jpeg', // As seen in OpenAiAssistant
            'quality' => $this->quality,     // As seen in OpenAiAssistant
        ]);

        // The OpenAiAssistant assumes b64_json is returned even without response_format='b64_json'
        // likely due to specific gateway configuration or model behavior.
        $b64 = $response->data[0]->b64_json;
        $imageData = base64_decode($b64);

        return $this->imageProcessor->processAndSave($imageData, $this->outputPath, 'openai_'.time().'.jpg');
    }

    private function buildPrompt(string $gameName, string $videoDescription): string
    {
        // Truncate inputs to prevent prompt overflow (limit is 1000 chars)
        $gameNameShort = mb_substr($gameName, 0, 60);
        $descShort = mb_substr($videoDescription, 0, 300);

        $is360 = preg_match('/\b360\b|360°|panoram|панорам/ui', $descShort);

        $prompt = "Create a viral YouTube thumbnail for '$gameNameShort' from this screenshot.\n";
        $prompt .= "Style: Official '$gameNameShort' art style, vibrant, high contrast.\n";
        $prompt .= "Add Elements:\n";
        $prompt .= "1. HEADLINE: Condense '$descShort' into a short, punchy 2-5 word clickbaity text (Massive, Readable).\n";
        $prompt .= "2. LOGO: '$gameNameShort' logo in corner (Oversized, show ONCE).\n";

        if ($is360) {
            $prompt .= "3. BADGE: Prominent and recognizable '360° Video' logo so users immediately know it's panoramic.\n";
        }

        $prompt .= "Ensure text/logo do not cover main focal point.\n";
        $prompt .= "Resolution: {$this->size}.";

        return $prompt;
    }
}
