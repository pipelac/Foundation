<?php

declare(strict_types=1);

namespace App\Rss2Tlg;

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\OpenRouter;

/**
 * Сервис для AI-анализа новостей через OpenRouter API
 * 
 * Обрабатывает новости через LLM модели (DeepSeek, Qwen) с поддержкой кеширования.
 */
class AIAnalysisService
{
    private PromptManager $promptManager;
    private AIAnalysisRepository $repository;
    private OpenRouter $openRouter;
    private MySQL $db;
    private ?Logger $logger;
    
    private array $metrics = [
        'total_analyzed' => 0,
        'successful' => 0,
        'failed' => 0,
        'total_tokens' => 0,
        'total_time_ms' => 0,
        'cache_hits' => 0,
        'model_attempts' => [],
        'fallback_used' => 0,
    ];

    /**
     * Конструктор сервиса AI-анализа
     * 
     * @param PromptManager $promptManager Менеджер промптов
     * @param AIAnalysisRepository $repository Репозиторий для сохранения результатов
     * @param OpenRouter $openRouter Клиент OpenRouter API
     * @param MySQL $db Подключение к БД
     * @param Logger|null $logger Логгер для отладки
     */
    public function __construct(
        PromptManager $promptManager,
        AIAnalysisRepository $repository,
        OpenRouter $openRouter,
        MySQL $db,
        ?Logger $logger = null
    ) {
        $this->promptManager = $promptManager;
        $this->repository = $repository;
        $this->openRouter = $openRouter;
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Анализирует новость через AI с поддержкой fallback моделей
     * 
     * @param array<string, mixed> $item Данные новости из БД
     * @param string $promptId ID промпта для анализа
     * @param array<string>|null $models Список моделей в порядке приоритета (null = использовать из конфига)
     * @param array<string, mixed> $options Дополнительные опции для API
     * @return array<string, mixed>|null Результат анализа или null при ошибке
     */
    public function analyzeWithFallback(
        array $item,
        string $promptId,
        ?array $models = null,
        array $options = []
    ): ?array {
        // Если модели не указаны, используем значение по умолчанию
        if ($models === null || empty($models)) {
            $models = ['deepseek/deepseek-chat-v3.1:free'];
        }

        $lastError = null;
        $attemptNumber = 0;

        foreach ($models as $model) {
            $attemptNumber++;
            
            $this->logDebug('Попытка анализа с моделью', [
                'item_id' => $item['id'],
                'model' => $model,
                'attempt' => $attemptNumber,
                'total_models' => count($models),
            ]);

            // Увеличиваем счетчик попыток для модели
            if (!isset($this->metrics['model_attempts'][$model])) {
                $this->metrics['model_attempts'][$model] = 0;
            }
            $this->metrics['model_attempts'][$model]++;

            try {
                $result = $this->analyze($item, $promptId, $model, $options);
                
                if ($result !== null) {
                    if ($attemptNumber > 1) {
                        $this->metrics['fallback_used']++;
                        $this->logDebug('Fallback успешен', [
                            'item_id' => $item['id'],
                            'successful_model' => $model,
                            'attempt' => $attemptNumber,
                        ]);
                    }
                    return $result;
                }
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                $this->logError('Ошибка при анализе с моделью', [
                    'item_id' => $item['id'],
                    'model' => $model,
                    'attempt' => $attemptNumber,
                    'error' => $lastError,
                ]);

                // Если это не последняя модель, пробуем следующую
                if ($attemptNumber < count($models)) {
                    $retryDelayMs = $options['retry_delay_ms'] ?? 1000;
                    $this->logDebug('Переход к следующей модели', [
                        'next_model' => $models[$attemptNumber] ?? 'unknown',
                        'delay_ms' => $retryDelayMs,
                    ]);
                    usleep($retryDelayMs * 1000);
                    continue;
                }
            }
        }

        // Все модели не сработали
        $this->logError('Все модели не смогли проанализировать новость', [
            'item_id' => $item['id'],
            'models_tried' => $models,
            'last_error' => $lastError,
        ]);

        return null;
    }

    /**
     * Анализирует новость через AI
     * 
     * @param array<string, mixed> $item Данные новости из БД
     * @param string $promptId ID промпта для анализа
     * @param string $model Модель AI (например, 'deepseek/deepseek-chat')
     * @param array<string, mixed> $options Дополнительные опции для API
     * @return array<string, mixed>|null Результат анализа или null при ошибке
     */
    public function analyze(
        array $item,
        string $promptId,
        string $model = 'deepseek/deepseek-chat',
        array $options = []
    ): ?array {
        $startTime = microtime(true);
        $this->metrics['total_analyzed']++;

        try {
            // Проверяем, не анализировалась ли уже эта новость УСПЕШНО
            $existingAnalysis = $this->repository->getByItemId((int)$item['id']);
            if ($existingAnalysis !== null) {
                if ($existingAnalysis['analysis_status'] === 'success') {
                    $this->logDebug('Новость уже успешно проанализирована', ['item_id' => $item['id']]);
                    return $existingAnalysis;
                }
                
                // Если статус failed - удаляем запись для повторной попытки
                if ($existingAnalysis['analysis_status'] === 'failed') {
                    $this->logDebug('Удаляем failed запись для повторной попытки', [
                        'item_id' => $item['id'],
                        'analysis_id' => $existingAnalysis['id']
                    ]);
                    $this->db->execute("DELETE FROM rss2tlg_ai_analysis WHERE id = ?", [$existingAnalysis['id']]);
                }
            }

            // Получаем эффективный контент новости
            $itemRepository = new ItemRepository(
                $this->db,
                $this->logger,
                false
            );
            $effectiveContent = $itemRepository->getEffectiveContent($item);

            // Определяем язык статьи
            $articleLanguage = $this->detectLanguage($item['title'] ?? '', $effectiveContent);

            // Загружаем системный промпт (будет кешироваться на стороне API)
            $systemPrompt = $this->promptManager->getSystemPrompt($promptId);

            // Формируем динамическое сообщение пользователя
            $userMessage = $this->promptManager->buildUserMessage(
                $item['title'] ?? '',
                $effectiveContent,
                $articleLanguage
            );

            $this->logDebug('Отправка запроса к AI', [
                'item_id' => $item['id'],
                'prompt_id' => $promptId,
                'model' => $model,
                'article_language' => $articleLanguage,
                'content_length' => strlen($effectiveContent),
            ]);

            // Формируем messages для OpenRouter
            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage],
            ];

            // Добавляем опции для кеширования и качества
            // Параметры по умолчанию, могут быть переопределены через $options
            $apiOptions = array_merge([
                'messages' => $messages,
                'temperature' => 0.25,
                'top_p' => 0.85,
                'frequency_penalty' => 0.15,
                'presence_penalty' => 0.10,
                'max_tokens' => 3000,
                'min_tokens' => 400,
            ], $options);

            // Отправляем запрос к OpenRouter
            $response = $this->sendRequestToOpenRouter($model, $apiOptions);

            if ($response === null) {
                throw new \RuntimeException('OpenRouter вернул пустой ответ');
            }

            // Парсим JSON ответ от модели
            $analysisData = $this->parseAnalysisResponse($response);

            // Вычисляем метрики
            $processingTimeMs = (int)((microtime(true) - $startTime) * 1000);
            $tokensUsed = $this->extractTokensUsed($response);
            $cacheHit = $this->wasCacheHit($response);

            // Сохраняем результат
            $analysisId = $this->repository->save(
                (int)$item['id'],
                (int)$item['feed_id'],
                $promptId,
                $analysisData,
                $model,
                $tokensUsed,
                $processingTimeMs,
                $cacheHit
            );

            // Обновляем метрики
            $this->metrics['successful']++;
            $this->metrics['total_tokens'] += $tokensUsed ?? 0;
            $this->metrics['total_time_ms'] += $processingTimeMs;
            if ($cacheHit) {
                $this->metrics['cache_hits']++;
            }

            $this->logDebug('Анализ выполнен успешно', [
                'analysis_id' => $analysisId,
                'item_id' => $item['id'],
                'importance_rating' => $analysisData['importance']['rating'] ?? null,
                'processing_time_ms' => $processingTimeMs,
                'tokens_used' => $tokensUsed,
                'cache_hit' => $cacheHit,
            ]);

            return $this->repository->getByItemId((int)$item['id']);

        } catch (\Exception $e) {
            $this->metrics['failed']++;

            $errorMessage = $e->getMessage();
            $this->logError('Ошибка анализа новости', [
                'item_id' => $item['id'],
                'prompt_id' => $promptId,
                'error' => $errorMessage,
            ]);

            // Сохраняем ошибку
            $this->repository->saveError(
                (int)$item['id'],
                (int)$item['feed_id'],
                $promptId,
                $errorMessage
            );

            return null;
        }
    }

    /**
     * Пакетный анализ новостей
     * 
     * @param array<int, array<string, mixed>> $items Массив новостей
     * @param string $promptId ID промпта
     * @param string $model Модель AI
     * @param array<string, mixed> $options Опции API
     * @return array<string, mixed> Результаты анализа
     */
    public function analyzeBatch(
        array $items,
        string $promptId,
        string $model = 'deepseek/deepseek-chat',
        array $options = []
    ): array {
        $results = [
            'total' => count($items),
            'successful' => 0,
            'failed' => 0,
            'analyses' => [],
        ];

        foreach ($items as $item) {
            $analysis = $this->analyze($item, $promptId, $model, $options);
            
            if ($analysis !== null) {
                $results['successful']++;
                $results['analyses'][] = $analysis;
            } else {
                $results['failed']++;
            }

            // Задержка между запросами для кеширования (5 мин = 300 сек)
            // Небольшая задержка чтобы не превысить rate limits
            usleep(100000); // 100ms между запросами
        }

        return $results;
    }

    /**
     * Получает метрики работы сервиса
     * 
     * @return array<string, mixed> Метрики
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * Сбрасывает метрики
     */
    public function resetMetrics(): void
    {
        $this->metrics = [
            'total_analyzed' => 0,
            'successful' => 0,
            'failed' => 0,
            'total_tokens' => 0,
            'total_time_ms' => 0,
            'cache_hits' => 0,
            'model_attempts' => [],
            'fallback_used' => 0,
        ];
    }

    /**
     * Последний полный ответ от OpenRouter с метриками
     * 
     * @var array<string, mixed>|null
     */
    private ?array $lastApiResponse = null;

    /**
     * Отправляет запрос к OpenRouter API
     * 
     * @param string $model Модель AI
     * @param array<string, mixed> $options Опции запроса
     * @return string|null Ответ API или null
     */
    private function sendRequestToOpenRouter(string $model, array $options): ?string
    {
        try {
            // Извлекаем messages из options
            $messages = $options['messages'] ?? [];
            unset($options['messages']);

            // Используем новый метод chatWithMessages для поддержки кеширования
            // Это позволяет OpenRouter кешировать system message между запросами
            $fullResponse = $this->openRouter->chatWithMessages($model, $messages, $options);
            
            // Сохраняем для последующего извлечения метрик
            $this->lastApiResponse = $fullResponse;

            return $fullResponse['content'];
        } catch (\Exception $e) {
            $this->logError('Ошибка запроса к OpenRouter', [
                'model' => $model,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Парсит ответ от AI
     * 
     * @param string $response Ответ от модели
     * @return array<string, mixed> Распарсенные данные
     * @throws \RuntimeException Если не удалось распарсить JSON
     */
    private function parseAnalysisResponse(string $response): array
    {
        // Удаляем reasoning теги (deepseek-r1 и другие reasoning модели)
        $cleanedResponse = preg_replace('/<think>.*?<\/think>/s', '', $response);
        $cleanedResponse = preg_replace('/<thinking>.*?<\/thinking>/s', '', $cleanedResponse ?? $response);
        $cleanedResponse = $cleanedResponse ?? $response;
        
        // Извлекаем JSON из ответа (может быть обернут в markdown блок)
        $jsonMatch = [];
        
        // 1. Проверяем markdown блок с json
        if (preg_match('/```json\s*(\{.*\})\s*```/s', $cleanedResponse, $jsonMatch)) {
            $jsonString = $jsonMatch[1];
        }
        // 2. Проверяем обычный markdown блок
        elseif (preg_match('/```\s*(\{.*\})\s*```/s', $cleanedResponse, $jsonMatch)) {
            $jsonString = $jsonMatch[1];
        }
        // 3. Ищем JSON объект в тексте (жадно до последней })
        elseif (preg_match('/\{.*\}/s', $cleanedResponse, $jsonMatch)) {
            $jsonString = $jsonMatch[0];
        }
        // 4. Используем весь ответ как есть
        else {
            $jsonString = trim($cleanedResponse);
        }

        $data = json_decode($jsonString, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Логируем первые 500 символов для отладки
            $preview = mb_substr($jsonString, 0, 500);
            $this->logError('JSON parsing error', [
                'error' => json_last_error_msg(),
                'preview' => $preview,
            ]);
            throw new \RuntimeException('Не удалось распарсить JSON ответ: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Извлекает количество использованных токенов из ответа
     * 
     * @param string $response Ответ API
     * @return int|null Количество токенов или null
     */
    private function extractTokensUsed(string $response): ?int
    {
        if ($this->lastApiResponse === null) {
            return null;
        }

        $usage = $this->lastApiResponse['usage'] ?? null;
        if ($usage === null) {
            return null;
        }

        return (int)($usage['total_tokens'] ?? 0);
    }

    /**
     * Проверяет, был ли использован кеш
     * 
     * @param string $response Ответ API
     * @return bool true если кеш был использован
     */
    private function wasCacheHit(string $response): bool
    {
        if ($this->lastApiResponse === null) {
            return false;
        }

        // OpenRouter может возвращать информацию о кешировании в usage
        $usage = $this->lastApiResponse['usage'] ?? [];
        return isset($usage['cached_tokens']) && $usage['cached_tokens'] > 0;
    }

    /**
     * Получает детальные метрики из последнего API ответа
     * 
     * @return array<string, mixed>|null Метрики или null
     */
    public function getLastApiMetrics(): ?array
    {
        return $this->lastApiResponse;
    }

    /**
     * Определяет язык статьи
     * 
     * @param string $title Заголовок
     * @param string $content Контент
     * @return string Код языка (en, ru, и т.д.)
     */
    private function detectLanguage(string $title, string $content): string
    {
        $text = $title . ' ' . substr($content, 0, 500);
        
        // Простая эвристика: проверяем наличие кириллицы
        if (preg_match('/[\x{0400}-\x{04FF}]/u', $text)) {
            return 'ru';
        }
        
        return 'en';
    }

    /**
     * Логирует отладочную информацию
     * 
     * @param string $message Сообщение
     * @param array<string, mixed> $context Контекст
     */
    private function logDebug(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->debug($message, $context);
        }
    }

    /**
     * Логирует ошибку
     * 
     * @param string $message Сообщение об ошибке
     * @param array<string, mixed> $context Контекст
     */
    private function logError(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->error($message, $context);
        }
    }
}
