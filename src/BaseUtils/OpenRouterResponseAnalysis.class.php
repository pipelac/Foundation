<?php

declare(strict_types=1);

namespace App\Component;

use App\Component\Exception\OpenRouter\OpenRouterException;
use App\Component\Exception\OpenRouter\OpenRouterValidationException;
use JsonException;

/**
 * Класс для работы с ответами OpenRouter API
 * 
 * Предоставляет утилиты для:
 * - Извлечения JSON из ответов AI (с обработкой markdown блоков)
 * - Подготовки сообщений для запросов (с поддержкой кеширования для Claude)
 * - Подготовки опций для запросов
 * - Валидации конфигурации AI модулей
 * 
 * Класс использует статические методы и не требует создания экземпляра.
 * Опциональное логирование через setLogger().
 * 
 * @link https://openrouter.ai/docs OpenRouter API Documentation
 */
class OpenRouterResponseAnalysis
{
    private static ?Logger $logger = null;

    /**
     * Устанавливает logger для класса
     *
     * @param Logger|null $logger Экземпляр логгера или null для отключения логирования
     */
    public static function setLogger(?Logger $logger): void
    {
        self::$logger = $logger;
    }

    /**
     * Извлекает JSON из ответа AI (удаляет markdown блоки и префиксы)
     * 
     * Метод обрабатывает различные форматы ответов AI:
     * - JSON внутри markdown блоков: ```json...``` или ```...```
     * - JSON объект: {...}
     * - JSON массив: [...]
     * - Текст с префиксом перед JSON
     *
     * @param string $content Ответ от AI (может содержать markdown, префиксы)
     * @return string Очищенный JSON string
     */
    public static function extractJSON(string $content): string
    {
        // Убираем лишние пробелы в начале и конце
        $content = trim($content);
        
        // Паттерн 1: JSON внутри markdown блоков ```json...``` или ```...```
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $content, $matches)) {
            self::logDebug('JSON извлечен из markdown блока');
            return trim($matches[1]);
        }
        
        // Паттерн 2: JSON начинается с { и заканчивается }
        if (preg_match('/(\{.*\})/s', $content, $matches)) {
            self::logDebug('JSON объект извлечен из текста');
            return $matches[1];
        }
        
        // Паттерн 3: JSON начинается с [ и заканчивается ]
        if (preg_match('/(\[.*\])/s', $content, $matches)) {
            self::logDebug('JSON массив извлечен из текста');
            return $matches[1];
        }
        
        self::logDebug('JSON не найден, возвращаем оригинальный контент');
        
        // Возвращаем как есть, если не нашли паттернов
        return $content;
    }

    /**
     * Парсит JSON ответ с обработкой ошибок
     * 
     * Сначала извлекает JSON из текста (используя extractJSON),
     * затем парсит его в массив с обработкой ошибок.
     *
     * @param string $content Ответ от AI (может содержать markdown, префиксы)
     * @return array|null Распарсенный массив или null при ошибке
     */
    public static function parseJSONResponse(string $content): ?array
    {
        $jsonString = self::extractJSON($content);
        
        try {
            $data = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
            
            if (!is_array($data)) {
                self::logWarning('JSON распарсен, но результат не массив', [
                    'type' => gettype($data)
                ]);
                return null;
            }
            
            self::logDebug('JSON успешно распарсен', [
                'keys_count' => count($data)
            ]);
            
            return $data;
            
        } catch (JsonException $e) {
            self::logError('Ошибка парсинга JSON', [
                'error' => $e->getMessage(),
                'json_preview' => mb_substr($jsonString, 0, 100)
            ]);
            return null;
        }
    }

    /**
     * Подготавливает messages для AI запроса с поддержкой кеширования
     * 
     * Для моделей Claude используется специальный формат с cache_control
     * для кеширования системных промптов, что экономит токены и деньги.
     * Для остальных моделей используется стандартный формат.
     *
     * @param string $systemPrompt Системный промпт
     * @param string $userPrompt Пользовательский промпт
     * @param string $model Название модели (для определения поддержки кеширования)
     * @return array<int, array<string, mixed>> Массив сообщений для API
     * 
     * @example
     * // Для Claude:
     * $messages = OpenRouterResponseAnalysis::prepareMessages(
     *     'You are a helpful assistant',
     *     'Hello!',
     *     'anthropic/claude-3.5-sonnet'
     * );
     * // [
     * //   ['role' => 'system', 'content' => [['type' => 'text', 'text' => '...', 'cache_control' => [...]]]],
     * //   ['role' => 'user', 'content' => 'Hello!']
     * // ]
     * 
     * // Для других моделей:
     * $messages = OpenRouterResponseAnalysis::prepareMessages(
     *     'You are a helpful assistant',
     *     'Hello!',
     *     'openai/gpt-4'
     * );
     * // [
     * //   ['role' => 'system', 'content' => 'You are a helpful assistant'],
     * //   ['role' => 'user', 'content' => 'Hello!']
     * // ]
     */
    public static function prepareMessages(string $systemPrompt, string $userPrompt, string $model): array
    {
        $messages = [];

        // Для Claude используем кеширование промптов
        if (str_contains($model, 'claude')) {
            $messages[] = [
                'role' => 'system',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $systemPrompt,
                        'cache_control' => ['type' => 'ephemeral'],
                    ],
                ],
            ];
            
            self::logDebug('Подготовлены messages с кешированием для Claude', [
                'model' => $model
            ]);
        } else {
            $messages[] = [
                'role' => 'system',
                'content' => $systemPrompt,
            ];
            
            self::logDebug('Подготовлены стандартные messages', [
                'model' => $model
            ]);
        }

        $messages[] = [
            'role' => 'user',
            'content' => $userPrompt,
        ];

        return $messages;
    }

    /**
     * Подготавливает опции для AI запроса
     * 
     * Извлекает параметры из конфигурации модели (если передан массив)
     * или использует дефолтные значения (если передана строка).
     * Объединяет с дополнительными опциями, если они указаны.
     *
     * @param array<string, mixed>|string $modelConfig Конфигурация модели или строка с названием
     * @param array<string, mixed>|null $extraOptions Дополнительные опции для переопределения
     * @return array<string, mixed> Готовые опции для запроса
     * 
     * @example
     * // С конфигурацией модели:
     * $options = OpenRouterResponseAnalysis::prepareOptions([
     *     'model' => 'openai/gpt-4',
     *     'max_tokens' => 2000,
     *     'temperature' => 0.7
     * ]);
     * // ['response_format' => ['type' => 'json_object'], 'max_tokens' => 2000, 'temperature' => 0.7]
     * 
     * // С дефолтными значениями:
     * $options = OpenRouterResponseAnalysis::prepareOptions('openai/gpt-4');
     * // ['response_format' => ['type' => 'json_object'], 'max_tokens' => 1500, 'temperature' => 0.2]
     */
    public static function prepareOptions($modelConfig, ?array $extraOptions = null): array
    {
        $options = ['response_format' => ['type' => 'json_object']];
        
        if (is_array($modelConfig)) {
            // Копируем параметры из конфигурации модели
            $allowedParams = ['max_tokens', 'temperature', 'top_p', 'frequency_penalty', 'presence_penalty'];
            foreach ($allowedParams as $param) {
                if (isset($modelConfig[$param])) {
                    $options[$param] = $modelConfig[$param];
                }
            }
            
            self::logDebug('Опции подготовлены из конфигурации модели', [
                'params_count' => count($options)
            ]);
        } else {
            // Дефолтные значения для обратной совместимости
            $options['max_tokens'] = 1500;
            $options['temperature'] = 0.2;
            
            self::logDebug('Использованы дефолтные опции');
        }

        // Объединяем с дополнительными опциями
        if ($extraOptions) {
            $options = array_merge($options, $extraOptions);
            
            self::logDebug('Опции объединены с дополнительными параметрами', [
                'extra_params' => array_keys($extraOptions)
            ]);
        }

        return $options;
    }

    /**
     * Валидирует конфигурацию AI модулей
     * 
     * Проверяет наличие обязательных полей в конфигурации:
     * - models: массив моделей для использования
     * - prompt_file: путь к файлу с промптом
     * 
     * Возвращает валидированную конфигурацию с дефолтными значениями
     * для необязательных полей.
     *
     * @param array<string, mixed> $config Конфигурация для валидации
     * @return array<string, mixed> Валидированная конфигурация
     * @throws OpenRouterValidationException Если конфигурация некорректна
     * 
     * @example
     * $config = OpenRouterResponseAnalysis::validateAIConfig([
     *     'models' => ['openai/gpt-4', 'anthropic/claude-3.5-sonnet'],
     *     'prompt_file' => '/path/to/prompt.txt',
     *     'fallback_strategy' => 'sequential'
     * ]);
     * // [
     * //   'models' => ['openai/gpt-4', 'anthropic/claude-3.5-sonnet'],
     * //   'fallback_strategy' => 'sequential',
     * //   'prompt_file' => '/path/to/prompt.txt'
     * // ]
     */
    public static function validateAIConfig(array $config): array
    {
        if (empty($config['models']) || !is_array($config['models'])) {
            throw new OpenRouterValidationException('Не указаны AI модели в конфигурации');
        }

        if (empty($config['prompt_file']) || !file_exists($config['prompt_file'])) {
            throw new OpenRouterValidationException('Не указан или не найден файл промпта');
        }

        self::logDebug('Конфигурация AI модулей валидирована', [
            'models_count' => count($config['models']),
            'prompt_file' => $config['prompt_file']
        ]);

        return [
            'models' => $config['models'],
            'fallback_strategy' => $config['fallback_strategy'] ?? 'sequential',
            'prompt_file' => $config['prompt_file'],
        ];
    }

    /**
     * Обнаруживает JSON в тексте (разные форматы)
     * 
     * Пытается найти и извлечь JSON из произвольного текста,
     * затем парсит его в массив. Полезно для обработки ответов,
     * где JSON может быть в любом месте текста.
     *
     * @param string $content Текст для анализа
     * @return array|null Найденный и распарсенный JSON или null
     * 
     * @example
     * $text = "Here is the result: {\"status\": \"ok\", \"data\": [1, 2, 3]} - that's all";
     * $json = OpenRouterResponseAnalysis::detectJSONInText($text);
     * // ['status' => 'ok', 'data' => [1, 2, 3]]
     */
    public static function detectJSONInText(string $content): ?array
    {
        $jsonString = self::extractJSON($content);
        
        if ($jsonString === $content) {
            // Если extractJSON не нашел паттернов, пробуем парсить напрямую
            self::logDebug('Попытка прямого парсинга JSON');
        }
        
        return self::parseJSONResponse($content);
    }

    /**
     * Очищает markdown блоки из текста
     * 
     * Удаляет markdown code blocks (```...```) из текста,
     * оставляя только содержимое блоков.
     *
     * @param string $content Текст с markdown блоками
     * @return string Очищенный текст (содержимое блоков без обрамления)
     * 
     * @example
     * $markdown = "Some text\n```json\n{\"key\": \"value\"}\n```\nMore text";
     * $clean = OpenRouterResponseAnalysis::cleanMarkdown($markdown);
     * // "Some text\n{\"key\": \"value\"}\nMore text"
     */
    public static function cleanMarkdown(string $content): string
    {
        // Удаляем markdown блоки, оставляя их содержимое
        $cleaned = preg_replace('/```(?:\w+)?\s*\n?(.*?)\n?```/s', '$1', $content);
        
        self::logDebug('Markdown блоки очищены');
        
        return $cleaned ?? $content;
    }

    /**
     * Извлекает код из markdown блока
     * 
     * Извлекает содержимое markdown code block определенного языка.
     * Если язык не указан, извлекает первый найденный блок.
     *
     * @param string $content Текст с markdown code block
     * @param string $language Язык кода (json, php, python и т.д.) или пустая строка для любого языка
     * @return string|null Извлеченный код или null если блок не найден
     * 
     * @example
     * // Извлечь JSON блок:
     * $code = OpenRouterResponseAnalysis::extractCodeBlock($content, 'json');
     * 
     * // Извлечь любой блок:
     * $code = OpenRouterResponseAnalysis::extractCodeBlock($content);
     */
    public static function extractCodeBlock(string $content, string $language = ''): ?string
    {
        if ($language !== '') {
            // Ищем блок с конкретным языком
            $pattern = '/```' . preg_quote($language, '/') . '\s*\n?(.*?)\n?```/s';
        } else {
            // Ищем любой блок
            $pattern = '/```(?:\w+)?\s*\n?(.*?)\n?```/s';
        }
        
        if (preg_match($pattern, $content, $matches)) {
            self::logDebug('Code block извлечен', [
                'language' => $language ?: 'any'
            ]);
            return trim($matches[1]);
        }
        
        self::logDebug('Code block не найден', [
            'language' => $language ?: 'any'
        ]);
        
        return null;
    }

    /**
     * Логирует debug сообщение
     *
     * @param string $message Сообщение для логирования
     * @param array<string, mixed> $context Контекст для логирования
     */
    private static function logDebug(string $message, array $context = []): void
    {
        self::$logger?->debug('[OpenRouterResponseAnalysis] ' . $message, $context);
    }

    /**
     * Логирует warning сообщение
     *
     * @param string $message Сообщение для логирования
     * @param array<string, mixed> $context Контекст для логирования
     */
    private static function logWarning(string $message, array $context = []): void
    {
        self::$logger?->warning('[OpenRouterResponseAnalysis] ' . $message, $context);
    }

    /**
     * Логирует error сообщение
     *
     * @param string $message Сообщение для логирования
     * @param array<string, mixed> $context Контекст для логирования
     */
    private static function logError(string $message, array $context = []): void
    {
        self::$logger?->error('[OpenRouterResponseAnalysis] ' . $message, $context);
    }
}
