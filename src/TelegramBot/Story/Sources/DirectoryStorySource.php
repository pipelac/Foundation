<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Story\Sources;

use App\Component\Logger;
use App\Component\TelegramBot\Story\Exceptions\StoryException;

/**
 * Источник контента из локальной папки с медиа файлами
 * 
 * Сканирует указанную папку на наличие изображений и видео,
 * и возвращает их для публикации в виде историй
 */
class DirectoryStorySource implements SourceInterface
{
    /**
     * Поддерживаемые расширения фото
     */
    private const PHOTO_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * Поддерживаемые расширения видео
     */
    private const VIDEO_EXTENSIONS = ['mp4', 'mov', 'avi'];

    /**
     * @param array<string, mixed> $config Конфигурация источника
     * @param Logger|null $logger Логгер
     */
    public function __construct(
        private readonly array $config,
        private readonly ?Logger $logger = null,
    ) {
        $this->validate();
    }

    /**
     * {@inheritdoc}
     */
    public function fetch(): array
    {
        $watchPath = $this->config['watch_path'];
        $patterns = $this->config['file_patterns'] ?? ['*'];
        
        $this->logger?->info('Сканирование папки', [
            'path' => $watchPath,
            'patterns' => $patterns,
        ]);

        if (!is_dir($watchPath)) {
            throw StoryException::sourceFetchFailed(
                $this->getName(),
                "Папка не найдена: {$watchPath}"
            );
        }

        $items = [];
        $files = $this->scanDirectory($watchPath, $patterns);

        foreach ($files as $file) {
            $items[] = [
                'type' => $file['type'],
                'path' => $file['path'],
                'filename' => $file['filename'],
                'size' => $file['size'],
                'modified' => $file['modified'],
                'caption' => $this->extractCaption($file),
            ];
        }

        $this->logger?->info('Найдено файлов', [
            'count' => count($items),
            'path' => $watchPath,
        ]);

        return $items;
    }

    /**
     * Сканирует директорию и возвращает список медиа файлов
     *
     * @param string $path Путь к директории
     * @param array<string> $patterns Паттерны файлов
     * @return array<array<string, mixed>>
     */
    public function scanDirectory(string $path, array $patterns = ['*']): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $extension = strtolower($file->getExtension());
            $filename = $file->getFilename();

            // Проверка паттернов
            if (!$this->matchesPatterns($filename, $patterns)) {
                continue;
            }

            // Определение типа
            $type = null;
            if (in_array($extension, self::PHOTO_EXTENSIONS, true)) {
                $type = 'photo';
            } elseif (in_array($extension, self::VIDEO_EXTENSIONS, true)) {
                $type = 'video';
            }

            if ($type === null) {
                continue;
            }

            $files[] = [
                'path' => $file->getPathname(),
                'filename' => $filename,
                'type' => $type,
                'size' => $file->getSize(),
                'modified' => $file->getMTime(),
                'extension' => $extension,
            ];
        }

        // Сортировка по дате изменения (новые первые)
        usort($files, fn($a, $b) => $b['modified'] <=> $a['modified']);

        return $files;
    }

    /**
     * Проверяет соответствие файла паттернам
     *
     * @param string $filename Имя файла
     * @param array<string> $patterns Паттерны
     * @return bool
     */
    private function matchesPatterns(string $filename, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $filename)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Извлекает подпись из метаданных файла или имени
     *
     * @param array<string, mixed> $file Данные файла
     * @return string|null
     */
    private function extractCaption(array $file): ?string
    {
        // Попытка извлечь из файла метаданных
        $metadataPath = $file['path'] . '.txt';
        if (file_exists($metadataPath)) {
            $caption = file_get_contents($metadataPath);
            return $caption !== false ? trim($caption) : null;
        }

        // Попытка извлечь из имени файла
        $filename = pathinfo($file['filename'], PATHINFO_FILENAME);
        // Заменяем подчеркивания и дефисы на пробелы
        $caption = str_replace(['_', '-'], ' ', $filename);
        
        // Если это не просто цифры, возвращаем как подпись
        if (!preg_match('/^\d+$/', $caption)) {
            return $caption;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function hasNewContent(): bool
    {
        $watchPath = $this->config['watch_path'];
        
        if (!is_dir($watchPath)) {
            return false;
        }

        $files = $this->scanDirectory(
            $watchPath,
            $this->config['file_patterns'] ?? ['*']
        );

        return count($files) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->config['name'] ?? 'directory_source';
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return 'directory';
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(): bool
    {
        if (!isset($this->config['watch_path'])) {
            throw StoryException::invalidConfiguration(
                'watch_path',
                'Не указан путь к папке для мониторинга'
            );
        }

        $watchPath = $this->config['watch_path'];
        if (!is_string($watchPath) || empty($watchPath)) {
            throw StoryException::invalidConfiguration(
                'watch_path',
                'Путь должен быть непустой строкой'
            );
        }

        return true;
    }

    /**
     * Получает конфигурацию источника
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
