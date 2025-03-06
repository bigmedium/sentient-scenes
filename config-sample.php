<?php
// config.sample.php
return [
    'openai_api_key' => 'your-api-key-here',
    'openai_api_url' => 'https://api.openai.com/v1/chat/completions',
    'openai_model' => 'chatgpt-4o-latest',
    'temperature' => 0.7,

   // OpenAI pricing (per 1K tokens)
    'openai_pricing' => [
        'input_per_1k' => 0.0025,   // $2.50 per 1M tokens
        'output_per_1k' => 0.01     // $10.00 per 1M tokens
    ],
    
    // Rate limiting configuration
    'rate_limits' => [
        'user' => [
            'per_minute' => [
                'max' => 10     // Maximum requests per minute
            ],
            'per_day' => [
                'max' => 40     // Maximum requests per day
            ]
        ],
        'global' => [
            'per_minute' => [
                'max' => 500    // Maximum total requests per minute
            ],
            'per_day' => [
                'max' => 50000  // Maximum total requests per day
            ]
        ]
    ]
];