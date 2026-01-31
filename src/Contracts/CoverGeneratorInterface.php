<?php

namespace Artryazanov\YtCoverGen\Contracts;

interface CoverGeneratorInterface
{
    /**
     * Generate a YouTube cover from a screenshot/image.
     *
     * @param string $imagePath Path to the source image (local path).
     * @param string $gameName Name of the game.
     * @param string $videoDescription Description/Title to put on the cover.
     * @return string Path to the generated cover image.
     */
    public function generate(string $imagePath, string $gameName, string $videoDescription): string;
}
