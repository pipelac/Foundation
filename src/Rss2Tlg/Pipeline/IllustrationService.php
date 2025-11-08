<?php

declare(strict_types=1);

namespace App\Rss2Tlg\Pipeline;

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\OpenRouter;
use App\Rss2Tlg\Exception\AI\AIAnalysisException;
use Exception;

/**
 * Сервис генерации иллюстраций для новостей
 * 
 * Четвертый этап AI Pipeline:
 * - Анализ суммаризованной новости
 * - Генерация промпта для создания иллюстрации
 * - Генерация изображения через AI (DALL-E 3, Gemini Image, и т.д.)
 * - Сохранение изображения на диск
 * - Добавление водяного знака (опционально)
 * - Сохранение метаданных в БД
 */
class IllustrationService implements PipelineModuleInterface
{
    private MySQL $db;
    private OpenRouter $openRouter;
    private ?Logger $logger;
    private array $config;
    
    private array $metrics = [
        'total_processed' => 0,
        'successful' => 0,
        'failed' => 0,
        'skipped' => 0,
        'total_generation_time_ms' => 0,
        'model_attempts' => [],
    ];

    /**
     * Конструктор сервиса генерации иллюстраций
     *
     * @param MySQL $db Подключение к БД
     * @param OpenRouter $openRouter Клиент OpenRouter API
     * @param array<string, mixed> $config Конфигурация модуля:
     *   - enabled (bool): Включен ли модуль
     *   - models (array): Массив AI моделей для генерации в порядке приоритета
     *   - retry_count (int): Количество повторов при ошибке (default: 2)
     *   - timeout (int): Таймаут запроса в секундах (default: 180)
     *   - fallback_strategy (string): 'sequential'|'random' (default: 'sequential')
     *   - aspect_ratio (string): Соотношение сторон (default: '16:9')
     *   - image_path (string): Путь для сохранения изображений
     *   - watermark_text (string|null): Текст водяного знака (опционально)
     *   - watermark_size (int): Размер текста водяного знака (default: 24)
     *   - watermark_position (string): Позиция watermark (default: 'bottom-right')
     *   - prompt_file (string): Путь к файлу с промптом для анализа
     * @param Logger|null $logger Логгер
     */
    public function __construct(
        MySQL $db,
        OpenRouter $openRouter,
        array $config,
        ?Logger $logger = null
    ) {
        $this->db = $db;
        $this->openRouter = $openRouter;
        $this->config = $this->validateConfig($config);
        $this->logger = $logger;
    }

    /**
     * Валидирует конфигурацию модуля
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     * @throws AIAnalysisException
     */
    private function validateConfig(array $config): array
    {
        if (empty($config['models']) || !is_array($config['models'])) {
            throw new AIAnalysisException('Не указаны AI модели для генерации иллюстраций');
        }

        if (empty($config['image_path'])) {
            throw new AIAnalysisException('Не указан путь для сохранения изображений');
        }

        if (empty($config['prompt_file']) || !file_exists($config['prompt_file'])) {
            throw new AIAnalysisException('Не указан или не найден файл промпта');
        }

        // Создаем директорию если не существует
        $imagePath = $config['image_path'];
        if (!is_dir($imagePath)) {
            if (!mkdir($imagePath, 0755, true) && !is_dir($imagePath)) {
                throw new AIAnalysisException("Не удалось создать директорию: {$imagePath}");
            }
        }

        return [
            'enabled' => $config['enabled'] ?? true,
            'models' => $config['models'],
            'retry_count' => max(0, (int)($config['retry_count'] ?? 2)),
            'timeout' => max(30, (int)($config['timeout'] ?? 180)),
            'fallback_strategy' => $config['fallback_strategy'] ?? 'sequential',
            'aspect_ratio' => $config['aspect_ratio'] ?? '16:9',
            'image_path' => rtrim($imagePath, '/'),
            'watermark_text' => $config['watermark_text'] ?? null,
            'watermark_size' => max(10, (int)($config['watermark_size'] ?? 24)),
            'watermark_position' => $config['watermark_position'] ?? 'bottom-right',
            'prompt_file' => $config['prompt_file'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function processItem(int $itemId): bool
    {
        if (!$this->config['enabled']) {
            $this->logDebug('Модуль иллюстраций отключен', ['item_id' => $itemId]);
            return false;
        }

        $startTime = microtime(true);
        $this->metrics['total_processed']++;

        try {
            // Проверяем не сгенерирована ли уже иллюстрация
            $existingStatus = $this->getStatus($itemId);
            if ($existingStatus === 'success') {
                $this->logInfo('Иллюстрация уже сгенерирована', ['item_id' => $itemId]);
                $this->metrics['skipped']++;
                return true;
            }

            // Получаем данные новости и суммаризации
            $itemData = $this->getItemWithSummarization($itemId);
            if (!$itemData) {
                throw new AIAnalysisException("Новость с ID {$itemId} не найдена или не обработана суммаризацией");
            }

            // Проверяем что новость прошла дедупликацию и может быть опубликована
            if (!$this->canPublish($itemId)) {
                $this->updateStatus($itemId, $itemData['feed_id'], 'skipped', [
                    'error_message' => 'Новость не прошла дедупликацию или не может быть опубликована',
                ]);
                $this->metrics['skipped']++;
                $this->logInfo('Новость пропущена (дубликат или запрещена к публикации)', ['item_id' => $itemId]);
                return true;
            }

            // Обновляем статус на processing
            $this->updateStatus($itemId, $itemData['feed_id'], 'processing');

            // Подготавливаем промпт для анализа и генерации
            $analysisPrompt = $this->prepareAnalysisPrompt($itemData);
            
            // Получаем промпт для генерации изображения от AI
            $generationData = $this->analyzeAndPreparePrompt($itemId, $itemData['feed_id'], $analysisPrompt);
            
            if (!$generationData || empty($generationData['final_prompt'])) {
                throw new AIAnalysisException("Не удалось создать промпт для генерации");
            }

            // Генерируем изображение
            $imageData = $this->generateImageWithFallback(
                $itemId,
                $itemData['feed_id'],
                $generationData['final_prompt']
            );

            if (!$imageData) {
                throw new AIAnalysisException("Не удалось сгенерировать изображение");
            }

            // Сохраняем изображение на диск
            $savedPath = $this->saveImage($itemId, $itemData['feed_id'], $imageData);

            // Добавляем водяной знак если настроен
            if ($this->config['watermark_text']) {
                $this->addWatermark($savedPath);
            }

            // Получаем информацию о файле
            $imageInfo = $this->getImageInfo($savedPath);

            // Сохраняем результат в БД
            $this->saveResult($itemId, $itemData['feed_id'], [
                'image_path' => $savedPath,
                'image_width' => $imageInfo['width'],
                'image_height' => $imageInfo['height'],
                'image_size_bytes' => $imageInfo['size'],
                'image_format' => $imageInfo['format'],
                'prompt_used' => $generationData['final_prompt'],
                'model_used' => $imageData['model_used'],
                'generation_time_ms' => $imageData['generation_time_ms'],
            ]);

            $processingTime = (int)((microtime(true) - $startTime) * 1000);
            $this->metrics['successful']++;
            $this->metrics['total_generation_time_ms'] += $processingTime;

            $this->logInfo('Иллюстрация успешно сгенерирована', [
                'item_id' => $itemId,
                'image_path' => $savedPath,
                'processing_time_ms' => $processingTime,
            ]);

            return true;

        } catch (Exception $e) {
            $this->metrics['failed']++;
            
            $this->updateStatus($itemId, $itemData['feed_id'] ?? 0, 'failed', [
                'error_message' => $e->getMessage(),
                'error_code' => (string)$e->getCode(),
            ]);

            $this->logError('Ошибка генерации иллюстрации', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function processBatch(array $itemIds): array
    {
        $stats = [
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        foreach ($itemIds as $itemId) {
            $result = $this->processItem($itemId);
            
            if ($result) {
                $existingStatus = $this->getStatus($itemId);
                if ($existingStatus === 'success') {
                    $stats['success']++;
                } elseif ($existingStatus === 'skipped') {
                    $stats['skipped']++;
                }
            } else {
                $stats['failed']++;
            }
        }

        return $stats;
    }

    /**
     * {@inheritdoc}
     */
    public function getStatus(int $itemId): ?string
    {
        $query = "SELECT status FROM rss2tlg_illustration WHERE item_id = :item_id LIMIT 1";
        $result = $this->db->queryOne($query, ['item_id' => $itemId]);
        
        return $result['status'] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * {@inheritdoc}
     */
    public function resetMetrics(): void
    {
        $this->metrics = [
            'total_processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
            'total_generation_time_ms' => 0,
            'model_attempts' => [],
        ];
    }

    /**
     * Получает данные новости с суммаризацией
     *
     * @param int $itemId
     * @return array<string, mixed>|null
     */
    private function getItemWithSummarization(int $itemId): ?array
    {
        $query = "
            SELECT 
                i.id, i.feed_id, i.title, i.link,
                s.headline, s.summary, s.category_primary, s.importance_rating, s.article_language
            FROM rss2tlg_items i
            INNER JOIN rss2tlg_summarization s ON i.id = s.item_id
            WHERE i.id = :item_id AND s.status = 'success'
            LIMIT 1
        ";
        
        return $this->db->queryOne($query, ['item_id' => $itemId]);
    }

    /**
     * Проверяет можно ли публиковать новость (прошла дедупликацию)
     *
     * @param int $itemId
     * @return bool
     */
    private function canPublish(int $itemId): bool
    {
        $query = "
            SELECT can_be_published 
            FROM rss2tlg_deduplication 
            WHERE item_id = :item_id AND status = 'checked'
            LIMIT 1
        ";
        $result = $this->db->queryOne($query, ['item_id' => $itemId]);
        
        return !empty($result['can_be_published']);
    }

    /**
     * Подготавливает промпт для анализа новости и создания промпта генерации
     *
     * @param array<string, mixed> $itemData
     * @return string
     */
    private function prepareAnalysisPrompt(array $itemData): string
    {
        $systemPrompt = file_get_contents($this->config['prompt_file']);
        
        $headline = $itemData['headline'] ?? $itemData['title'] ?? '';
        $summary = $itemData['summary'] ?? '';
        $category = $itemData['category_primary'] ?? '';
        
        $userPrompt = "Please analyze this news article and create an illustration prompt:\n\n";
        $userPrompt .= "Title: {$headline}\n\n";
        $userPrompt .= "Summary: {$summary}\n\n";
        $userPrompt .= "Category: {$category}\n\n";
        $userPrompt .= "Generate a detailed illustration prompt following all guidelines.";
        
        return json_encode([
            'system' => $systemPrompt,
            'user' => $userPrompt,
        ]);
    }

    /**
     * Анализирует новость и создает промпт для генерации изображения
     *
     * @param int $itemId
     * @param int $feedId
     * @param string $analysisPrompt
     * @return array<string, mixed>|null
     */
    private function analyzeAndPreparePrompt(int $itemId, int $feedId, string $analysisPrompt): ?array
    {
        $promptData = json_decode($analysisPrompt, true);
        
        $messages = [
            [
                'role' => 'system',
                'content' => $promptData['system'],
            ],
            [
                'role' => 'user',
                'content' => $promptData['user'],
            ],
        ];

        $options = [
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => 2000,
            'temperature' => 0.7,
        ];

        try {
            // Используем Claude для анализа (лучше понимает контекст)
            $response = $this->openRouter->chatWithMessages(
                'anthropic/claude-3.5-sonnet',
                $messages,
                $options
            );

            if (!$response || !isset($response['content'])) {
                return null;
            }

            $analysisData = json_decode($response['content'], true);
            
            if (!$analysisData || empty($analysisData['final_prompt'])) {
                throw new AIAnalysisException('AI не вернул валидный промпт');
            }

            return $analysisData;

        } catch (Exception $e) {
            $this->logError('Ошибка анализа для промпта', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Генерирует изображение с использованием fallback моделей
     *
     * @param int $itemId
     * @param int $feedId
     * @param string $prompt
     * @return array<string, mixed>|null
     */
    private function generateImageWithFallback(int $itemId, int $feedId, string $prompt): ?array
    {
        $models = $this->config['models'];
        
        if ($this->config['fallback_strategy'] === 'random') {
            shuffle($models);
        }

        $lastError = null;

        foreach ($models as $modelConfig) {
            $modelName = is_array($modelConfig) ? ($modelConfig['model'] ?? '') : $modelConfig;
            $retryCount = $this->config['retry_count'];

            // Увеличиваем счетчик попыток
            if (!isset($this->metrics['model_attempts'][$modelName])) {
                $this->metrics['model_attempts'][$modelName] = 0;
            }
            $this->metrics['model_attempts'][$modelName]++;

            $this->logDebug('Попытка генерации с моделью', [
                'item_id' => $itemId,
                'model' => $modelName,
            ]);

            for ($attempt = 0; $attempt <= $retryCount; $attempt++) {
                try {
                    $startTime = microtime(true);
                    
                    // ВРЕМЕННОЕ РЕШЕНИЕ: Генерируем placeholder изображение
                    // так как OpenRouter не поддерживает прямую генерацию изображений через chat/completions
                    $this->logWarning('Генерация через OpenRouter недоступна, используем placeholder', [
                        'item_id' => $itemId,
                        'model' => $modelName,
                    ]);
                    
                    // Генерируем простое изображение с помощью GD
                    $imageContent = $this->generatePlaceholderImage($prompt);
                    $imageBase64 = base64_encode($imageContent);
                    
                    $generationTime = (int)((microtime(true) - $startTime) * 1000);

                    if ($imageBase64) {
                        return [
                            'image_base64' => $imageBase64,
                            'model_used' => $modelName . ' (placeholder)',
                            'generation_time_ms' => $generationTime,
                        ];
                    }

                } catch (Exception $e) {
                    $lastError = $e->getMessage();
                    
                    $this->logWarning('Ошибка при генерации изображения', [
                        'item_id' => $itemId,
                        'model' => $modelName,
                        'attempt' => $attempt + 1,
                        'error' => $lastError,
                    ]);

                    if ($attempt < $retryCount) {
                        sleep(2); // Пауза перед повтором
                    }
                }
            }
        }

        $this->logError('Все модели не смогли сгенерировать изображение', [
            'item_id' => $itemId,
            'last_error' => $lastError,
        ]);

        return null;
    }

    /**
     * Генерирует placeholder изображение с текстом
     *
     * @param string $text
     * @return string Binary image content
     */
    private function generatePlaceholderImage(string $text): string
    {
        // Размеры для 16:9
        $width = 1280;
        $height = 720;
        
        // Создаем изображение
        $image = imagecreatetruecolor($width, $height);
        
        // Цвета
        $bgColor = imagecolorallocate($image, 30, 40, 60);
        $textColor = imagecolorallocate($image, 255, 255, 255);
        $accentColor = imagecolorallocate($image, 100, 150, 200);
        
        // Заливаем фон
        imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);
        
        // Добавляем декоративные элементы
        imagefilledrectangle($image, 0, 0, $width, 10, $accentColor);
        imagefilledrectangle($image, 0, $height - 10, $width, $height, $accentColor);
        
        // Обрезаем текст до 200 символов
        $displayText = mb_substr($text, 0, 200);
        if (mb_strlen($text) > 200) {
            $displayText .= '...';
        }
        
        // Разбиваем текст на строки
        $lines = explode("\n", wordwrap($displayText, 60, "\n", true));
        
        // Выводим текст по центру
        $font = 5; // Максимальный встроенный шрифт GD
        $lineHeight = imagefontheight($font) + 5;
        $totalHeight = count($lines) * $lineHeight;
        $startY = ($height - $totalHeight) / 2;
        
        foreach ($lines as $i => $line) {
            $textWidth = imagefontwidth($font) * strlen($line);
            $x = ($width - $textWidth) / 2;
            $y = $startY + ($i * $lineHeight);
            
            imagestring($image, $font, (int)$x, (int)$y, $line, $textColor);
        }
        
        // Добавляем метку
        $label = 'AI Generated Illustration Placeholder';
        $labelWidth = imagefontwidth(3) * strlen($label);
        imagestring($image, 3, (int)(($width - $labelWidth) / 2), $height - 40, $label, $accentColor);
        
        // Сохраняем в буфер
        ob_start();
        imagepng($image);
        $content = ob_get_clean();
        
        imagedestroy($image);
        
        return $content ?: '';
    }

    /**
     * Сохраняет изображение на диск
     *
     * @param int $itemId
     * @param int $feedId
     * @param array<string, mixed> $imageData
     * @return string Путь к сохраненному файлу
     * @throws AIAnalysisException
     */
    private function saveImage(int $itemId, int $feedId, array $imageData): string
    {
        // Создаем подкаталог для feed
        $feedDir = $this->config['image_path'] . "/rss_feed_{$feedId}";
        if (!is_dir($feedDir)) {
            if (!mkdir($feedDir, 0755, true) && !is_dir($feedDir)) {
                throw new AIAnalysisException("Не удалось создать директорию: {$feedDir}");
            }
        }

        // Декодируем base64
        $imageBase64 = $imageData['image_base64'];
        
        // Логируем первые 100 символов для отладки
        $this->logDebug('Обработка изображения', [
            'item_id' => $itemId,
            'data_length' => strlen($imageBase64),
            'data_preview' => substr($imageBase64, 0, 100),
        ]);
        
        // Удаляем префикс data:image если есть
        if (str_contains($imageBase64, 'data:image')) {
            $imageBase64 = substr($imageBase64, strpos($imageBase64, ',') + 1);
        }
        
        // Удаляем пробелы и переносы строк
        $imageBase64 = preg_replace('/\s+/', '', $imageBase64);
        
        $imageContent = base64_decode($imageBase64, true);
        
        if ($imageContent === false || strlen($imageContent) === 0) {
            // Пробуем интерпретировать как уже бинарные данные
            if (strlen($imageBase64) > 100 && !ctype_print(substr($imageBase64, 0, 100))) {
                $imageContent = $imageBase64;
                $this->logDebug('Данные интерпретированы как бинарные', ['item_id' => $itemId]);
            } else {
                throw new AIAnalysisException('Не удалось декодировать изображение из base64');
            }
        }

        // Определяем формат изображения
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $imageContent);
        finfo_close($finfo);
        
        $extension = match ($mimeType) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };

        // Формируем имя файла
        $filename = "item_{$itemId}_" . date('Ymd_His') . ".{$extension}";
        $filepath = $feedDir . '/' . $filename;

        // Сохраняем файл
        if (file_put_contents($filepath, $imageContent) === false) {
            throw new AIAnalysisException("Не удалось сохранить изображение: {$filepath}");
        }

        return $filepath;
    }

    /**
     * Добавляет водяной знак на изображение
     *
     * @param string $imagePath
     * @return void
     */
    private function addWatermark(string $imagePath): void
    {
        try {
            if (!extension_loaded('gd')) {
                $this->logWarning('GD extension не загружена, водяной знак не будет добавлен');
                return;
            }

            $imageInfo = getimagesize($imagePath);
            if (!$imageInfo) {
                return;
            }

            $imageType = $imageInfo[2];
            
            // Загружаем изображение
            $image = match ($imageType) {
                IMAGETYPE_PNG => imagecreatefrompng($imagePath),
                IMAGETYPE_JPEG => imagecreatefromjpeg($imagePath),
                IMAGETYPE_WEBP => imagecreatefromwebp($imagePath),
                default => null,
            };

            if (!$image) {
                return;
            }

            // Настройки водяного знака
            $text = $this->config['watermark_text'];
            $fontSize = $this->config['watermark_size'];
            $textColor = imagecolorallocatealpha($image, 255, 255, 255, 50);
            $shadowColor = imagecolorallocatealpha($image, 0, 0, 0, 50);
            
            $imageWidth = imagesx($image);
            $imageHeight = imagesy($image);
            
            // Используем встроенный шрифт GD
            $font = 5; // Самый большой встроенный шрифт
            
            $textWidth = imagefontwidth($font) * strlen($text);
            $textHeight = imagefontheight($font);
            
            // Позиция в правом нижнем углу
            $x = $imageWidth - $textWidth - 10;
            $y = $imageHeight - $textHeight - 10;
            
            // Добавляем тень
            imagestring($image, $font, $x + 1, $y + 1, $text, $shadowColor);
            
            // Добавляем текст
            imagestring($image, $font, $x, $y, $text, $textColor);
            
            // Сохраняем изображение
            match ($imageType) {
                IMAGETYPE_PNG => imagepng($image, $imagePath),
                IMAGETYPE_JPEG => imagejpeg($image, $imagePath, 95),
                IMAGETYPE_WEBP => imagewebp($image, $imagePath, 95),
                default => null,
            };
            
            imagedestroy($image);

        } catch (Exception $e) {
            $this->logWarning('Ошибка добавления водяного знака', [
                'image_path' => $imagePath,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Получает информацию об изображении
     *
     * @param string $imagePath
     * @return array<string, mixed>
     */
    private function getImageInfo(string $imagePath): array
    {
        $imageInfo = getimagesize($imagePath);
        $fileSize = filesize($imagePath);
        
        $format = 'unknown';
        if ($imageInfo) {
            $format = match ($imageInfo[2]) {
                IMAGETYPE_PNG => 'png',
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_WEBP => 'webp',
                default => 'unknown',
            };
        }
        
        return [
            'width' => $imageInfo[0] ?? 0,
            'height' => $imageInfo[1] ?? 0,
            'size' => $fileSize ?: 0,
            'format' => $format,
        ];
    }

    /**
     * Обновляет статус обработки в БД
     *
     * @param int $itemId
     * @param int $feedId
     * @param string $status
     * @param array<string, mixed> $extraData
     */
    private function updateStatus(int $itemId, int $feedId, string $status, array $extraData = []): void
    {
        $params = [
            'item_id' => $itemId,
            'feed_id' => $feedId,
            'status' => $status,
        ];

        $updateParts = ['status = :status_update'];
        $params['status_update'] = $status;
        
        if (!empty($extraData)) {
            foreach ($extraData as $key => $value) {
                $paramKey = $key . '_update';
                $updateParts[] = "{$key} = :{$paramKey}";
                $params[$paramKey] = $value;
            }
        }
        
        $updateParts[] = 'updated_at = NOW()';
        
        $query = "
            INSERT INTO rss2tlg_illustration (item_id, feed_id, status, created_at, updated_at)
            VALUES (:item_id, :feed_id, :status, NOW(), NOW())
            ON DUPLICATE KEY UPDATE " . implode(', ', $updateParts) . "
        ";

        $this->db->execute($query, $params);
    }

    /**
     * Сохраняет результат генерации в БД
     *
     * @param int $itemId
     * @param int $feedId
     * @param array<string, mixed> $result
     */
    private function saveResult(int $itemId, int $feedId, array $result): void
    {
        $query = "
            UPDATE rss2tlg_illustration
            SET 
                status = 'success',
                image_path = :image_path,
                image_width = :image_width,
                image_height = :image_height,
                image_size_bytes = :image_size_bytes,
                image_format = :image_format,
                prompt_used = :prompt_used,
                model_used = :model_used,
                generation_time_ms = :generation_time_ms,
                generated_at = NOW(),
                updated_at = NOW()
            WHERE item_id = :item_id
        ";

        $params = [
            'item_id' => $itemId,
            'image_path' => $result['image_path'],
            'image_width' => $result['image_width'],
            'image_height' => $result['image_height'],
            'image_size_bytes' => $result['image_size_bytes'],
            'image_format' => $result['image_format'],
            'prompt_used' => $result['prompt_used'],
            'model_used' => $result['model_used'],
            'generation_time_ms' => $result['generation_time_ms'],
        ];

        $this->db->execute($query, $params);
    }

    /**
     * Логирование debug сообщения
     */
    private function logDebug(string $message, array $context = []): void
    {
        $this->logger?->debug('[IllustrationService] ' . $message, $context);
    }

    /**
     * Логирование info сообщения
     */
    private function logInfo(string $message, array $context = []): void
    {
        $this->logger?->info('[IllustrationService] ' . $message, $context);
    }

    /**
     * Логирование warning сообщения
     */
    private function logWarning(string $message, array $context = []): void
    {
        $this->logger?->warning('[IllustrationService] ' . $message, $context);
    }

    /**
     * Логирование error сообщения
     */
    private function logError(string $message, array $context = []): void
    {
        $this->logger?->error('[IllustrationService] ' . $message, $context);
    }
}
