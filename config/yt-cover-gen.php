<?php

return [
    'output_path' => storage_path('app/public/covers'),

    'drivers' => [
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('YT_COVER_GEN_GEMINI_MODEL'), // e.g. 'gemini-3.1-flash-image'
            'text_model' => env('YT_COVER_GEN_GEMINI_TEXT_MODEL'), // e.g. 'gemini-3.1-pro-preview'
            'aspect_ratio' => env('YT_COVER_GEN_GEMINI_ASPECT_RATIO'), // e.g. '16:9'
            'resolution' => env('YT_COVER_GEN_GEMINI_RESOLUTION'), // e.g. '1K'
        ],
    ],
];
