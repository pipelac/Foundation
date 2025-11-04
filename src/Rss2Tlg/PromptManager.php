<?php

declare(strict_types=1);

namespace App\Rss2Tlg;

use App\Component\Logger;

/**
 * Менеджер для загрузки и управления промптами для AI-анализа новостей
 * 
 * Управляет файлами промптов, загружает системные промпты для кеширования.
 */
class PromptManager
{
    private string $promptsDirectory;
    private array $loadedPrompts = [];

    /**
     * Конструктор менеджера промптов
     * 
     * @param string $promptsDirectory Директория с файлами промптов
     * @param Logger|null $logger Логгер для отладки
     */
    public function __construct(
        string $promptsDirectory,
        private readonly ?Logger $logger = null
    ) {
        $this->promptsDirectory = rtrim($promptsDirectory, '/');
        
        if (!is_dir($this->promptsDirectory)) {
            throw new \RuntimeException("Директория промптов не существует: {$this->promptsDirectory}");
        }
    }

    /**
     * Загружает системный промпт по ID
     * 
     * @param string $promptId ID промпта (например, 'INoT_v1')
     * @return string Содержимое системного промпта
     * @throws \RuntimeException Если файл промпта не найден
     */
    public function getSystemPrompt(string $promptId): string
    {
        // Проверяем кеш
        if (isset($this->loadedPrompts[$promptId])) {
            $this->logDebug("Промпт загружен из кеша", ['prompt_id' => $promptId]);
            return $this->loadedPrompts[$promptId];
        }

        // Формируем путь к файлу
        $filePath = $this->getPromptFilePath($promptId);

        if (!file_exists($filePath)) {
            throw new \RuntimeException("Файл промпта не найден: {$filePath}");
        }

        // Загружаем содержимое
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Не удалось прочитать файл промпта: {$filePath}");
        }

        // Кешируем
        $this->loadedPrompts[$promptId] = $content;

        $this->logDebug("Промпт загружен из файла", [
            'prompt_id' => $promptId,
            'file_path' => $filePath,
            'size' => strlen($content),
        ]);

        return $content;
    }

    /**
     * Формирует динамическую часть запроса для AI (user message)
     * 
     * @param string $articleTitle Заголовок новости
     * @param string $articleText Текст новости
     * @param string $articleLanguage Язык статьи (en, ru, и т.д.)
     * @return string Форматированное сообщение пользователя
     */
    public function buildUserMessage(string $articleTitle, string $articleText, string $articleLanguage): string
    {
        // Формируем JSON для динамического входа
        $input = [
            'article_title' => $articleTitle,
            'article_text' => $articleText,
            'article_language' => $articleLanguage,
        ];

        return json_encode($input, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Получает список доступных промптов
     * 
     * @return array<string> Массив ID промптов
     */
    public function getAvailablePrompts(): array
    {
        $files = glob($this->promptsDirectory . '/*.xml');
        if ($files === false) {
            return [];
        }

        return array_map(function ($file) {
            return basename($file, '.xml');
        }, $files);
    }

    /**
     * Проверяет существование промпта
     * 
     * @param string $promptId ID промпта
     * @return bool true если промпт существует
     */
    public function hasPrompt(string $promptId): bool
    {
        return file_exists($this->getPromptFilePath($promptId));
    }

    /**
     * Получает полный путь к файлу промпта
     * 
     * @param string $promptId ID промпта
     * @return string Полный путь к файлу
     */
    private function getPromptFilePath(string $promptId): string
    {
        // Безопасность: удаляем недопустимые символы
        $safePromptId = preg_replace('/[^a-zA-Z0-9_-]/', '', $promptId);
        return $this->promptsDirectory . '/' . $safePromptId . '.xml';
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
}
