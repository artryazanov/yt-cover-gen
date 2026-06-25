<?php

return [
    'driver' => env('YT_COVER_GEN_DRIVER', 'openai'), // 'openai' or 'gemini'

    'output_path' => storage_path('app/public/covers'),

    'drivers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('YT_COVER_GEN_OPENAI_MODEL'), // e.g. 'gpt-image-2'
            'text_model' => env('YT_COVER_GEN_OPENAI_TEXT_MODEL'), // e.g. 'gpt-5.5'
            'size' => env('YT_COVER_GEN_OPENAI_SIZE'), // e.g. '1536x1024'
            'quality' => env('YT_COVER_GEN_OPENAI_QUALITY'), // e.g. 'high'
        ],
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('YT_COVER_GEN_GEMINI_MODEL'), // e.g. 'gemini-3.1-flash-image'
            'text_model' => env('YT_COVER_GEN_GEMINI_TEXT_MODEL'), // e.g. 'gemini-3.1-pro-preview'
            'aspect_ratio' => env('YT_COVER_GEN_GEMINI_ASPECT_RATIO'), // e.g. '16:9'
            'resolution' => env('YT_COVER_GEN_GEMINI_RESOLUTION'), // e.g. '1K'
        ],
    ],
];
