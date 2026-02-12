<?php

namespace Artryazanov\YtCoverGen\Integrations\Laravel;

use Artryazanov\YtCoverGen\Contracts\CoverGeneratorInterface;
use Artryazanov\YtCoverGen\CoverGeneratorFactory;
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
            $driver = $config['driver'] ?? 'openai';
            $outputPath = $config['output_path'] ?? storage_path('app/public/covers');

            // Helper to create OpenAI Generator
            $createOpenAi = function () use ($config, $outputPath) {
                return CoverGeneratorFactory::createOpenAi(
                    $config['drivers']['openai']['api_key'],
                    $outputPath,
                    $config['drivers']['openai']['model'] ?? null,
                    $config['drivers']['openai']['size'] ?? null
                );
            };

            // Helper to create Gemini Generator
            $createGemini = function () use ($app, $config, $outputPath) {
                $httpClient = $app->bound(ClientInterface::class) ? $app->make(ClientInterface::class) : new \GuzzleHttp\Client;
                $requestFactory = $app->bound(RequestFactoryInterface::class) ? $app->make(RequestFactoryInterface::class) : new \GuzzleHttp\Psr7\HttpFactory;
                $streamFactory = $app->bound(StreamFactoryInterface::class) ? $app->make(StreamFactoryInterface::class) : new \GuzzleHttp\Psr7\HttpFactory;

                return CoverGeneratorFactory::createGemini(
                    $config['drivers']['gemini']['api_key'],
                    $httpClient,
                    $requestFactory,
                    $streamFactory,
                    $outputPath,
                    $config['drivers']['gemini']['model'] ?? null
                );
            };

            $errorHandler = function (\Throwable $e) {
                // Use global helper if available, or just log
                if (function_exists('report')) {
                    report($e);
                }
            };

            if ($driver === 'openai') {
                $primary = $createOpenAi();

                // Check if Fallback (Gemini) is possible
                if (! empty($config['drivers']['gemini']['api_key'])) {
                    $secondary = $createGemini();

                    return new \Artryazanov\YtCoverGen\Generators\FallbackCoverGenerator($primary, $secondary, $errorHandler);
                }

                return $primary;
            }

            if ($driver === 'gemini') {
                $primary = $createGemini();

                // Check if Fallback (OpenAI) is possible
                if (! empty($config['drivers']['openai']['api_key'])) {
                    $secondary = $createOpenAi();

                    return new \Artryazanov\YtCoverGen\Generators\FallbackCoverGenerator($primary, $secondary, $errorHandler);
                }

                return $primary;
            }

            throw new \RuntimeException("Unknown driver: $driver");
        });
    }
}
