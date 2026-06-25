<?php

namespace Artryazanov\YtCoverGen\Generators;

use Artryazanov\YtCoverGen\Contracts\CoverGeneratorInterface;
use Artryazanov\YtCoverGen\Enums\GeminiAspectRatioEnum;
use Artryazanov\YtCoverGen\Enums\GeminiImageModelEnum;
use Artryazanov\YtCoverGen\Enums\GeminiTextModelEnum;
use Artryazanov\YtCoverGen\Enums\GeminiResolutionEnum;
use Artryazanov\YtCoverGen\Exceptions\GeminiResponseException;
use Artryazanov\YtCoverGen\Support\ImageProcessor;
use RuntimeException;

class GeminiCoverGenerator implements CoverGeneratorInterface
{
    private const DEFAULT_MODEL = GeminiImageModelEnum::GEMINI_3_1_FLASH_IMAGE->value;

    private $httpClient;

    private $requestFactory;

    private $streamFactory;

    private ?string $apiKey;

    private ImageProcessor $imageProcessor;

    private string $outputPath;

    private string $model;

    private string $textModel;

    private string $aspectRatio;

    private string $resolution;

    public function __construct(
        ImageProcessor $imageProcessor,
        string $outputPath = '/tmp',
        ?string $model = null,
        ?string $textModel = null,
        $httpClient = null, // PSR Client
        $requestFactory = null, // PSR RequestFactory
        $streamFactory = null, // PSR StreamFactory
        ?string $apiKey = null,
        ?string $aspectRatio = null,
        ?string $resolution = null
    ) {

        $this->imageProcessor = $imageProcessor;
        $this->outputPath = $outputPath;
        $this->model = $model ?? self::DEFAULT_MODEL;
        $this->textModel = $textModel ?? GeminiTextModelEnum::GEMINI_3_1_PRO_PREVIEW->value;
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->apiKey = $apiKey;
        $this->aspectRatio = $aspectRatio ?: getenv('YT_COVER_GEN_GEMINI_ASPECT_RATIO') ?: GeminiAspectRatioEnum::RATIO_16_9->value;
        $this->resolution = $resolution ?: getenv('YT_COVER_GEN_GEMINI_RESOLUTION') ?: GeminiResolutionEnum::RES_1K->value;
    }

    public function generate(string $imagePath, string $gameName, string $videoDescription): string
    {
        if (! file_exists($imagePath)) {
            throw new RuntimeException("Image file not found: $imagePath");
        }

        if (! $this->httpClient || ! $this->apiKey) {
            throw new RuntimeException('PSR Client and API Key required for Gemini models.');
        }

        $clickbaitTitle = $this->generateClickbaitTitle($gameName, $videoDescription);
        $prompt = $this->buildPrompt($gameName, $clickbaitTitle);

        return $this->generateWithRawBetaApi($imagePath, $prompt);
    }

    private function generateWithRawBetaApi(string $imagePath, string $prompt): string
    {
        $imageBase64 = $this->imageProcessor->imageToBase64($imagePath);

        // Strict match of ExtendedGeminiClient logic
        $mimeType = 'image/jpeg'; // Default
        $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
        if ($extension === 'png') {
            $mimeType = 'image/png';
        } elseif ($extension === 'gif') {
            $mimeType = 'image/gif';
        } elseif ($extension === 'webp') {
            $mimeType = 'image/webp';
        }

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $prompt,
                        ],
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => $imageBase64,
                            ],
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'responseModalities' => ['TEXT', 'IMAGE'],
                'imageConfig' => [
                    'aspectRatio' => $this->aspectRatio,
                    'imageSize' => $this->resolution,
                ],
            ],
            'safetySettings' => [
                [
                    'category' => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_NONE',
                ],
                [
                    'category' => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_NONE',
                ],
                [
                    'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                    'threshold' => 'BLOCK_NONE',
                ],
                [
                    'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                    'threshold' => 'BLOCK_NONE',
                ],
            ],
        ];

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $request = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Content-Type', 'application/json');
        // ExtendedGeminiClient::editImage does NOT add x-goog-api-key header, it uses query param only.

        $body = $this->streamFactory->createStream(json_encode($payload));
        $request = $request->withBody($body);

        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw new GeminiResponseException('Gemini API Error: '.$response->getBody()->getContents());
        }

        $json = json_decode($response->getBody()->getContents(), true);

        // Extract image data manually
        foreach ($json['candidates'][0]['content']['parts'] ?? [] as $part) {
            if (isset($part['inlineData']['data'])) {
                $imageData = base64_decode($part['inlineData']['data']);

                return $this->imageProcessor->processAndSave($imageData, $this->outputPath, 'gemini_beta_'.time().'.jpg');
            }
        }

        throw new GeminiResponseException('No image found in Gemini Beta response. Response: '.json_encode($json));
    }

    private function generateClickbaitTitle(string $gameName, string $videoDescription): string
    {
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => "You are an expert YouTube strategist. Your task is to generate a short, highly enticing, clickbait thumbnail title (maximum 5 words) based on the user's video description.\nCRITICAL NEGATIVE CONSTRAINT: Do NOT invent, promise, or mention any features, items, characters, or events that are not explicitly present in the description. The clickbait must be 100% truthful.\n\nGame: $gameName\nDescription: $videoDescription",
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 20,
            ],
        ];

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->textModel}:generateContent?key={$this->apiKey}";

        $request = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Content-Type', 'application/json');

        $body = $this->streamFactory->createStream(json_encode($payload));
        $request = $request->withBody($body);

        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            return $videoDescription;
        }

        $json = json_decode($response->getBody()->getContents(), true);
        $title = $json['candidates'][0]['content']['parts'][0]['text'] ?? $videoDescription;
        
        return trim(trim($title, '"\' '));
    }

    private function buildPrompt(string $gameName, string $generatedTitle): string
    {
        $is360 = preg_match('/\b360\b|360°|panoram|панорам/ui', $generatedTitle);

        $prompt = "Act as a world-class YouTube thumbnail designer.\r\n";
        $prompt .= "Task: Create a viral, high-click-through-rate (CTR) thumbnail based on the attached gameplay screenshot.\r\n";
        $prompt .= "The game is \"$gameName\".\r\n";
        $prompt .= "Visual Style & Composition:\r\n";
        $prompt .= "    Art Direction: Strictly adhere to the official art style of \"$gameName\".";
        $prompt .= "    Subject: Enhance the main character or focal point on the screenshot.\r\n";
        $prompt .= "    Color: Vibrant and high-contrast, but strictly within the game's official color palette.\r\n";
        $prompt .= "Text & Branding:\r\n";
        $prompt .= "    1. HEADLINE: Render EXACTLY this text: \"$generatedTitle\". Do not change a single letter. Make the text MASSIVE and DOMINANT.\r\n";
        $prompt .= "    2. LOGO: Integrate the official \"$gameName\" logo in one corner. Make the logo OVERSIZED.\r\n";

        if ($is360) {
            $prompt .= "    3. BADGE: Add a prominent and recognizable '360° Video' logo so users immediately know it's a panoramic video.\r\n";
        }

        $prompt .= "    NEGATIVE CONSTRAINT: Do NOT duplicate the logo. Do NOT write the game name as plain text separate from the logo. Show the logo EXACTLY ONCE.\r\n";
        $prompt .= "    HEADLINE & LOGO should be maximum readability against the background.\r\n";
        $prompt .= "    Place HEADLINE & LOGO strategically so they don't cover the main focal point.\r\n";

        return $prompt;
    }
}
