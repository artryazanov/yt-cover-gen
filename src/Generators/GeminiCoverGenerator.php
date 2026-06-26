<?php

namespace Artryazanov\YtCoverGen\Generators;

use Artryazanov\YtCoverGen\Enums\GeminiAspectRatioEnum;
use Artryazanov\YtCoverGen\Enums\GeminiImageModelEnum;
use Artryazanov\YtCoverGen\Enums\GeminiResolutionEnum;
use Artryazanov\YtCoverGen\Enums\GeminiTextModelEnum;
use Artryazanov\YtCoverGen\Exceptions\GeminiResponseException;
use Artryazanov\YtCoverGen\Support\ImageProcessor;
use RuntimeException;

class GeminiCoverGenerator extends AbstractCoverGenerator
{
    private const DEFAULT_MODEL = GeminiImageModelEnum::GEMINI_3_1_FLASH_IMAGE->value;

    private $httpClient;

    private $requestFactory;

    private $streamFactory;

    private ?string $apiKey;

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
        parent::__construct(
            $imageProcessor,
            $outputPath,
            $model ?? self::DEFAULT_MODEL,
            $textModel ?? GeminiTextModelEnum::GEMINI_3_1_PRO_PREVIEW->value
        );

        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->apiKey = $apiKey;
        $this->aspectRatio = $aspectRatio ?: getenv('YT_COVER_GEN_GEMINI_ASPECT_RATIO') ?: GeminiAspectRatioEnum::RATIO_16_9->value;
        $this->resolution = $resolution ?: getenv('YT_COVER_GEN_GEMINI_RESOLUTION') ?: GeminiResolutionEnum::RES_1K->value;
    }

    protected function doGenerate(string $imagePath, string $prompt, ?string $gameCoverPath = null): string
    {
        if (! $this->httpClient || ! $this->apiKey) {
            throw new RuntimeException('PSR Client and API Key required for Gemini models.');
        }

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

        $parts = [
            [
                'text' => $prompt,
            ],
            [
                'inline_data' => [
                    'mime_type' => $mimeType,
                    'data' => $imageBase64,
                ],
            ],
        ];

        if ($gameCoverPath && file_exists($gameCoverPath)) {
            $coverBase64 = $this->imageProcessor->imageToBase64($gameCoverPath);
            $coverMimeType = 'image/jpeg';
            $coverExtension = strtolower(pathinfo($gameCoverPath, PATHINFO_EXTENSION));
            if ($coverExtension === 'png') {
                $coverMimeType = 'image/png';
            } elseif ($coverExtension === 'gif') {
                $coverMimeType = 'image/gif';
            } elseif ($coverExtension === 'webp') {
                $coverMimeType = 'image/webp';
            }

            $parts[] = [
                'text' => 'Here is the official game cover image. Use the logo from this second image as an EXACT reference for drawing the game logo in the thumbnail:',
            ];
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $coverMimeType,
                    'data' => $coverBase64,
                ],
            ];
        }

        $payload = [
            'contents' => [
                [
                    'parts' => $parts,
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

    protected function generateShortTitle(string $gameName, string $videoDescription): string
    {
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $this->getShortTitleSystemPrompt($gameName, $videoDescription),
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.7,
            ],
        ];

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->textModel}:generateContent?key={$this->apiKey}";

        if (! $this->httpClient || ! $this->apiKey) {
            return $videoDescription;
        }

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

    protected function buildPrompt(string $gameName, string $generatedTitle, string $originalDescription, ?string $gameCoverPath = null): string
    {
        $is360 = preg_match('/\b360\b|360°|panoram|панорам/ui', $originalDescription);

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

        if ($gameCoverPath) {
            $prompt .= "       IMPORTANT: I have provided a second image which is the official game cover. Use the logo from that second image as the EXACT reference for drawing the logo.\r\n";
        }

        if ($is360) {
            $prompt .= "    3. BADGE: Add a prominent and recognizable '360° Video' logo so users immediately know it's a panoramic video.\r\n";
        }

        $prompt .= "    NEGATIVE CONSTRAINT: Do NOT duplicate the logo. Do NOT write the game name as plain text separate from the logo. Show the logo EXACTLY ONCE.\r\n";
        $prompt .= "    HEADLINE & LOGO should be maximum readability against the background.\r\n";
        $prompt .= "    Place HEADLINE & LOGO strategically so they don't cover the main focal point.\r\n";

        return $prompt;
    }

    protected function generateCleanLogo(string $originalLogoPath, string $cachePath): string
    {
        if (! $this->httpClient || ! $this->apiKey) {
            throw new RuntimeException('PSR Client and API Key required for Gemini models.');
        }

        $imageBase64 = $this->imageProcessor->imageToBase64($originalLogoPath);
        $mimeType = $this->imageProcessor->getMimeType($originalLogoPath);

        $prompt = "You are an expert graphic designer. Your task is to completely isolate and recreate ONLY the main game logo/typography from the provided image.\n";
        $prompt .= "1. Draw ONLY the typography/logo. Make it large and perfectly centered.\n";
        $prompt .= "2. Background MUST be solid black. NO gradients, NO scenes.\n";
        $prompt .= "3. NO characters, NO faces, NO background scenery, NO secondary text.\n";
        $prompt .= '4. Crop closely to the logo so there is no unnecessary excess background.';

        $parts = [
            [
                'text' => $prompt,
            ],
            [
                'inline_data' => [
                    'mime_type' => $mimeType,
                    'data' => $imageBase64,
                ],
            ],
        ];

        $payload = [
            'contents' => [
                [
                    'parts' => $parts,
                ],
            ],
            'generationConfig' => [
                'responseModalities' => ['IMAGE'],
                'imageConfig' => [
                    'aspectRatio' => '16:9',
                ],
            ],
        ];

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $request = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Content-Type', 'application/json');

        $body = $this->streamFactory->createStream(json_encode($payload));
        $request = $request->withBody($body);

        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw new GeminiResponseException('Gemini API Error (Logo Extraction): '.$response->getBody()->getContents());
        }

        $json = json_decode($response->getBody()->getContents(), true);

        foreach ($json['candidates'][0]['content']['parts'] ?? [] as $part) {
            if (isset($part['inlineData']['data'])) {
                $imageData = base64_decode($part['inlineData']['data']);

                $dir = dirname($cachePath);
                $filename = basename($cachePath);

                return $this->imageProcessor->saveRawImage($imageData, $dir, $filename);
            }
        }

        throw new GeminiResponseException('No image found in Gemini Beta response during logo extraction. Response: '.json_encode($json));
    }
}
