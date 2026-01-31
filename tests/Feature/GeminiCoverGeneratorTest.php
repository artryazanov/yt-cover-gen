<?php

use Artryazanov\YtCoverGen\Generators\GeminiCoverGenerator;
use Artryazanov\YtCoverGen\Support\ImageProcessor;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

beforeEach(function () {
    $this->httpClient = Mockery::mock(ClientInterface::class);
    $this->requestFactory = Mockery::mock(RequestFactoryInterface::class);
    $this->streamFactory = Mockery::mock(StreamFactoryInterface::class);
    $this->imageProcessor = new ImageProcessor();
    
    $this->tempDir = sys_get_temp_dir() . '/yt_cover_gen_tests_gemini_' . uniqid();
    mkdir($this->tempDir);
    
    $this->dummyImage = $this->tempDir . '/input.jpg';
    $img = imagecreatetruecolor(50, 50);
    imagejpeg($img, $this->dummyImage);
    imagedestroy($img);
});

afterEach(function () {
    $files = glob($this->tempDir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) unlink($file);
    }
    rmdir($this->tempDir);
});

it('throws exception if image missing', function () {
    $generator = new GeminiCoverGenerator(
        $this->imageProcessor, 
        $this->tempDir, 
        null, 
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
        'gemini-3-pro-image-preview', 
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
        ->with('POST', Mockery::pattern('/gemini-3-pro-image-preview:generateContent/'))
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
                                'data' => $b64
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ]);

    $responseBody = Mockery::mock(StreamInterface::class);
    $responseBody->shouldReceive('getContents')->andReturn($jsonResponse);

    $response = Mockery::mock(ResponseInterface::class);
    $response->shouldReceive('getStatusCode')->andReturn(200);
    $response->shouldReceive('getBody')->andReturn($responseBody);

    $this->httpClient->shouldReceive('sendRequest')
        ->once()
        ->andReturn($response);

    $path = $generator->generate($this->dummyImage, 'GameName', 'VideoDesc');

    expect(file_exists($path))->toBeTrue();
});
