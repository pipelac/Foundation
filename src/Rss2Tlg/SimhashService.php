<?php

declare(strict_types=1);

namespace App\Rss2Tlg;

use App\Component\Logger;
use App\Component\MySQL;
use Swatchion\SimHash\SimHash;

/**
 * Сервис для работы с Simhash дедупликацией новостей
 * 
 * Использует алгоритм Simhash для определения схожести текстов.
 * Позволяет находить дубликаты новостей из разных источников.
 */
class SimhashService
{
    private const TABLE_NAME = 'rss2tlg_items';
    
    /**
     * Конструктор сервиса
     * 
     * @param MySQL $db Подключение к БД
     * @param Logger|null $logger Логгер для отладки
     */
    public function __construct(
        private readonly MySQL $db,
        private readonly ?Logger $logger = null
    ) {
    }

    /**
     * Вычисляет Simhash значение для текста
     * 
     * @param string $text Текст для вычисления хеша
     * @return string Simhash значение как бинарная строка (64 бита)
     */
    public function calculate(string $text): string
    {
        if (empty(trim($text))) {
            return str_repeat('0', 64);
        }

        try {
            $simhash = new SimHash();
            $hash = $simhash->hash($text);
            
            $this->logDebug('Simhash вычислен', [
                'text_length' => strlen($text),
                'hash_length' => strlen($hash),
            ]);
            
            return $hash;
        } catch (\Exception $e) {
            $this->logError('Ошибка вычисления Simhash', [
                'error' => $e->getMessage(),
            ]);
            return str_repeat('0', 64);
        }
    }

    /**
     * Ищет похожие новости за указанный период времени
     * 
     * @param string $simhash Simhash значение новой новости
     * @param int $hoursBack За сколько часов назад искать (по умолчанию 48)
     * @param int $maxDistance Максимальное расстояние Хэмминга (порог схожести)
     * @return array|null Массив с данными похожей новости или null
     */
    public function findSimilar(
        string $simhash, 
        int $hoursBack = 48, 
        int $maxDistance = 3
    ): ?array {
        $zeroHash = str_repeat('0', 64);
        if ($simhash === $zeroHash || empty($simhash)) {
            return null;
        }

        try {
            // Получаем все новости за указанный период с simhash
            $sql = sprintf(
                "SELECT id, title, simhash, created_at 
                FROM %s 
                WHERE simhash IS NOT NULL 
                AND simhash != '%s'
                AND created_at >= DATE_SUB(NOW(), INTERVAL %d HOUR)
                ORDER BY created_at DESC",
                self::TABLE_NAME,
                $zeroHash,
                $hoursBack
            );
            
            $candidates = $this->db->query($sql);
            
            if (empty($candidates)) {
                $this->logDebug('Нет кандидатов для сравнения', [
                    'hours_back' => $hoursBack,
                ]);
                return null;
            }

            $this->logDebug('Найдено кандидатов для сравнения', [
                'count' => count($candidates),
                'hours_back' => $hoursBack,
            ]);

            // Ищем наиболее похожую новость
            $bestMatch = null;
            $minDistance = PHP_INT_MAX;

            foreach ($candidates as $candidate) {
                $distance = $this->getHammingDistance($simhash, (string)$candidate['simhash']);
                
                if ($distance <= $maxDistance && $distance < $minDistance) {
                    $minDistance = $distance;
                    $bestMatch = $candidate;
                    $bestMatch['hamming_distance'] = $distance;
                }
            }

            if ($bestMatch !== null) {
                $this->logDebug('Найдена похожая новость', [
                    'original_id' => $bestMatch['id'],
                    'hamming_distance' => $minDistance,
                    'max_distance' => $maxDistance,
                ]);
            }

            return $bestMatch;
        } catch (\Exception $e) {
            $this->logError('Ошибка поиска похожих новостей', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Вычисляет расстояние Хэмминга между двумя Simhash значениями
     * 
     * @param string $hash1 Первый хеш (бинарная строка)
     * @param string $hash2 Второй хеш (бинарная строка)
     * @return int Расстояние Хэмминга (количество различающихся битов)
     */
    public function getHammingDistance(string $hash1, string $hash2): int
    {
        try {
            if (strlen($hash1) !== strlen($hash2)) {
                $this->logError('Длины хешей не совпадают', [
                    'hash1_length' => strlen($hash1),
                    'hash2_length' => strlen($hash2),
                ]);
                return PHP_INT_MAX;
            }
            
            $distance = 0;
            $length = strlen($hash1);
            
            for ($i = 0; $i < $length; $i++) {
                if ($hash1[$i] !== $hash2[$i]) {
                    $distance++;
                }
            }
            
            return $distance;
        } catch (\Exception $e) {
            $this->logError('Ошибка вычисления расстояния Хэмминга', [
                'error' => $e->getMessage(),
            ]);
            return PHP_INT_MAX;
        }
    }

    /**
     * Получает статистику по Simhash дедупликации
     * 
     * @return array<string, mixed> Статистика
     */
    public function getStats(): array
    {
        try {
            $zeroHash = str_repeat('0', 64);
            $sql = sprintf(
                "SELECT 
                    COUNT(*) as total_with_simhash,
                    SUM(CASE WHEN is_duplicate = 1 THEN 1 ELSE 0 END) as duplicates_found,
                    SUM(CASE WHEN is_duplicate = 0 AND simhash IS NOT NULL THEN 1 ELSE 0 END) as unique_items
                FROM %s
                WHERE simhash IS NOT NULL AND simhash != '%s'",
                self::TABLE_NAME,
                $zeroHash
            );
            
            $result = $this->db->queryOne($sql);
            return $result ?? [];
        } catch (\Exception $e) {
            $this->logError('Ошибка получения статистики Simhash', [
                'error' => $e->getMessage(),
            ]);
            return [];
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
            $this->logger->debug('[SimhashService] ' . $message, $context);
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
            $this->logger->error('[SimhashService] ' . $message, $context);
        }
    }
}
