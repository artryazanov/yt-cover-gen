<?php

namespace Artryazanov\YtCoverGen\Integrations\Laravel;

use Artryazanov\YtCoverGen\Contracts\CoverGeneratorInterface;
use Artryazanov\YtCoverGen\CoverGeneratorFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Support\ServiceProvider;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

class YtCoverGenServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../../config/yt-cover-gen.php' => config_path('yt-cover-gen.php'),
        ], 'yt-cover-gen-config');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../../config/yt-cover-gen.php', 'yt-cover-gen'
        );

        $this->app->bind(CoverGeneratorInterface::class, function ($app) {
            $config = $app['config']['yt-cover-gen'];
            $outputPath = $config['output_path'] ?? storage_path('app/public/covers');

            $httpClient = $app->bound(ClientInterface::class) ? $app->make(ClientInterface::class) : new Client;
            $requestFactory = $app->bound(RequestFactoryInterface::class) ? $app->make(RequestFactoryInterface::class) : new HttpFactory;
            $streamFactory = $app->bound(StreamFactoryInterface::class) ? $app->make(StreamFactoryInterface::class) : new HttpFactory;

            return CoverGeneratorFactory::createGemini(
                $config['drivers']['gemini']['api_key'],
                $httpClient,
                $requestFactory,
                $streamFactory,
                $outputPath,
                $config['drivers']['gemini']['model'] ?? null,
                $config['drivers']['gemini']['text_model'] ?? null,
                $config['drivers']['gemini']['aspect_ratio'] ?? null,
                $config['drivers']['gemini']['resolution'] ?? null
            );
        });
    }
}
