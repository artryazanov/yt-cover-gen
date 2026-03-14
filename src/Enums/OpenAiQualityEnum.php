<?php

namespace Artryazanov\YtCoverGen\Enums;

enum OpenAiQualityEnum: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case AUTO = 'auto';
}
