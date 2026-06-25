<?php

use Artryazanov\YtCoverGen\Support\ImageProcessor;

beforeEach(function () {
    $this->processor = new ImageProcessor;
    $this->tempDir = sys_get_temp_dir().'/yt_cover_gen_tests_'.uniqid();
    mkdir($this->tempDir);
});

afterEach(function () {
    // Cleanup temp files
    $files = glob($this->tempDir.'/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir($this->tempDir);
});

it('can process and save an image', function () {
    // Create a dummy 100x100 white image
    $img = imagecreatetruecolor(100, 100);
    imagefilledrectangle($img, 0, 0, 99, 99, imagecolorallocate($img, 255, 255, 255));
    ob_start();
    imagejpeg($img);
    $data = ob_get_clean();
    imagedestroy($img);

    $path = $this->processor->processAndSave($data, $this->tempDir, 'test.jpg');

    expect($path)->toBe($this->tempDir.'/test.jpg');
    expect(file_exists($path))->toBeTrue();

    // Verify 16:9 aspect ratio check (logic says newHeight = width * 9 / 16)
    // 100 * 9 / 16 = 56.25 -> 56
    $size = getimagesize($path);
    expect($size[0])->toBe(100);
    expect($size[1])->toBe(56);
});

it('can convert image to base64', function () {
    $path = $this->tempDir.'/test_base64.txt';
    file_put_contents($path, 'hello world');

    $base64 = $this->processor->imageToBase64($path);

    expect($base64)->toBe(base64_encode('hello world'));
});

it('can get correct mime type', function () {
    $files = [
        'test.png' => 'image/png',
        'test.gif' => 'image/gif',
        'test.webp' => 'image/webp',
        'test.jpg' => 'image/jpeg',
        'test.jpeg' => 'image/jpeg',
    ];

    foreach ($files as $file => $mime) {
        $path = $this->tempDir.'/'.$file;
        touch($path);
        expect($this->processor->getMimeType($path))->toBe($mime);
    }
});

it('throws exception when creating image from invalid data', function () {
    $this->processor->processAndSave('invalid_data', $this->tempDir, 'test.jpg');
})->throws(RuntimeException::class, 'Failed to create image from provided data');

it('can convert image to png', function () {
    $img = imagecreatetruecolor(50, 50);
    $path = $this->tempDir.'/test_convert.jpg';
    imagejpeg($img, $path);
    imagedestroy($img);

    $pngPath = $this->processor->convertToPng($path);

    expect(file_exists($pngPath))->toBeTrue();
    expect($this->processor->getMimeType($pngPath))->toBe('image/png');
    unlink($pngPath);
});

it('throws exception when reading invalid image for conversion', function () {
    $path = $this->tempDir.'/invalid.jpg';
    file_put_contents($path, 'not an image');
    $this->processor->convertToPng($path);
})->throws(RuntimeException::class);

it('throws exception when reading invalid image for base64', function () {
    $this->processor->imageToBase64($this->tempDir.'/does_not_exist.jpg');
})->throws(RuntimeException::class);

it('generates filename and creates dir when saving', function () {
    $img = imagecreatetruecolor(10, 10);
    ob_start();
    imagejpeg($img);
    $data = ob_get_clean();
    imagedestroy($img);

    $newDir = $this->tempDir.'/newdir';
    $path = $this->processor->processAndSave($data, $newDir);

    expect(file_exists($path))->toBeTrue();
    expect(dirname($path))->toBe($newDir);
    expect(pathinfo($path, PATHINFO_EXTENSION))->toBe('jpeg');

    unlink($path);
    rmdir($newDir);
});
