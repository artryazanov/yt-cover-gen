<?php

use Artryazanov\YtCoverGen\Contracts\CoverGeneratorInterface;
use Artryazanov\YtCoverGen\Generators\GeminiCoverGenerator;
use Artryazanov\YtCoverGen\Integrations\Laravel\YtCoverGenServiceProvider;

beforeEach(function () {
    $this->baseConfig = [
        'output_path' => '/tmp',
        'drivers' => [
            'gemini' => [
                'api_key' => 'fake-gemini-key',
            ],
        ],
    ];
});

it('registers primary gemini driver', function () {
    config(['yt-cover-gen' => $this->baseConfig]);
    $this->app->register(YtCoverGenServiceProvider::class);

    $generator = $this->app->make(CoverGeneratorInterface::class);

    expect($generator)->toBeInstanceOf(GeminiCoverGenerator::class);
});
