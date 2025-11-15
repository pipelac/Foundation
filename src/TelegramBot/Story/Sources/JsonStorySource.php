<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Story\Sources;

use App\Component\Http;
use App\Component\Logger;
use App\Component\TelegramBot\Story\Exceptions\StoryException;

/**
 * Источник контента из JSON файлов или URL
 * 
 * Загружает истории из JSON файла или HTTP endpoint
 * в формате массива объектов с медиа и метаданными
 */
class JsonStorySource implements SourceInterface
{
    /**
     * @param array<string, mixed> $config Конфигурация источника
     * @param Http|null $http HTTP клиент для загрузки с URL
     * @param Logger|null $logger Логгер
     */
    public function __construct(
        private readonly array $config,
        private readonly ?Http $http = null,
        private readonly ?Logger $logger = null,
    ) {
        $this->validate();
    }

    /**
     * {@inheritdoc}
     */
    public function fetch(): array
    {
        $source = $this->config['source'];
        
        $this->logger?->info('Загрузка JSON источника', [
            'source' => $source,
            'type' => $this->isUrl($source) ? 'url' : 'file',
        ]);

        $data = $this->isUrl($source)
            ? $this->loadFromUrl($source)
            : $this->loadFromFile($source);

        $items = $this->transform($data);

        $this->logger?->info('Загружено элементов из JSON', [
            'count' => count($items),
        ]);

        return $items;
    }

    /**
     * Загружает данные из локального файла
     *
     * @param string $path Путь к файлу
     * @return array<array<string, mixed>>
     * @throws StoryException При ошибке чтения
     */
    public function loadFromFile(string $path): array
    {
        if (!file_exists($path)) {
            throw StoryException::sourceFetchFailed(
                $this->getName(),
                "Файл не найден: {$path}"
            );
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw StoryException::sourceFetchFailed(
                $this->getName(),
                "Не удалось прочитать файл: {$path}"
            );
        }

        $data = json_decode($content, true);
        if ($data === null) {
            throw StoryException::sourceFetchFailed(
                $this->getName(),
                "Невалидный JSON: " . json_last_error_msg()
            );
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Загружает данные из URL
     *
     * @param string $url URL источника
     * @return array<array<string, mixed>>
     * @throws StoryException При ошибке загрузки
     */
    public function loadFromUrl(string $url): array
    {
        if ($this->http === null) {
            throw StoryException::sourceFetchFailed(
                $this->getName(),
                "HTTP клиент не инициализирован"
            );
        }

        try {
            $response = $this->http->get($url);
            $data = json_decode($response, true);

            if ($data === null) {
                throw StoryException::sourceFetchFailed(
                    $this->getName(),
                    "Невалидный JSON от {$url}: " . json_last_error_msg()
                );
            }

            return is_array($data) ? $data : [];
        } catch (\Exception $e) {
            throw StoryException::sourceFetchFailed(
                $this->getName(),
                "Ошибка загрузки с {$url}: " . $e->getMessage()
            );
        }
    }

    /**
     * Трансформирует загруженные данные в формат для публикации
     *
     * @param array<array<string, mixed>> $data Исходные данные
     * @return array<array<string, mixed>>
     */
    private function transform(array $data): array
    {
        $items = [];
        $maxItems = $this->config['max_items'] ?? null;

        // Если данные обернуты в объект с ключом 'stories' или 'items'
        if (isset($data['stories']) && is_array($data['stories'])) {
            $data = $data['stories'];
        } elseif (isset($data['items']) && is_array($data['items'])) {
            $data = $data['items'];
        }

        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }

            // Пропускаем если нет медиа
            if (!isset($item['media_url']) && !isset($item['media_path'])) {
                continue;
            }

            // Определяем тип контента
            $type = $item['type'] ?? $this->detectType($item);

            $items[] = [
                'type' => $type,
                'media_url' => $item['media_url'] ?? null,
                'media_path' => $item['media_path'] ?? null,
                'caption' => $item['caption'] ?? null,
                'areas' => $item['areas'] ?? null,
                'privacy' => $item['privacy'] ?? null,
                'scheduled_at' => $item['scheduled_at'] ?? null,
                'metadata' => $item['metadata'] ?? null,
            ];

            if ($maxItems !== null && count($items) >= $maxItems) {
                break;
            }
        }

        return $items;
    }

    /**
     * Определяет тип контента по URL или пути
     *
     * @param array<string, mixed> $item Элемент данных
     * @return string
     */
    private function detectType(array $item): string
    {
        $media = $item['media_url'] ?? $item['media_path'] ?? '';
        $extension = strtolower(pathinfo($media, PATHINFO_EXTENSION));

        $videoExtensions = ['mp4', 'mov', 'avi', 'webm'];
        if (in_array($extension, $videoExtensions, true)) {
            return 'video';
        }

        return 'photo';
    }

    /**
     * Проверяет, является ли источник URL
     *
     * @param string $source Источник
     * @return bool
     */
    private function isUrl(string $source): bool
    {
        return filter_var($source, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function hasNewContent(): bool
    {
        try {
            $items = $this->fetch();
            return count($items) > 0;
        } catch (\Exception $e) {
            $this->logger?->warning('Ошибка проверки нового контента', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->config['name'] ?? 'json_source';
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return 'json';
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
        if (!isset($this->config['source'])) {
            throw StoryException::invalidConfiguration(
                'source',
                'Не указан источник JSON (путь к файлу или URL)'
            );
        }

        $source = $this->config['source'];
        if (!is_string($source) || empty($source)) {
            throw StoryException::invalidConfiguration(
                'source',
                'Источник должен быть непустой строкой'
            );
        }

        // Если это URL, проверяем наличие HTTP клиента
        if ($this->isUrl($source) && $this->http === null) {
            throw StoryException::invalidConfiguration(
                'http',
                'Для загрузки с URL требуется HTTP клиент'
            );
        }

        return true;
    }
}
