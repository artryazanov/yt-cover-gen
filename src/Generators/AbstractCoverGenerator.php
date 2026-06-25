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
        return "You are a YouTube thumbnail text generator. Your task is to distill the user's input into the most concise, punchy phrase possible. This text will be printed in a huge font directly on the video thumbnail, so extreme brevity is critical.\n\nIdentify the absolute most important subject in the input and output ONLY that. Represent the text as concisely as possible, concentrating only on the core essence.\n\nOutput ONLY the final short phrase, without quotes, introductions, or explanations.";
    }
}
