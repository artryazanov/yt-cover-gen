<?php

use Artryazanov\YtCoverGen\Contracts\CoverGeneratorInterface;
use Artryazanov\YtCoverGen\Generators\FallbackCoverGenerator;
use Artryazanov\YtCoverGen\Generators\GeminiCoverGenerator;
use Artryazanov\YtCoverGen\Generators\OpenAiCoverGenerator;
use Artryazanov\YtCoverGen\Integrations\Laravel\YtCoverGenServiceProvider;

beforeEach(function () {
    $this->provider = new YtCoverGenServiceProvider($this->app);
    $this->baseConfig = [
        'output_path' => '/tmp',
        'drivers' => [
            'openai' => [
                'api_key' => 'fake-openai-key',
            ],
            'gemini' => [
                'api_key' => 'fake-gemini-key',
            ]
        ]
    ];
});

it('registers primary openai driver without fallback', function () {
    $config = $this->baseConfig;
    $config['driver'] = 'openai';
    $config['drivers']['gemini']['api_key'] = null; // No fallback
    
    config(['yt-cover-gen' => $config]);
    $this->provider->register();
    
    $generator = $this->app->make(CoverGeneratorInterface::class);
    
    expect($generator)->toBeInstanceOf(OpenAiCoverGenerator::class);
});

it('registers primary openai driver with gemini fallback', function () {
    $config = $this->baseConfig;
    $config['driver'] = 'openai';
    
    config(['yt-cover-gen' => $config]);
    $this->provider->register();
    
    $generator = $this->app->make(CoverGeneratorInterface::class);
    
    expect($generator)->toBeInstanceOf(FallbackCoverGenerator::class);
    
    $reflection = new ReflectionClass($generator);
    $primaryProperty = $reflection->getProperty('primary');
    $primaryProperty->setAccessible(true);
    
    $fallbackProperty = $reflection->getProperty('fallback');
    $fallbackProperty->setAccessible(true);
    
    expect($primaryProperty->getValue($generator))->toBeInstanceOf(OpenAiCoverGenerator::class);
    expect($fallbackProperty->getValue($generator))->toBeInstanceOf(GeminiCoverGenerator::class);
});

it('registers primary gemini driver without fallback', function () {
    $config = $this->baseConfig;
    $config['driver'] = 'gemini';
    $config['drivers']['openai']['api_key'] = null; // No fallback
    
    config(['yt-cover-gen' => $config]);
    $this->provider->register();
    
    $generator = $this->app->make(CoverGeneratorInterface::class);
    
    expect($generator)->toBeInstanceOf(GeminiCoverGenerator::class);
});

it('registers primary gemini driver with openai fallback', function () {
    $config = $this->baseConfig;
    $config['driver'] = 'gemini';
    
    config(['yt-cover-gen' => $config]);
    $this->provider->register();
    
    $generator = $this->app->make(CoverGeneratorInterface::class);
    
    expect($generator)->toBeInstanceOf(FallbackCoverGenerator::class);
    
    $reflection = new ReflectionClass($generator);
    $primaryProperty = $reflection->getProperty('primary');
    $primaryProperty->setAccessible(true);
    
    $fallbackProperty = $reflection->getProperty('fallback');
    $fallbackProperty->setAccessible(true);
    
    expect($primaryProperty->getValue($generator))->toBeInstanceOf(GeminiCoverGenerator::class);
    expect($fallbackProperty->getValue($generator))->toBeInstanceOf(OpenAiCoverGenerator::class);
});

it('throws exception for unknown driver', function () {
    $config = $this->baseConfig;
    $config['driver'] = 'unknown';
    
    config(['yt-cover-gen' => $config]);
    $this->provider->register();
    
    $this->app->make(CoverGeneratorInterface::class);
})->throws(\RuntimeException::class, 'Unknown driver: unknown');
