<?php

/**
 * Конфигурация для тестирования модуля иллюстраций
 */

return [
    // База данных
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'rss2tlg',
        'username' => 'rss2tlg_user',
        'password' => 'rss2tlg_password_2024',
        'charset' => 'utf8mb4',
    ],

    // OpenRouter API
    'openrouter' => [
        'api_key' => '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',
        'app_name' => 'Rss2Tlg-IllustrationTest',
        'timeout' => 180,
        'retries' => 2,
    ],

    // Telegram Bot
    'telegram' => [
        'token' => '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI',
        'default_chat_id' => '366442475',
        'timeout' => 30,
    ],

    // Логирование
    'logger' => [
        'directory' => __DIR__ . '/../../logs/rss2tlg',
        'file_name' => 'illustration_test',
        'min_level' => 'debug',
    ],

    // RSS ленты для тестирования
    'rss_feeds' => [
        [
            'feed_id' => 1,
            'name' => 'TechCrunch',
            'url' => 'https://techcrunch.com/feed/',
            'enabled' => true,
            
            // Модуль суммаризации
            'summarization' => [
                'enabled' => true,
                'models' => [
                    [
                        'model' => 'anthropic/claude-3.5-sonnet',
                        'max_tokens' => 1500,
                        'temperature' => 0.2,
                        'top_p' => 0.9,
                        'frequency_penalty' => 0.3,
                        'presence_penalty' => 0.1,
                    ],
                    'deepseek/deepseek-chat',
                ],
                'retry_count' => 2,
                'timeout' => 120,
                'fallback_strategy' => 'sequential',
                'prompt_file' => __DIR__ . '/../../src/Rss2Tlg/prompts/summarization_prompt_v2.txt',
            ],
            
            // Модуль дедупликации
            'deduplication' => [
                'enabled' => true,
                'models' => [
                    [
                        'model' => 'anthropic/claude-3.5-sonnet',
                        'max_tokens' => 1000,
                        'temperature' => 0.1,
                        'top_p' => 0.95,
                    ],
                    'deepseek/deepseek-chat',
                ],
                'retry_count' => 2,
                'timeout' => 120,
                'fallback_strategy' => 'sequential',
                'similarity_threshold' => 0.85,
                'compare_last_n_days' => 7,
                'prompt_file' => __DIR__ . '/../../src/Rss2Tlg/prompts/deduplication_prompt_v2.txt',
            ],
            
            // Модуль иллюстраций
            'illustration' => [
                'enabled' => true,
                'models' => [
                    'openai/dall-e-3',
                    'stability-ai/stable-diffusion-xl',
                ],
                'retry_count' => 2,
                'timeout' => 180,
                'fallback_strategy' => 'sequential',
                'aspect_ratio' => '16:9',
                'image_path' => __DIR__ . '/../../data/illustrations',
                'watermark_text' => 'TechCrunch AI',
                'watermark_size' => 24,
                'watermark_position' => 'bottom-right',
                'prompt_file' => __DIR__ . '/../../src/Rss2Tlg/prompts/illustration_generation_prompt_v1.txt',
            ],
        ],
        
        [
            'feed_id' => 2,
            'name' => 'BBC News',
            'url' => 'http://feeds.bbci.co.uk/news/rss.xml',
            'enabled' => true,
            
            'summarization' => [
                'enabled' => true,
                'models' => [
                    [
                        'model' => 'anthropic/claude-3.5-sonnet',
                        'max_tokens' => 1500,
                        'temperature' => 0.2,
                        'top_p' => 0.9,
                        'frequency_penalty' => 0.3,
                        'presence_penalty' => 0.1,
                    ],
                ],
                'retry_count' => 2,
                'timeout' => 120,
                'fallback_strategy' => 'sequential',
                'prompt_file' => __DIR__ . '/../../src/Rss2Tlg/prompts/summarization_prompt_v2.txt',
            ],
            
            'deduplication' => [
                'enabled' => true,
                'models' => [
                    [
                        'model' => 'anthropic/claude-3.5-sonnet',
                        'max_tokens' => 1000,
                        'temperature' => 0.1,
                        'top_p' => 0.95,
                    ],
                ],
                'retry_count' => 2,
                'timeout' => 120,
                'fallback_strategy' => 'sequential',
                'similarity_threshold' => 0.85,
                'compare_last_n_days' => 7,
                'prompt_file' => __DIR__ . '/../../src/Rss2Tlg/prompts/deduplication_prompt_v2.txt',
            ],
            
            'illustration' => [
                'enabled' => true,
                'models' => [
                    'openai/dall-e-3',
                ],
                'retry_count' => 2,
                'timeout' => 180,
                'fallback_strategy' => 'sequential',
                'aspect_ratio' => '16:9',
                'image_path' => __DIR__ . '/../../data/illustrations',
                'watermark_text' => 'BBC News AI',
                'watermark_size' => 24,
                'watermark_position' => 'bottom-right',
                'prompt_file' => __DIR__ . '/../../src/Rss2Tlg/prompts/illustration_generation_prompt_v1.txt',
            ],
        ],
    ],
];
