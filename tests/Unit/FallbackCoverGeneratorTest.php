<?php

use Artryazanov\YtCoverGen\Contracts\CoverGeneratorInterface;
use Artryazanov\YtCoverGen\Exceptions\GeminiResponseException;
use Artryazanov\YtCoverGen\Generators\FallbackCoverGenerator;

it('generates cover using primary generator', function () {
    $primary = Mockery::mock(CoverGeneratorInterface::class);
    $primary->shouldReceive('generate')->once()->with('img.jpg', 'Game', 'Desc', null)->andReturn('primary.jpg');

    $fallback = Mockery::mock(CoverGeneratorInterface::class);
    $fallback->shouldNotReceive('generate');

    $generator = new FallbackCoverGenerator($primary, $fallback);
    $result = $generator->generate('img.jpg', 'Game', 'Desc');

    expect($result)->toBe('primary.jpg');
});

it('falls back if primary fails with GeminiResponseException', function () {
    $primary = Mockery::mock(CoverGeneratorInterface::class);
    $primary->shouldReceive('generate')->once()->andThrow(new GeminiResponseException('Blocked'));

    $fallback = Mockery::mock(CoverGeneratorInterface::class);
    $fallback->shouldReceive('generate')->once()->with('img.jpg', 'Game', 'Desc', null)->andReturn('fallback.jpg');

    $generator = new FallbackCoverGenerator($primary, $fallback);
    $result = $generator->generate('img.jpg', 'Game', 'Desc');

    expect($result)->toBe('fallback.jpg');
});

it('falls back if primary fails with generic Exception', function () {
    $primary = Mockery::mock(CoverGeneratorInterface::class);
    $primary->shouldReceive('generate')->once()->andThrow(new RuntimeException('Error'));

    $fallback = Mockery::mock(CoverGeneratorInterface::class);
    $fallback->shouldReceive('generate')->once()->with('img.jpg', 'Game', 'Desc', null)->andReturn('fallback.jpg');

    $generator = new FallbackCoverGenerator($primary, $fallback);
    $result = $generator->generate('img.jpg', 'Game', 'Desc');

    expect($result)->toBe('fallback.jpg');
});

it('calls error handler if primary fails', function () {
    $primary = Mockery::mock(CoverGeneratorInterface::class);
    $primary->shouldReceive('generate')->once()->andThrow(new RuntimeException('Error'));

    $fallback = Mockery::mock(CoverGeneratorInterface::class);
    $fallback->shouldReceive('generate')->once()->andReturn('fallback.jpg');

    $handled = false;
    $errorHandler = function (Throwable $e) use (&$handled) {
        $handled = true;
    };

    $generator = new FallbackCoverGenerator($primary, $fallback, $errorHandler);
    $generator->generate('img.jpg', 'Game', 'Desc');

    expect($handled)->toBeTrue();
});

it('falls back even if error handler throws exception on Gemini exception', function () {
    $primary = Mockery::mock(CoverGeneratorInterface::class);
    $primary->shouldReceive('generate')->once()->andThrow(new GeminiResponseException('Blocked'));

    $fallback = Mockery::mock(CoverGeneratorInterface::class);
    $fallback->shouldReceive('generate')->once()->andReturn('fallback.jpg');

    $errorHandler = function (Throwable $e) {
        throw new RuntimeException('Handler failed');
    };

    $generator = new FallbackCoverGenerator($primary, $fallback, $errorHandler);
    $result = $generator->generate('img.jpg', 'Game', 'Desc');

    expect($result)->toBe('fallback.jpg');
});

it('throws exception if error handler throws on generic exception', function () {
    $primary = Mockery::mock(CoverGeneratorInterface::class);
    $primary->shouldReceive('generate')->once()->andThrow(new RuntimeException('Error'));

    $fallback = Mockery::mock(CoverGeneratorInterface::class);
    $fallback->shouldNotReceive('generate');

    $errorHandler = function (Throwable $e) {
        throw new RuntimeException('Handler failed');
    };

    $generator = new FallbackCoverGenerator($primary, $fallback, $errorHandler);
    $generator->generate('img.jpg', 'Game', 'Desc');
})->throws(RuntimeException::class, 'Handler failed');
