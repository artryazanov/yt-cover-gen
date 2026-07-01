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

    $validationJsonResponse = json_encode([
        'candidates' => [
            [
                'content' => [
                    'parts' => [
                        ['text' => '{"is_valid": true, "remarks": ""}'],
                    ],
                ],
            ],
        ],
    ]);
    $validationResponseBody = Mockery::mock(StreamInterface::class);
    $validationResponseBody->shouldReceive('getContents')->andReturn($validationJsonResponse);
    $this->validationResponse = Mockery::mock(ResponseInterface::class);
    $this->validationResponse->shouldReceive('getStatusCode')->andReturn(200);
    $this->validationResponse->shouldReceive('getBody')->andReturn($validationResponseBody);
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
        ->times(3)
        ->andReturn($textResponse, $response, $this->validationResponse);

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
        ->times(3)
        ->andReturn($textResponse, $response, $this->validationResponse);

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
        ->times(4)
        ->andReturn($textResponse, $response, $response, $this->validationResponse);

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
        ->times(4)
        ->andReturn($textResponse, $response, $response, $this->validationResponse);

    $path = $generator->generate($gifImage, 'GameName', 'This is a very long description', $webpCover);

    expect(file_exists($path))->toBeTrue();
});

it('retries generation when validation fails', function () {
    $generator = new GeminiCoverGenerator($this->imageProcessor, $this->tempDir, null, null, $this->httpClient, $this->requestFactory, $this->streamFactory, 'test-api-key');
    $request = Mockery::mock(RequestInterface::class);
    $request->shouldReceive('withHeader')->andReturnSelf();
    $request->shouldReceive('withBody')->andReturnSelf();
    $this->requestFactory->shouldReceive('createRequest')->andReturn($request);
    $this->streamFactory->shouldReceive('createStream')->andReturn(Mockery::mock(StreamInterface::class));

    $img = imagecreatetruecolor(10, 10); ob_start(); imagejpeg($img); $b64 = base64_encode(ob_get_clean()); imagedestroy($img);
    $jsonResponse = json_encode(['candidates' => [['content' => ['parts' => [['inlineData' => ['mime_type' => 'image/jpeg', 'data' => $b64]]]]]]]);
    $responseBody = Mockery::mock(StreamInterface::class);
    $responseBody->shouldReceive('getContents')->andReturn($jsonResponse);
    $response = Mockery::mock(ResponseInterface::class);
    $response->shouldReceive('getStatusCode')->andReturn(200);
    $response->shouldReceive('getBody')->andReturn($responseBody);

    $textJsonResponse = json_encode(['candidates' => [['content' => ['parts' => [['text' => 'Long title']]]]]]);
    $textResponseBody = Mockery::mock(StreamInterface::class);
    $textResponseBody->shouldReceive('getContents')->andReturn($textJsonResponse);
    $textResponse = Mockery::mock(ResponseInterface::class);
    $textResponse->shouldReceive('getStatusCode')->andReturn(200);
    $textResponse->shouldReceive('getBody')->andReturn($textResponseBody);

    $valFailJson = json_encode(['candidates' => [['content' => ['parts' => [['text' => '{"is_valid": false, "remarks": "Fix it"}']]]]]]);
    $valFailBody = Mockery::mock(StreamInterface::class);
    $valFailBody->shouldReceive('getContents')->andReturn($valFailJson);
    $valFail = Mockery::mock(ResponseInterface::class);
    $valFail->shouldReceive('getStatusCode')->andReturn(200);
    $valFail->shouldReceive('getBody')->andReturn($valFailBody);

    $this->httpClient->shouldReceive('sendRequest')
        ->times(5)
        ->andReturn($textResponse, $response, $valFail, $response, $this->validationResponse);

    $path = $generator->generate($this->dummyImage, 'GameName', 'This is a very long description that triggers title generation');
    expect(file_exists($path))->toBeTrue();
});

it('handles missing game cover gracefully', function () {
    $generator = new GeminiCoverGenerator($this->imageProcessor, $this->tempDir, null, null, $this->httpClient, $this->requestFactory, $this->streamFactory, 'key');
    
    $request = Mockery::mock(RequestInterface::class);
    $request->shouldReceive('withHeader')->andReturnSelf();
    $request->shouldReceive('withBody')->andReturnSelf();
    $this->requestFactory->shouldReceive('createRequest')->andReturn($request);
    $this->streamFactory->shouldReceive('createStream')->andReturn(Mockery::mock(StreamInterface::class));

    $img = imagecreatetruecolor(10, 10); ob_start(); imagejpeg($img); $b64 = base64_encode(ob_get_clean()); imagedestroy($img);
    $jsonResponse = json_encode(['candidates' => [['content' => ['parts' => [['inlineData' => ['mime_type' => 'image/jpeg', 'data' => $b64]]]]]]]);
    $responseBody = Mockery::mock(StreamInterface::class);
    $responseBody->shouldReceive('getContents')->andReturn($jsonResponse);
    $response = Mockery::mock(ResponseInterface::class);
    $response->shouldReceive('getStatusCode')->andReturn(200);
    $response->shouldReceive('getBody')->andReturn($responseBody);

    $this->httpClient->shouldReceive('sendRequest')->andReturn($response, $this->validationResponse);
    
    $path = $generator->generate($this->dummyImage, 'G', 'D', '/path/to/missing/cover.png');
    expect(file_exists($path))->toBeTrue();
});

it('uses cached game cover logo if it exists', function () {
    $generator = new GeminiCoverGenerator($this->imageProcessor, $this->tempDir, null, null, $this->httpClient, $this->requestFactory, $this->streamFactory, 'key');
    
    $request = Mockery::mock(RequestInterface::class);
    $request->shouldReceive('withHeader')->andReturnSelf();
    $request->shouldReceive('withBody')->andReturnSelf();
    $this->requestFactory->shouldReceive('createRequest')->andReturn($request);
    $this->streamFactory->shouldReceive('createStream')->andReturn(Mockery::mock(StreamInterface::class));

    $img = imagecreatetruecolor(10, 10); ob_start(); imagejpeg($img); $b64 = base64_encode(ob_get_clean()); imagedestroy($img);
    $jsonResponse = json_encode(['candidates' => [['content' => ['parts' => [['inlineData' => ['mime_type' => 'image/jpeg', 'data' => $b64]]]]]]]);
    $responseBody = Mockery::mock(StreamInterface::class);
    $responseBody->shouldReceive('getContents')->andReturn($jsonResponse);
    $response = Mockery::mock(ResponseInterface::class);
    $response->shouldReceive('getStatusCode')->andReturn(200);
    $response->shouldReceive('getBody')->andReturn($responseBody);

    $this->httpClient->shouldReceive('sendRequest')->andReturn($response, $response, $this->validationResponse, $response, $this->validationResponse);
    
    $gameCoverPath = $this->tempDir.'/game_cover_cache.png';
    $img2 = imagecreatetruecolor(20, 20); imagepng($img2, $gameCoverPath); imagedestroy($img2);

    $path1 = $generator->generate($this->dummyImage, 'G', 'D', $gameCoverPath);
    $path2 = $generator->generate($this->dummyImage, 'G', 'D', $gameCoverPath);
    expect(file_exists($path1))->toBeTrue();
});

it('returns generated image even if validation fails 3 times', function () {
    $generator = new GeminiCoverGenerator($this->imageProcessor, $this->tempDir, null, null, $this->httpClient, $this->requestFactory, $this->streamFactory, 'key');
    $request = Mockery::mock(RequestInterface::class);
    $request->shouldReceive('withHeader')->andReturnSelf();
    $request->shouldReceive('withBody')->andReturnSelf();
    $this->requestFactory->shouldReceive('createRequest')->andReturn($request);
    $this->streamFactory->shouldReceive('createStream')->andReturn(Mockery::mock(StreamInterface::class));

    $img = imagecreatetruecolor(10, 10); ob_start(); imagejpeg($img); $b64 = base64_encode(ob_get_clean()); imagedestroy($img);
    $jsonResponse = json_encode(['candidates' => [['content' => ['parts' => [['inlineData' => ['mime_type' => 'image/jpeg', 'data' => $b64]]]]]]]);
    $responseBody = Mockery::mock(StreamInterface::class);
    $responseBody->shouldReceive('getContents')->andReturn($jsonResponse);
    $response = Mockery::mock(ResponseInterface::class);
    $response->shouldReceive('getStatusCode')->andReturn(200);
    $response->shouldReceive('getBody')->andReturn($responseBody);

    $valFailJson = json_encode(['candidates' => [['content' => ['parts' => [['text' => '{"is_valid": false, "remarks": "Fix it"}']]]]]]);
    $valFailBody = Mockery::mock(StreamInterface::class);
    $valFailBody->shouldReceive('getContents')->andReturn($valFailJson);
    $valFail = Mockery::mock(ResponseInterface::class);
    $valFail->shouldReceive('getStatusCode')->andReturn(200);
    $valFail->shouldReceive('getBody')->andReturn($valFailBody);

    // 3 iterations = 3 validation failures
    $this->httpClient->shouldReceive('sendRequest')
        ->times(6) // generate + validate = 2 calls per iteration * 3 iterations
        ->andReturn($response, $valFail, $response, $valFail, $response, $valFail);

    $path = $generator->generate($this->dummyImage, 'G', 'D');
    expect(file_exists($path))->toBeTrue();
});

it('throws exception on Gemini API error', function () {
    $generator = new GeminiCoverGenerator($this->imageProcessor, $this->tempDir, null, null, $this->httpClient, $this->requestFactory, $this->streamFactory, 'key');
    $request = Mockery::mock(RequestInterface::class);
    $request->shouldReceive('withHeader')->andReturnSelf();
    $request->shouldReceive('withBody')->andReturnSelf();
    $this->requestFactory->shouldReceive('createRequest')->andReturn($request);
    $this->streamFactory->shouldReceive('createStream')->andReturn(Mockery::mock(StreamInterface::class));

    $responseBody = Mockery::mock(StreamInterface::class);
    $responseBody->shouldReceive('getContents')->andReturn('Internal Server Error');
    $response = Mockery::mock(ResponseInterface::class);
    $response->shouldReceive('getStatusCode')->andReturn(500);
    $response->shouldReceive('getBody')->andReturn($responseBody);

    $this->httpClient->shouldReceive('sendRequest')->once()->andReturn($response);
    
    $generator->generate($this->dummyImage, 'G', 'D');
})->throws(\Artryazanov\YtCoverGen\Exceptions\GeminiResponseException::class, 'Gemini API Error: Internal Server Error');

