<?php

use Artryazanov\YtCoverGen\Generators\GeminiCoverGenerator;
use Artryazanov\YtCoverGen\Support\ImageProcessor;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

beforeEach(function () {
    $this->httpClient = Mockery::mock(ClientInterface::class);
    $this->requestFactory = Mockery::mock(RequestFactoryInterface::class);
    $this->streamFactory = Mockery::mock(StreamFactoryInterface::class);
    $this->imageProcessor = new ImageProcessor;

    $this->tempDir = sys_get_temp_dir().'/yt_cover_gen_tests_gemini_'.uniqid();
    mkdir($this->tempDir);

    $this->dummyImage = $this->tempDir.'/input.jpg';
    $img = imagecreatetruecolor(50, 50);
    imagejpeg($img, $this->dummyImage);
    imagedestroy($img);
});

afterEach(function () {
    if (is_dir($this->tempDir.'/logos')) {
        array_map('unlink', glob($this->tempDir.'/logos/*'));
        rmdir($this->tempDir.'/logos');
    }
    $files = glob($this->tempDir.'/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir($this->tempDir);
});

it('throws exception if image missing', function () {
    $generator = new GeminiCoverGenerator(
        $this->imageProcessor,
        $this->tempDir,
        null,
        null, // textModel
        $this->httpClient,
        $this->requestFactory,
        $this->streamFactory,
        'key'
    );
    $generator->generate('missing.jpg', 'G', 'D');
})->throws(RuntimeException::class, 'Image file not found');

it('generates cover using Gemini Beta API', function () {
    $generator = new GeminiCoverGenerator(
        $this->imageProcessor,
        $this->tempDir,
        'gemini-3.1-flash-image',
        'gemini-3.1-pro-preview',
        $this->httpClient,
        $this->requestFactory,
        $this->streamFactory,
        'test-api-key'
    );

    // Prepare Request Mock
    $request = Mockery::mock(RequestInterface::class);
    $request->shouldReceive('withHeader')->with('Content-Type', 'application/json')->andReturnSelf();
    $request->shouldReceive('withBody')->andReturnSelf();

    $this->requestFactory->shouldReceive('createRequest')
        ->with('POST', Mockery::pattern('/gemini-.+:generateContent/'))
        ->andReturn($request);

    $this->streamFactory->shouldReceive('createStream')->andReturn(Mockery::mock(StreamInterface::class));

    // Prepare Response Mock
    // Create valid image data
    $img = imagecreatetruecolor(10, 10);
    ob_start();
    imagejpeg($img);
    $realImageData = ob_get_clean();
    imagedestroy($img);
    $b64 = base64_encode($realImageData);

    $jsonResponse = json_encode([
        'candidates' => [
            [
                'content' => [
                    'parts' => [
                        [
                            'inlineData' => [
                                'mime_type' => 'image/jpeg',
                                'data' => $b64,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $responseBody = Mockery::mock(StreamInterface::class);
    $responseBody->shouldReceive('getContents')->andReturn($jsonResponse);

    $response = Mockery::mock(ResponseInterface::class);
    $response->shouldReceive('getStatusCode')->andReturn(200);
    $response->shouldReceive('getBody')->andReturn($responseBody);

    // Text response mock
    $textJsonResponse = json_encode([
        'candidates' => [
            [
                'content' => [
                    'parts' => [
                        ['text' => 'This is a very long description'],
                    ],
                ],
            ],
        ],
    ]);

    $textResponseBody = Mockery::mock(StreamInterface::class);
    $textResponseBody->shouldReceive('getContents')->andReturn($textJsonResponse);

    $textResponse = Mockery::mock(ResponseInterface::class);
    $textResponse->shouldReceive('getStatusCode')->andReturn(200);
    $textResponse->shouldReceive('getBody')->andReturn($textResponseBody);

    $this->httpClient->shouldReceive('sendRequest')
        ->twice()
        ->andReturn($textResponse, $response);

    $path = $generator->generate($this->dummyImage, 'GameName', 'This is a very long description');

    expect(file_exists($path))->toBeTrue();
});

it('generates cover using Gemini Beta API and includes 360 badge prompt when requested', function () {
    $generator = new GeminiCoverGenerator(
        $this->imageProcessor,
        $this->tempDir,
        'gemini-3.1-flash-image',
        'gemini-3.1-pro-preview',
        $this->httpClient,
        $this->requestFactory,
        $this->streamFactory,
        'test-api-key'
    );

    // Prepare Request Mock
    $request = Mockery::mock(RequestInterface::class);
    $request->shouldReceive('withHeader')->with('Content-Type', 'application/json')->andReturnSelf();
    $request->shouldReceive('withBody')->andReturnSelf();

    $this->requestFactory->shouldReceive('createRequest')
        ->with('POST', Mockery::pattern('/gemini-.+:generateContent/'))
        ->andReturn($request);

    $this->streamFactory->shouldReceive('createStream')->andReturn(Mockery::mock(StreamInterface::class));

    // Prepare Response Mock
    // Create valid image data
    $img = imagecreatetruecolor(10, 10);
    ob_start();
    imagejpeg($img);
    $realImageData = ob_get_clean();
    imagedestroy($img);
    $b64 = base64_encode($realImageData);

    $jsonResponse = json_encode([
        'candidates' => [
            [
                'content' => [
                    'parts' => [
                        [
                            'inlineData' => [
                                'mime_type' => 'image/jpeg',
                                'data' => $b64,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $responseBody = Mockery::mock(StreamInterface::class);
    $responseBody->shouldReceive('getContents')->andReturn($jsonResponse);

    $response = Mockery::mock(ResponseInterface::class);
    $response->shouldReceive('getStatusCode')->andReturn(200);
    $response->shouldReceive('getBody')->andReturn($responseBody);

    // Text response mock
    $textJsonResponse = json_encode([
        'candidates' => [
            [
                'content' => [
                    'parts' => [
                        ['text' => 'Awesome 360 video with a lot of words'],
                    ],
                ],
            ],
        ],
    ]);

    $textResponseBody = Mockery::mock(StreamInterface::class);
    $textResponseBody->shouldReceive('getContents')->andReturn($textJsonResponse);

    $textResponse = Mockery::mock(ResponseInterface::class);
    $textResponse->shouldReceive('getStatusCode')->andReturn(200);
    $textResponse->shouldReceive('getBody')->andReturn($textResponseBody);

    $this->httpClient->shouldReceive('sendRequest')
        ->twice()
        ->andReturn($textResponse, $response);

    $path = $generator->generate($this->dummyImage, 'GameName', 'Awesome 360 video with a lot of words');

    expect(file_exists($path))->toBeTrue();
});

it('throws exception if client or api key is missing', function () {
    $generator = new GeminiCoverGenerator(
        $this->imageProcessor,
        $this->tempDir,
        null,
        null, // textModel
        null, // httpClient
        $this->requestFactory,
        $this->streamFactory,
        null // api key
    );
    $generator->generate($this->dummyImage, 'G', 'D');
})->throws(RuntimeException::class, 'PSR Client and API Key required for Gemini models.');

it('generates cover using Gemini Beta API with game cover reference', function () {
    $generator = new GeminiCoverGenerator(
        $this->imageProcessor,
        $this->tempDir,
        null,
        null,
        $this->httpClient,
        $this->requestFactory,
        $this->streamFactory,
        'test-api-key'
    );

    // Create a dummy game cover image
    $gameCoverPath = $this->tempDir.'/game_cover.png';
    $img = imagecreatetruecolor(20, 20);
    imagepng($img, $gameCoverPath);
    imagedestroy($img);

    // Prepare Request Mock
    $request = Mockery::mock(RequestInterface::class);
    $request->shouldReceive('withHeader')->with('Content-Type', 'application/json')->andReturnSelf();
    $request->shouldReceive('withBody')->andReturnSelf();

    $this->requestFactory->shouldReceive('createRequest')
        ->with('POST', Mockery::pattern('/gemini-.+:generateContent/'))
        ->andReturn($request);

    $this->streamFactory->shouldReceive('createStream')->andReturn(Mockery::mock(StreamInterface::class));

    // Prepare Response Mock
    $img = imagecreatetruecolor(10, 10);
    ob_start();
    imagejpeg($img);
    $realImageData = ob_get_clean();
    imagedestroy($img);
    $b64 = base64_encode($realImageData);

    $jsonResponse = json_encode([
        'candidates' => [
            [
                'content' => [
                    'parts' => [
                        [
                            'inlineData' => [
                                'mime_type' => 'image/jpeg',
                                'data' => $b64,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $responseBody = Mockery::mock(StreamInterface::class);
    $responseBody->shouldReceive('getContents')->andReturn($jsonResponse);

    $response = Mockery::mock(ResponseInterface::class);
    $response->shouldReceive('getStatusCode')->andReturn(200);
    $response->shouldReceive('getBody')->andReturn($responseBody);

    // Text response mock
    $textJsonResponse = json_encode([
        'candidates' => [
            [
                'content' => [
                    'parts' => [
                        ['text' => 'This is a very long description'],
                    ],
                ],
            ],
        ],
    ]);

    $textResponseBody = Mockery::mock(StreamInterface::class);
    $textResponseBody->shouldReceive('getContents')->andReturn($textJsonResponse);

    $textResponse = Mockery::mock(ResponseInterface::class);
    $textResponse->shouldReceive('getStatusCode')->andReturn(200);
    $textResponse->shouldReceive('getBody')->andReturn($textResponseBody);

    $this->httpClient->shouldReceive('sendRequest')
        ->times(3)
        ->andReturn($textResponse, $response, $response);

    $path = $generator->generate($this->dummyImage, 'GameName', 'This is a very long description', $gameCoverPath);

    expect(file_exists($path))->toBeTrue();
});

it('handles gif and webp extensions and handles clickbait error', function () {
    $generator = new GeminiCoverGenerator(
        $this->imageProcessor,
        $this->tempDir,
        null,
        null,
        $this->httpClient,
        $this->requestFactory,
        $this->streamFactory,
        'test-api-key'
    );

    // Dummy images
    $gifImage = $this->tempDir.'/input.gif';
    $img = imagecreatetruecolor(10, 10);
    imagegif($img, $gifImage);

    $webpCover = $this->tempDir.'/cover.webp';
    // Just empty file for extension check
    file_put_contents($webpCover, 'fake webp');

    // Prepare Request Mock
    $request = Mockery::mock(RequestInterface::class);
    $request->shouldReceive('withHeader')->with('Content-Type', 'application/json')->andReturnSelf();
    $request->shouldReceive('withBody')->andReturnSelf();

    $this->requestFactory->shouldReceive('createRequest')->andReturn($request);
    $this->streamFactory->shouldReceive('createStream')->andReturn(Mockery::mock(StreamInterface::class));

    // Prepare Response Mock (Image generation)
    ob_start();
    imagejpeg($img);
    $realImageData = ob_get_clean();
    imagedestroy($img);
    $b64 = base64_encode($realImageData);

    $jsonResponse = json_encode([
        'candidates' => [
            [
                'content' => [
                    'parts' => [
                        [
                            'inlineData' => [
                                'mime_type' => 'image/jpeg',
                                'data' => $b64,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $responseBody = Mockery::mock(StreamInterface::class);
    $responseBody->shouldReceive('getContents')->andReturn($jsonResponse);

    $response = Mockery::mock(ResponseInterface::class);
    $response->shouldReceive('getStatusCode')->andReturn(200);
    $response->shouldReceive('getBody')->andReturn($responseBody);

    // Text response mock - FAILS (500)
    $textResponse = Mockery::mock(ResponseInterface::class);
    $textResponse->shouldReceive('getStatusCode')->andReturn(500);

    $this->httpClient->shouldReceive('sendRequest')
        ->times(3)
        ->andReturn($textResponse, $response, $response);

    $path = $generator->generate($gifImage, 'GameName', 'This is a very long description', $webpCover);

    expect(file_exists($path))->toBeTrue();
});
