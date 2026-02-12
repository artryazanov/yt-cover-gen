<?php

use Artryazanov\YtCoverGen\CoverGeneratorFactory;
use Artryazanov\YtCoverGen\Generators\GeminiCoverGenerator;
use Artryazanov\YtCoverGen\Generators\OpenAiCoverGenerator;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

it('creates OpenAi generator', function () {
    $generator = CoverGeneratorFactory::createOpenAi('fake-key');

    expect($generator)->toBeInstanceOf(OpenAiCoverGenerator::class);
});

it('creates Gemini generator', function () {
    $httpClient = Mockery::mock(ClientInterface::class);
    $requestFactory = Mockery::mock(RequestFactoryInterface::class);
    $streamFactory = Mockery::mock(StreamFactoryInterface::class);

    $generator = CoverGeneratorFactory::createGemini(
        'fake-key',
        $httpClient,
        $requestFactory,
        $streamFactory
    );

    expect($generator)->toBeInstanceOf(GeminiCoverGenerator::class);
});
