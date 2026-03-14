<?php

namespace Artryazanov\YtCoverGen\Enums;

enum OpenAiSizeEnum: string
{
    case SIZE_1024x1024 = '1024x1024';
    case SIZE_1536x1024 = '1536x1024';
    case SIZE_1024x1536 = '1024x1536';
    case AUTO = 'auto';
}
