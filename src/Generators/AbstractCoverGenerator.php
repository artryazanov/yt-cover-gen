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

        $clickbaitTitle = $this->generateClickbaitTitle($gameName, $videoDescription);
        $prompt = $this->buildPrompt($gameName, $clickbaitTitle, $gameCoverPath);

        return $this->doGenerate($imagePath, $prompt, $gameCoverPath);
    }

    abstract protected function generateClickbaitTitle(string $gameName, string $videoDescription): string;

    abstract protected function buildPrompt(string $gameName, string $generatedTitle, ?string $gameCoverPath = null): string;

    abstract protected function doGenerate(string $imagePath, string $prompt, ?string $gameCoverPath = null): string;

    protected function getClickbaitSystemPrompt(): string
    {
        return "You are an expert YouTube strategist. Your task is to generate a short, highly enticing, clickbait thumbnail title (maximum 5 words) based on the user's input (which can be a video title or description).\nRules:\n1. If the input is long, extract the core exciting part and shorten it to a punchy clickbait phrase.\n2. If the input is already short (5 words or less), leave it exactly as is without any additions.\nCRITICAL NEGATIVE CONSTRAINT: Do NOT invent, promise, or mention any features, items, characters, or events that are not explicitly present in the input. The clickbait must be 100% truthful.";
    }
}
