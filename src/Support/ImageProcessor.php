<?php

namespace Artryazanov\YtCoverGen\Support;

use RuntimeException;

class ImageProcessor
{
    private const IMAGE_FORMAT = 'jpeg';

    /**
     * Ensure the image is in 16:9 aspect ratio and save it.
     *
     * @param  string  $imageData  Binary image data.
     * @param  string  $outputPath  Directory to save the image to.
     * @param  string|null  $filename  Optional filename.
     * @return string Full path to the saved image.
     */
    public function processAndSave(string $imageData, string $outputPath, ?string $filename = null): string
    {
        // Convert binary image data to an image resource
        $src = @imagecreatefromstring($imageData);
        if ($src === false) {
            throw new RuntimeException('Failed to create image from provided data');
        }

        $width = imagesx($src);
        $height = imagesy($src); // Corrected from imagesx to imagesy

        // Create a truecolor canvas and fill with white (to handle PNG transparency if source was PNG)
        // Check if we need to resize to 16:9 or just process it
        // The original code calculated new height for 16:9 based on width
        $newHeight = (int) round($width * 9 / 16);

        // If the original image is already 16:9 (roughly), we might not need to crop/resize,
        // but the AI might return non-16:9 if not strictly enforced, so we enforce it.

        $dst = imagecreatetruecolor($width, $newHeight);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $width, $newHeight, $white);
        imagealphablending($dst, true);

        // Copy original image onto the background, resizing/resampling if needed to fit key area
        // The original code did a simple copyresampled to fit the width and new height
        imagecopyresampled(
            $dst,    // destination image
            $src,    // source image
            0, 0, 0, 0,   // destination x, y, source x, y
            $width,       // destination width
            $newHeight,   // destination height
            $width,       // source width
            imagesy($src) // source height
        );

        // Encode as JPEG into memory
        ob_start();
        imagejpeg($dst, null, 90);
        $resultData = ob_get_clean();

        // Free resources
        imagedestroy($src);
        imagedestroy($dst);

        // Generate filename if not provided
        if (! $filename) {
            $filename = 'cover_'.time().'_'.uniqid().'.'.self::IMAGE_FORMAT;
        }

        // Ensure directory exists
        if (! is_dir($outputPath)) {
            mkdir($outputPath, 0755, true);
        }

        $fullPath = rtrim($outputPath, '/').'/'.$filename;

        if (file_put_contents($fullPath, $resultData) === false) {
            throw new RuntimeException("Failed to save image to {$fullPath}");
        }

        return $fullPath;
    }

    /**
     * Convert image to Base64.
     */
    public function imageToBase64(string $path): string
    {
        $data = file_get_contents($path);
        if ($data === false) {
            throw new RuntimeException("Failed to read image: $path");
        }

        return base64_encode($data);
    }

    /**
     * Get image MIME type.
     */
    public function getMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }

    /**
     * Convert an image to PNG format, preserving or adding transparency.
     * Use this for OpenAI DALL-E 2 edits which require PNG with transparency.
     *
     * @param  string  $path  Input image path
     * @return string Path to the converted PNG file
     */
    public function convertToPng(string $path): string
    {
        $src = @imagecreatefromstring(file_get_contents($path));
        if ($src === false) {
            throw new RuntimeException("Failed to read image for conversion: $path");
        }

        $width = imagesx($src);
        $height = imagesy($src);

        // Create new truecolor image with alpha support
        $dst = imagecreatetruecolor($width, $height);

        // Disable blending to overwrite alpha info
        imagealphablending($dst, false);
        imagesavealpha($dst, true);

        // Fill with transparent color
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $width, $height, $transparent);

        // Copy processed image contents
        imagecopy($dst, $src, 0, 0, 0, 0, $width, $height);

        $tempPath = sys_get_temp_dir().'/'.uniqid('img_conv_').'.png';
        imagepng($dst, $tempPath);

        imagedestroy($src);
        imagedestroy($dst);

        return $tempPath;
    }
}
