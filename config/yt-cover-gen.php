<?php

return [
    'driver' => env('YT_COVER_GEN_DRIVER', 'openai'), // 'openai' or 'gemini'

    'output_path' => storage_path('app/public/covers'),

    'drivers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('YT_COVER_GEN_OPENAI_MODEL'), // e.g. 'gpt-image-1'
            'size' => env('YT_COVER_GEN_OPENAI_SIZE'), // e.g. '1536x1024'
        ],
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('YT_COVER_GEN_GEMINI_MODEL'), // e.g. 'gemini-3-pro-image-preview'
        ],
    ],
];
