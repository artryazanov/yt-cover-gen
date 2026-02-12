<?php

use Artryazanov\YtCoverGen\Generators\OpenAiCoverGenerator;
use Artryazanov\YtCoverGen\Support\ImageProcessor;
use OpenAI\Responses\Images\EditResponse;

beforeEach(function () {
    // Debug info
    $interface = \OpenAI\Contracts\ClientContract::class;
    if (! interface_exists($interface)) {
        echo "Interface $interface NOT FOUND. Attempting eager load...\n";
        if (file_exists(__DIR__.'/../../vendor/autoload.php')) {
            require_once __DIR__.'/../../vendor/autoload.php';
        }
    }

    $this->client = Mockery::mock(\OpenAI\Contracts\ClientContract::class);
    $this->images = Mockery::mock(\OpenAI\Contracts\Resources\ImagesContract::class);
    $this->client->allows()->images()->andReturn($this->images);

    $this->imageProcessor = new ImageProcessor;
    $this->tempDir = sys_get_temp_dir().'/yt_cover_gen_tests_openai_'.uniqid();
    if (! is_dir($this->tempDir)) {
        mkdir($this->tempDir);
    }

    $this->dummyImage = $this->tempDir.'/input.jpg';
    $img = imagecreatetruecolor(100, 100);
    imagejpeg($img, $this->dummyImage);
    imagedestroy($img);
});

afterEach(function () {
    if (isset($this->tempDir) && is_dir($this->tempDir)) {
        $files = glob($this->tempDir.'/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($this->tempDir);
    }
});

it('throws exception if image does not exist', function () {
    $generator = new OpenAiCoverGenerator($this->client, $this->imageProcessor, $this->tempDir);
    $generator->generate('non-existent.jpg', 'Game', 'Desc');
})->throws(RuntimeException::class, 'Image file not found');

it('generates cover using OpenAI', function () {
    $generator = new OpenAiCoverGenerator($this->client, $this->imageProcessor, $this->tempDir);

    $img = imagecreatetruecolor(10, 10);
    ob_start();
    imagejpeg($img);
    $realImageData = ob_get_clean();
    imagedestroy($img);
    $b64 = base64_encode($realImageData);

    $mockResponse = EditResponse::fake([
        'data' => [
            [
                'b64_json' => $b64,
            ],
        ],
    ]);

    $this->images->shouldReceive('edit')
        ->once()
        ->with(Mockery::on(function ($args) {
            return $args['model'] === 'gpt-image-1'
               && is_resource($args['image'])
               && str_contains($args['prompt'], 'Create a viral YouTube thumbnail')
               && $args['size'] === '1536x1024';
        }))
        ->andReturn($mockResponse);

    $path = $generator->generate($this->dummyImage, 'Game Name', 'Description');

    expect($path)->toBeString();
    expect(file_exists($path))->toBeTrue();
});
