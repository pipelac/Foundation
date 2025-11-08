<?php

declare(strict_types=1);

namespace App\Rss2Tlg\Pipeline;

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\Telegram;
use App\Rss2Tlg\Exception\Publication\PublicationException;
use Exception;

/**
 * Сервис публикации новостей в Telegram каналы и группы
 * 
 * Финальный этап AI Pipeline:
 * - Получение готовых к публикации новостей
 * - Фильтрация по правилам (категории, важность, язык)
 * - Публикация в Telegram (текст + изображение)
 * - Сохранение журнала публикаций
 * - Обработка ошибок и retry механизм
 * 
 * @version 2.0 - Рефакторинг с использованием AbstractPipelineModule
 */
class PublicationService extends AbstractPipelineModule
{
    private array $telegramBots = []; // Кеш Telegram клиентов

    /**
     * Конструктор сервиса публикаций
     *
     * @param MySQL $db Подключение к БД
     * @param array<string, mixed> $config Конфигурация модуля
     * @param Logger|null $logger Логгер
     */
    public function __construct(
        MySQL $db,
        array $config,
        ?Logger $logger = null
    ) {
        $this->db = $db;
        $this->logger = $logger;
        $this->config = $this->validateConfig($config);
        $this->metrics = $this->initializeMetrics();
    }

    /**
     * {@inheritdoc}
     */
    protected function getModuleName(): string
    {
        return 'Publication';
    }

    /**
     * {@inheritdoc}
     */
    protected function validateModuleConfig(array $config): array
    {
        if (empty($config['telegram_bots']) || !is_array($config['telegram_bots'])) {
            throw new PublicationException('Не указаны конфигурации Telegram ботов');
        }

        return [
            'telegram_bots' => $config['telegram_bots'],
            'batch_size' => max(1, (int)($config['batch_size'] ?? 10)),
            'message_template' => $config['message_template'] ?? null,
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function initializeMetrics(): array
    {
        return [
            'total_processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
            'by_destination' => [],
            'total_time_ms' => 0,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function processItem(int $itemId): bool
    {
        if (!$this->config['enabled']) {
            $this->logDebug('Модуль отключен', ['item_id' => $itemId]);
            return false;
        }

        $startTime = microtime(true);
        $this->incrementMetric('total_processed');

        try {
            // Получаем данные новости готовой к публикации
            $item = $this->getItemReadyToPublish($itemId);
            
            if (!$item) {
                $this->logWarning('Новость не готова к публикации', ['item_id' => $itemId]);
                $this->incrementMetric('skipped');
                return false;
            }

            // Получаем правила публикации для feed_id
            $rules = $this->getPublicationRules((int)$item['feed_id']);
            
            if (empty($rules)) {
                $this->logInfo('Нет правил публикации для источника', [
                    'item_id' => $itemId,
                    'feed_id' => $item['feed_id']
                ]);
                $this->incrementMetric('skipped');
                return false;
            }

            // Публикуем во все подходящие destinations
            $published = false;
            foreach ($rules as $rule) {
                if ($this->matchesRule($item, $rule)) {
                    if ($this->publishToDestination($item, $rule)) {
                        $published = true;
                    }
                }
            }

            if ($published) {
                // Обновляем флаг is_published в rss2tlg_items
                $this->db->execute(
                    'UPDATE rss2tlg_items SET is_published = 1 WHERE id = :item_id',
                    ['item_id' => $itemId]
                );
                
                $this->incrementMetric('successful');
                $processingTime = $this->recordProcessingTime($startTime);
                
                $this->logInfo('Новость успешно опубликована', [
                    'item_id' => $itemId,
                    'processing_time_ms' => $processingTime
                ]);
                
                return true;
            }

            $this->logWarning('Новость не соответствует ни одному правилу', ['item_id' => $itemId]);
            $this->incrementMetric('skipped');
            return false;

        } catch (Exception $e) {
            $this->incrementMetric('failed');
            $processingTime = $this->recordProcessingTime($startTime);
            
            $this->logError('Ошибка публикации новости', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
                'processing_time_ms' => $processingTime
            ]);
            
            return false;
        }
    }

    /**
     * Получает данные новости готовой к публикации
     *
     * @param int $itemId ID новости
     * @return array<string, mixed>|null
     */
    private function getItemReadyToPublish(int $itemId): ?array
    {
        $sql = 'SELECT * FROM v_rss2tlg_ready_to_publish WHERE item_id = :item_id LIMIT 1';
        $result = $this->db->queryOne($sql, ['item_id' => $itemId]);
        
        return $result ?: null;
    }

    /**
     * Получает правила публикации для источника
     *
     * @param int $feedId ID источника
     * @return array<int, array<string, mixed>>
     */
    private function getPublicationRules(int $feedId): array
    {
        $sql = 'SELECT * FROM rss2tlg_publication_rules 
                WHERE feed_id = :feed_id AND enabled = 1 
                ORDER BY priority DESC';
        
        $results = $this->db->query($sql, ['feed_id' => $feedId]);
        
        return $results ?: [];
    }

    /**
     * Проверяет соответствие новости правилу публикации
     *
     * @param array<string, mixed> $item Данные новости
     * @param array<string, mixed> $rule Правило публикации
     * @return bool
     */
    private function matchesRule(array $item, array $rule): bool
    {
        // Проверка минимальной важности
        if ($rule['min_importance'] !== null) {
            if (($item['importance_rating'] ?? 0) < $rule['min_importance']) {
                $this->logDebug('Новость не соответствует min_importance', [
                    'item_id' => $item['item_id'],
                    'importance' => $item['importance_rating'],
                    'required' => $rule['min_importance']
                ]);
                return false;
            }
        }

        // Проверка категорий
        if ($rule['categories'] !== null) {
            $allowedCategories = json_decode($rule['categories'], true);
            if (!in_array('all', $allowedCategories, true)) {
                $itemCategory = $item['category_primary'] ?? '';
                if (!in_array($itemCategory, $allowedCategories, true)) {
                    $this->logDebug('Новость не соответствует категориям', [
                        'item_id' => $item['item_id'],
                        'category' => $itemCategory,
                        'allowed' => $allowedCategories
                    ]);
                    return false;
                }
            }
        }

        // Проверка языков
        if ($rule['languages'] !== null) {
            $allowedLanguages = json_decode($rule['languages'], true);
            $itemLanguage = $item['translation_language'] ?? $item['article_language'] ?? '';
            
            if (!in_array($itemLanguage, $allowedLanguages, true)) {
                $this->logDebug('Новость не соответствует языкам', [
                    'item_id' => $item['item_id'],
                    'language' => $itemLanguage,
                    'allowed' => $allowedLanguages
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * Публикует новость в destination
     *
     * @param array<string, mixed> $item Данные новости
     * @param array<string, mixed> $rule Правило публикации
     * @return bool
     */
    private function publishToDestination(array $item, array $rule): bool
    {
        $retryCount = 0;
        $maxRetries = $this->config['retry_count'];
        
        while ($retryCount <= $maxRetries) {
            try {
                // Получаем Telegram клиент
                $telegram = $this->getTelegramClient($rule['destination_type']);
                
                if (!$telegram) {
                    $this->logError('Не найден Telegram клиент', [
                        'destination_type' => $rule['destination_type']
                    ]);
                    return false;
                }

                // Формируем сообщение
                $message = $this->formatMessage($item, $rule);
                
                // Публикуем
                $destinationId = $rule['destination_id'];
                $imagePath = ($rule['include_image'] && !empty($item['image_path'])) 
                    ? $item['image_path'] 
                    : null;

                $result = null;
                if ($imagePath && file_exists($imagePath)) {
                    // Публикуем с изображением
                    $result = $telegram->sendPhoto(
                        $destinationId,
                        $imagePath,
                        ['caption' => $message, 'parse_mode' => 'HTML']
                    );
                } else {
                    // Публикуем только текст
                    $result = $telegram->sendText(
                        $destinationId,
                        $message,
                        ['parse_mode' => 'HTML']
                    );
                }

                if (!$result || !isset($result['message_id'])) {
                    throw new PublicationException('Не получен message_id от Telegram API');
                }

                // Сохраняем запись о публикации
                $this->savePublication($item, $rule, $result['message_id'], $message);
                
                // Обновляем метрики
                $destKey = "{$rule['destination_type']}:{$rule['destination_id']}";
                if (!isset($this->metrics['by_destination'][$destKey])) {
                    $this->metrics['by_destination'][$destKey] = 0;
                }
                $this->metrics['by_destination'][$destKey]++;
                
                $this->logInfo('Новость опубликована в destination', [
                    'item_id' => $item['item_id'],
                    'destination' => $destKey,
                    'message_id' => $result['message_id']
                ]);
                
                return true;

            } catch (Exception $e) {
                $retryCount++;
                
                $this->logWarning('Ошибка публикации в destination', [
                    'item_id' => $item['item_id'],
                    'destination' => "{$rule['destination_type']}:{$rule['destination_id']}",
                    'retry' => $retryCount,
                    'error' => $e->getMessage()
                ]);

                if ($retryCount > $maxRetries) {
                    // Сохраняем запись о неудачной публикации
                    $this->saveFailedPublication($item, $rule, $e->getMessage(), $retryCount - 1);
                    return false;
                }
                
                // Экспоненциальная задержка перед повтором
                usleep(min(1000000, 100000 * (2 ** $retryCount)));
            }
        }

        return false;
    }

    /**
     * Получает Telegram клиент по типу destination
     *
     * @param string $destinationType Тип destination (bot, channel, group)
     * @return Telegram|null
     */
    private function getTelegramClient(string $destinationType): ?Telegram
    {
        // Ищем подходящий бот в конфиге
        foreach ($this->config['telegram_bots'] as $botConfig) {
            if (isset($botConfig['types']) && in_array($destinationType, $botConfig['types'], true)) {
                $botKey = $botConfig['token'];
                
                // Создаем клиент если еще не создан
                if (!isset($this->telegramBots[$botKey])) {
                    $this->telegramBots[$botKey] = new Telegram($botConfig, $this->logger);
                }
                
                return $this->telegramBots[$botKey];
            }
        }

        return null;
    }

    /**
     * Форматирует сообщение для публикации
     *
     * @param array<string, mixed> $item Данные новости
     * @param array<string, mixed> $rule Правило публикации
     * @return string
     */
    private function formatMessage(array $item, array $rule): string
    {
        // Используем переведенный контент если есть
        $headline = $item['translated_headline'] ?? $item['headline'] ?? '';
        $text = $item['translated_summary'] ?? $item['summary'] ?? '';

        // Используем кастомный шаблон если указан
        if (!empty($rule['template'])) {
            $template = $rule['template'];
            $message = str_replace([
                '{headline}',
                '{text}',
                '{category}',
                '{importance}',
                '{link}'
            ], [
                $headline,
                $text,
                $item['category_primary'] ?? '',
                $item['importance_rating'] ?? '',
                $item['source_link'] ?? ''
            ], $template);
        } else {
            // Стандартный формат
            $message = "<b>{$headline}</b>\n\n{$text}";
            
            // Добавляем ссылку если требуется
            if ($rule['include_link'] && !empty($item['source_link'])) {
                $message .= "\n\n🔗 <a href=\"{$item['source_link']}\">Читать полностью</a>";
            }
        }

        return $message;
    }

    /**
     * Сохраняет запись об успешной публикации
     *
     * @param array<string, mixed> $item Данные новости
     * @param array<string, mixed> $rule Правило публикации
     * @param int $messageId ID сообщения в Telegram
     * @param string $publishedText Опубликованный текст
     * @return void
     */
    private function savePublication(array $item, array $rule, int $messageId, string $publishedText): void
    {
        $media = [];
        if (!empty($item['image_path'])) {
            $media[] = [
                'type' => 'photo',
                'path' => $item['image_path'],
                'format' => $item['image_format'] ?? 'unknown'
            ];
        }

        $categories = [$item['category_primary']];
        if (!empty($item['category_secondary'])) {
            $secondary = json_decode($item['category_secondary'], true);
            if (is_array($secondary)) {
                $categories = array_merge($categories, $secondary);
            }
        }

        $sql = 'INSERT INTO rss2tlg_publications (
                    item_id, feed_id, destination_type, destination_id, message_id,
                    published_headline, published_text, published_language, 
                    published_media, published_categories, importance_rating,
                    publication_status, published_at
                ) VALUES (
                    :item_id, :feed_id, :destination_type, :destination_id, :message_id,
                    :headline, :text, :language,
                    :media, :categories, :importance,
                    :status, NOW()
                )';

        $this->db->execute($sql, [
            'item_id' => $item['item_id'],
            'feed_id' => $item['feed_id'],
            'destination_type' => $rule['destination_type'],
            'destination_id' => $rule['destination_id'],
            'message_id' => $messageId,
            'headline' => $item['translated_headline'] ?? $item['headline'] ?? '',
            'text' => $publishedText,
            'language' => $item['translation_language'] ?? $item['article_language'] ?? '',
            'media' => json_encode($media),
            'categories' => json_encode($categories),
            'importance' => $item['importance_rating'],
            'status' => 'published'
        ]);
    }

    /**
     * Сохраняет запись о неудачной публикации
     *
     * @param array<string, mixed> $item Данные новости
     * @param array<string, mixed> $rule Правило публикации
     * @param string $errorMessage Сообщение об ошибке
     * @param int $retryCount Количество повторов
     * @return void
     */
    private function saveFailedPublication(
        array $item,
        array $rule,
        string $errorMessage,
        int $retryCount
    ): void {
        $sql = 'INSERT INTO rss2tlg_publications (
                    item_id, feed_id, destination_type, destination_id, 
                    message_id, publication_status, retry_count, 
                    error_message, published_at
                ) VALUES (
                    :item_id, :feed_id, :destination_type, :destination_id,
                    0, :status, :retry_count, :error_message, NOW()
                )';

        $this->db->execute($sql, [
            'item_id' => $item['item_id'],
            'feed_id' => $item['feed_id'],
            'destination_type' => $rule['destination_type'],
            'destination_id' => $rule['destination_id'],
            'status' => 'failed',
            'retry_count' => $retryCount,
            'error_message' => substr($errorMessage, 0, 1000)
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getStatus(int $itemId): ?string
    {
        $sql = 'SELECT publication_status FROM rss2tlg_publications 
                WHERE item_id = :item_id 
                ORDER BY published_at DESC 
                LIMIT 1';
        
        $result = $this->db->queryOne($sql, ['item_id' => $itemId]);
        
        return $result ? $result['publication_status'] : null;
    }
}
