<?php

namespace Artryazanov\YtCoverGen\Generators;

use Artryazanov\YtCoverGen\Contracts\CoverGeneratorInterface;
use Artryazanov\YtCoverGen\Enums\OpenAiImageModelEnum;
use Artryazanov\YtCoverGen\Enums\OpenAiTextModelEnum;
use Artryazanov\YtCoverGen\Enums\OpenAiQualityEnum;
use Artryazanov\YtCoverGen\Enums\OpenAiSizeEnum;
use Artryazanov\YtCoverGen\Support\ImageProcessor;
use OpenAI\Contracts\ClientContract;
use RuntimeException;

class OpenAiCoverGenerator implements CoverGeneratorInterface
{
    private const DEFAULT_IMAGE_SIZE = OpenAiSizeEnum::SIZE_1536x1024->value;

    private const DEFAULT_MODEL = OpenAiImageModelEnum::GPT_IMAGE_2->value;

    private ClientContract $client;

    private ImageProcessor $imageProcessor;

    private string $outputPath;

    private string $model;

    private string $textModel;

    private string $size;

    private string $quality;

    public function __construct(
        ClientContract $client,
        ImageProcessor $imageProcessor,
        string $outputPath = '/tmp',
        ?string $model = null,
        ?string $textModel = null,
        ?string $size = null,
        ?string $quality = null
    ) {
        $this->client = $client;
        $this->imageProcessor = $imageProcessor;
        $this->outputPath = $outputPath;
        $this->model = $model ?? self::DEFAULT_MODEL;
        $this->textModel = $textModel ?? OpenAiTextModelEnum::GPT_5_5->value;
        $this->size = $size ?? self::DEFAULT_IMAGE_SIZE;
        $this->quality = $quality ?: getenv('YT_COVER_GEN_OPENAI_QUALITY') ?: OpenAiQualityEnum::AUTO->value;
    }

    public function generate(string $imagePath, string $gameName, string $videoDescription): string
    {
        if (! file_exists($imagePath)) {
            throw new RuntimeException("Image file not found: $imagePath");
        }

        $clickbaitTitle = $this->generateClickbaitTitle($gameName, $videoDescription);
        $prompt = $this->buildPrompt($gameName, $clickbaitTitle);

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

    private function generateClickbaitTitle(string $gameName, string $videoDescription): string
    {
        $response = $this->client->chat()->create([
            'model' => $this->textModel,
            'messages' => [
                [
                    'role' => 'system', 
                    'content' => 'You are an expert YouTube strategist. Your task is to generate a short, highly enticing, clickbait thumbnail title (maximum 5 words) based on the user\'s video description. CRITICAL NEGATIVE CONSTRAINT: Do NOT invent, promise, or mention any features, items, characters, or events that are not explicitly present in the description. The clickbait must be 100% truthful.'
                ],
                [
                    'role' => 'user', 
                    'content' => "Game: $gameName\nDescription: $videoDescription"
                ],
            ],
            'max_tokens' => 20,
            'temperature' => 0.7,
        ]);

        $title = $response->choices[0]->message->content ?? $videoDescription;
        return trim(trim($title, '"\' '));
    }

    private function buildPrompt(string $gameName, string $generatedTitle): string
    {
        // Truncate inputs to prevent prompt overflow (limit is 1000 chars)
        $gameNameShort = mb_substr($gameName, 0, 60);
        $titleShort = mb_substr($generatedTitle, 0, 150);

        $is360 = preg_match('/\b360\b|360°|panoram|панорам/ui', $generatedTitle);

        $prompt = "Create a viral YouTube thumbnail for '$gameNameShort' from this screenshot.\n";
        $prompt .= "Style: Official '$gameNameShort' art style, vibrant, high contrast.\n";
        $prompt .= "Add Elements:\n";
        $prompt .= "1. HEADLINE: Render EXACTLY this text: '$titleShort'. Do not change a single letter. Make it Massive and Readable.\n";
        $prompt .= "2. LOGO: '$gameNameShort' logo in corner (Oversized, show ONCE).\n";
        
        if ($is360) {
            $prompt .= "3. BADGE: Prominent and recognizable '360° Video' logo so users immediately know it's panoramic.\n";
        }

        $prompt .= "Ensure text/logo do not cover main focal point.\n";
        $prompt .= "Resolution: {$this->size}.";

        return $prompt;
    }
}
