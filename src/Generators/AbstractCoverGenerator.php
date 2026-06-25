<?php

namespace Artryazanov\YtCoverGen\Generators;

use Artryazanov\YtCoverGen\Contracts\CoverGeneratorInterface;
use Artryazanov\YtCoverGen\Support\ImageProcessor;
use RuntimeException;

abstract class AbstractCoverGenerator implements CoverGeneratorInterface
{
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

        $shortTitle = $this->generateShortTitle($gameName, $videoDescription);
        $prompt = $this->buildPrompt($gameName, $shortTitle, $gameCoverPath);

        return $this->doGenerate($imagePath, $prompt, $gameCoverPath);
    }

    abstract protected function generateShortTitle(string $gameName, string $videoDescription): string;

    abstract protected function buildPrompt(string $gameName, string $generatedTitle, ?string $gameCoverPath = null): string;

    abstract protected function doGenerate(string $imagePath, string $prompt, ?string $gameCoverPath = null): string;

    protected function getShortTitleSystemPrompt(): string
    {
        return "You are a YouTube thumbnail text generator. The text you generate will be placed in huge font directly on the video cover, so it must be extremely concise to be highly visible.\nRules:\n1. If the user's input is already short, return it EXACTLY as is without any modifications.\n2. If the input is long, rewrite it into a very short, punchy phrase that captures the core essence of the original title. You should omit details, subtitles, or secondary information, as long as the main subject remains clear.\n3. Output ONLY the text that will be printed on the thumbnail, nothing else.";
    }
}
