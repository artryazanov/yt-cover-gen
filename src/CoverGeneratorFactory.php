<?php

namespace Artryazanov\YtCoverGen;

use Artryazanov\YtCoverGen\Generators\GeminiCoverGenerator;
use Artryazanov\YtCoverGen\Generators\OpenAiCoverGenerator;
use Artryazanov\YtCoverGen\Support\ImageProcessor;
use OpenAI;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

class CoverGeneratorFactory
{
    public static function createOpenAi(string $apiKey, ?string $outputPath = null, ?string $model = null, ?string $size = null): OpenAiCoverGenerator
    {
        $client = OpenAI::client($apiKey);
        $imageProcessor = new ImageProcessor;

        return new OpenAiCoverGenerator($client, $imageProcessor, $outputPath ?? sys_get_temp_dir(), $model, $size);
    }

    public static function createGemini(
        string $apiKey,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        ?string $outputPath = null,
        ?string $model = null
    ): GeminiCoverGenerator {
        $imageProcessor = new ImageProcessor;

        return new GeminiCoverGenerator(
            $imageProcessor,
            $outputPath ?? sys_get_temp_dir(),
            $model,
            $httpClient,
            $requestFactory,
            $streamFactory,
            $apiKey
        );
    }
}
