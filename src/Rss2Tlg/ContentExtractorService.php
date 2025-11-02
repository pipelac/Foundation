<?php

declare(strict_types=1);

namespace App\Rss2Tlg;

use App\Component\Logger;
use App\Component\WebtExtractor;
use App\Rss2Tlg\ItemRepository;

/**
 * Сервис для автоматического извлечения контента из веб-страниц
 * 
 * Работает с новостями из RSS лент, у которых нет полного контента.
 * Использует WebtExtractor для парсинга веб-страниц.
 */
class ContentExtractorService
{
    /**
     * Минимальная длина контента в RSS для пропуска извлечения
     */
    private const MIN_CONTENT_LENGTH = 300;
    
    /**
     * Максимальное количество попыток извлечения
     */
    private const MAX_RETRIES = 2;

    /**
     * Конструктор сервиса
     * 
     * @param ItemRepository $itemRepository Репозиторий новостей
     * @param WebtExtractor $extractor Экстрактор веб-контента
     * @param Logger|null $logger Логгер для отладки
     */
    public function __construct(
        private readonly ItemRepository $itemRepository,
        private readonly WebtExtractor $extractor,
        private readonly ?Logger $logger = null
    ) {
    }

    /**
     * Обрабатывает новости и извлекает контент для тех, у кого его нет
     * 
     * @param array<int, array<string, mixed>> $items Массив новостей из БД
     * @return array<string, mixed> Статистика обработки
     */
    public function processItems(array $items): array
    {
        $stats = [
            'total' => count($items),
            'skipped' => 0,
            'extracted' => 0,
            'failed' => 0,
            'duration' => 0,
        ];
        
        $startTime = microtime(true);
        
        $this->logInfo('Начало извлечения контента', [
            'items_count' => count($items),
        ]);
        
        foreach ($items as $item) {
            $itemId = (int)$item['id'];
            $link = (string)($item['link'] ?? '');
            
            // Проверяем, нужно ли извлекать контент
            if ($this->shouldSkipExtraction($item)) {
                $this->itemRepository->markExtractionSkipped($itemId);
                $stats['skipped']++;
                
                $this->logDebug('Извлечение пропущено: контент уже есть', [
                    'item_id' => $itemId,
                    'title' => $item['title'] ?? '',
                ]);
                continue;
            }
            
            // Проверяем ссылку
            if (empty($link)) {
                $this->itemRepository->markExtractionFailed($itemId, 'Отсутствует ссылка');
                $stats['failed']++;
                continue;
            }
            
            // Извлекаем контент
            try {
                $this->logInfo('Извлечение контента', [
                    'item_id' => $itemId,
                    'url' => $link,
                ]);
                
                $result = $this->extractor->extract($link);
                
                // Сохраняем результат
                $success = $this->itemRepository->saveExtractedContent(
                    itemId: $itemId,
                    extractedContent: $result['text_content'],
                    extractedImages: $result['images'],
                    extractedMetadata: $result['metadata']
                );
                
                if ($success) {
                    $stats['extracted']++;
                    
                    $this->logInfo('Контент успешно извлечен', [
                        'item_id' => $itemId,
                        'word_count' => $result['word_count'],
                        'images_count' => count($result['images']),
                    ]);
                } else {
                    $stats['failed']++;
                    $this->logError('Не удалось сохранить извлеченный контент', [
                        'item_id' => $itemId,
                    ]);
                }
                
            } catch (\Exception $e) {
                $errorMessage = sprintf('[%s] %s', get_class($e), $e->getMessage());
                $this->itemRepository->markExtractionFailed($itemId, $errorMessage);
                $stats['failed']++;
                
                $this->logError('Ошибка извлечения контента', [
                    'item_id' => $itemId,
                    'url' => $link,
                    'error' => $errorMessage,
                ]);
            }
            
            // Небольшая задержка между запросами
            usleep(500000); // 0.5 секунды
        }
        
        $stats['duration'] = round(microtime(true) - $startTime, 3);
        
        $this->logInfo('Извлечение контента завершено', $stats);
        
        return $stats;
    }

    /**
     * Обрабатывает одну новость
     * 
     * @param array<string, mixed> $item Данные новости из БД
     * @return bool true при успехе
     */
    public function processItem(array $item): bool
    {
        $itemId = (int)$item['id'];
        $link = (string)($item['link'] ?? '');
        
        // Проверяем, нужно ли извлекать
        if ($this->shouldSkipExtraction($item)) {
            $this->itemRepository->markExtractionSkipped($itemId);
            return true;
        }
        
        if (empty($link)) {
            $this->itemRepository->markExtractionFailed($itemId, 'Отсутствует ссылка');
            return false;
        }
        
        try {
            $result = $this->extractor->extract($link);
            
            return $this->itemRepository->saveExtractedContent(
                itemId: $itemId,
                extractedContent: $result['text_content'],
                extractedImages: $result['images'],
                extractedMetadata: $result['metadata']
            );
        } catch (\Exception $e) {
            $errorMessage = sprintf('[%s] %s', get_class($e), $e->getMessage());
            $this->itemRepository->markExtractionFailed($itemId, $errorMessage);
            
            $this->logError('Ошибка извлечения контента', [
                'item_id' => $itemId,
                'error' => $errorMessage,
            ]);
            
            return false;
        }
    }

    /**
     * Обрабатывает отложенные извлечения из БД
     * 
     * @param int $limit Максимальное количество новостей для обработки
     * @return array<string, mixed> Статистика обработки
     */
    public function processPending(int $limit = 10): array
    {
        $items = $this->itemRepository->getPendingExtraction($limit);
        return $this->processItems($items);
    }

    /**
     * Проверяет, нужно ли пропустить извлечение контента
     * 
     * @param array<string, mixed> $item Данные новости
     * @return bool true если нужно пропустить
     */
    private function shouldSkipExtraction(array $item): bool
    {
        // Если уже есть полный контент из RSS
        $content = (string)($item['content'] ?? '');
        if (strlen($content) >= self::MIN_CONTENT_LENGTH) {
            return true;
        }
        
        // Если описание достаточно длинное, можно пропустить
        $description = (string)($item['description'] ?? '');
        if (strlen($description) >= self::MIN_CONTENT_LENGTH * 2) {
            return true;
        }
        
        return false;
    }

    /**
     * Логирует информацию
     * 
     * @param string $message Сообщение
     * @param array<string, mixed> $context Контекст
     */
    private function logInfo(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->info($message, $context);
        }
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
