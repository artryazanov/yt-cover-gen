<?php

namespace Artryazanov\YtCoverGen\Generators;

use Artryazanov\YtCoverGen\Contracts\CoverGeneratorInterface;
use Artryazanov\YtCoverGen\Support\ImageProcessor;
use RuntimeException;

abstract class AbstractCoverGenerator implements CoverGeneratorInterface
{
    protected const MAX_WORDS_FOR_DIRECT_OUTPUT = 5;

    protected ImageProcessor $imageProcessor;

    protected string $outputPath;

    protected string $model;

    protected string $textModel;

    public function __construct(
        ImageProcessor $imageProcessor,
        string $outputPath,
        string $model,
        string $textModel
    ) {
        $this->imageProcessor = $imageProcessor;
        $this->outputPath = $outputPath;
        $this->model = $model;
        $this->textModel = $textModel;
    }

    public function generate(string $imagePath, string $gameName, string $videoDescription, ?string $gameCoverPath = null): string
    {
        if (! file_exists($imagePath)) {
            throw new RuntimeException("Image file not found: $imagePath");
        }

        $wordCount = count(preg_split('/\s+/u', trim($videoDescription), -1, PREG_SPLIT_NO_EMPTY));
        if ($wordCount <= self::MAX_WORDS_FOR_DIRECT_OUTPUT) {
            $shortTitle = $videoDescription;
        } else {
            $shortTitle = $this->generateShortTitle($gameName, $videoDescription);
        }

        if ($gameCoverPath) {
            $gameCoverPath = $this->getCleanLogo($gameCoverPath);
        }

        $prompt = $this->buildPrompt($gameName, $shortTitle, $videoDescription, $gameCoverPath);

        return $this->doGenerate($imagePath, $prompt, $gameCoverPath);
    }

    abstract protected function generateShortTitle(string $gameName, string $videoDescription): string;

    abstract protected function buildPrompt(string $gameName, string $generatedTitle, string $originalDescription, ?string $gameCoverPath = null): string;

    abstract protected function doGenerate(string $imagePath, string $prompt, ?string $gameCoverPath = null): string;

    protected function getShortTitleSystemPrompt(string $gameName, string $videoDescription): string
    {
        return "Condense the provided text into a short, punchy 2-5 word clickbaity phrase for a YouTube gaming video thumbnail.\n\n"
            ."Game: {$gameName}\n"
            ."Input: {$videoDescription}\n\n"
            .'Output ONLY the final short phrase, without quotes, introductions, or explanations.';
    }

    abstract protected function generateCleanLogo(string $originalLogoPath, string $cachePath): string;

    protected function getCleanLogo(string $originalLogoPath): string
    {
        if (! file_exists($originalLogoPath)) {
            return $originalLogoPath;
        }

        $hash = md5_file($originalLogoPath);
        $logosDir = rtrim($this->outputPath, '/').'/logos';

        if (! is_dir($logosDir)) {
            mkdir($logosDir, 0755, true);
        }

        $cachePath = $logosDir.'/'.$hash.'.jpg';

        if (file_exists($cachePath)) {
            return $cachePath;
        }

        return $this->generateCleanLogo($originalLogoPath, $cachePath);
    }
}
