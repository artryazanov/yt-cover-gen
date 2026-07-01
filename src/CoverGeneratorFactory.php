<?php

namespace Artryazanov\YtCoverGen;

use Artryazanov\YtCoverGen\Generators\GeminiCoverGenerator;
use Artryazanov\YtCoverGen\Support\ImageProcessor;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

class CoverGeneratorFactory
{
    public static function createGemini(
        string $apiKey,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        ?string $outputPath = null,
        ?string $model = null,
        ?string $textModel = null,
        ?string $aspectRatio = null,
        ?string $resolution = null
    ): GeminiCoverGenerator {
        $imageProcessor = new ImageProcessor;

        return new GeminiCoverGenerator(
            $imageProcessor,
            $outputPath ?? sys_get_temp_dir(),
            $model,
            $textModel,
            $httpClient,
            $requestFactory,
            $streamFactory,
            $apiKey,
            $aspectRatio,
            $resolution
        );
    }
}
