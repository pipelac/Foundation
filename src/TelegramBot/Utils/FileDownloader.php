<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Utils;

use App\Component\Http;
use App\Component\Logger;
use App\Component\TelegramBot\Exceptions\FileException;

/**
 * Загрузчик файлов с серверов Telegram
 * 
 * Позволяет скачивать файлы, отправленные пользователями,
 * с серверов Telegram по file_id
 */
class FileDownloader
{
    /**
     * URL для получения информации о файле
     */
    private const FILE_INFO_URL = 'https://api.telegram.org/bot{token}/getFile?file_id={file_id}';

    /**
     * URL для скачивания файла
     */
    private const FILE_DOWNLOAD_URL = 'https://api.telegram.org/file/bot{token}/{file_path}';

    /**
     * @param string $token Токен бота
     * @param Http $http HTTP клиент
     * @param Logger|null $logger Логгер
     */
    public function __construct(
        private readonly string $token,
        private readonly Http $http,
        private readonly ?Logger $logger = null,
    ) {
    }

    /**
     * Получает информацию о файле по file_id
     *
     * @param string $fileId Идентификатор файла из Telegram
     * @return array{file_id: string, file_unique_id: string, file_size: int, file_path: string} Информация о файле
     * @throws FileException Если не удалось получить информацию
     */
    public function getFileInfo(string $fileId): array
    {
        try {
            $url = str_replace(
                ['{token}', '{file_id}'],
                [$this->token, $fileId],
                self::FILE_INFO_URL
            );

            $response = $this->http->get($url);
            $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

            if (!isset($data['ok']) || !$data['ok']) {
                throw new FileException(
                    'Не удалось получить информацию о файле: ' . ($data['description'] ?? 'Unknown error')
                );
            }

            if (!isset($data['result']['file_path'])) {
                throw new FileException('В ответе API отсутствует file_path');
            }

            $this->logger?->info('Получена информация о файле', [
                'file_id' => $fileId,
                'file_size' => $data['result']['file_size'] ?? 0,
            ]);

            return $data['result'];
        } catch (\JsonException $e) {
            $this->logger?->error('Ошибка парсинга JSON при получении информации о файле', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            throw new FileException('Ошибка парсинга ответа API: ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка получения информации о файле', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            throw new FileException('Ошибка получения информации о файле: ' . $e->getMessage());
        }
    }

    /**
     * Скачивает файл по file_id и сохраняет в указанный путь
     *
     * @param string $fileId Идентификатор файла из Telegram
     * @param string $savePath Путь для сохранения файла
     * @return string Путь к сохраненному файлу
     * @throws FileException Если не удалось скачать файл
     */
    public function downloadFile(string $fileId, string $savePath): string
    {
        try {
            $fileInfo = $this->getFileInfo($fileId);
            $filePath = $fileInfo['file_path'];

            $url = str_replace(
                ['{token}', '{file_path}'],
                [$this->token, $filePath],
                self::FILE_DOWNLOAD_URL
            );

            $directory = dirname($savePath);
            if (!is_dir($directory)) {
                if (!mkdir($directory, 0755, true)) {
                    throw new FileException('Не удалось создать директорию: ' . $directory);
                }
            }

            $response = $this->http->get($url);
            
            if (file_put_contents($savePath, $response) === false) {
                throw new FileException('Не удалось сохранить файл: ' . $savePath, $savePath);
            }

            $this->logger?->info('Файл успешно скачан', [
                'file_id' => $fileId,
                'save_path' => $savePath,
                'file_size' => $fileInfo['file_size'] ?? 0,
            ]);

            return $savePath;
        } catch (FileException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка скачивания файла', [
                'file_id' => $fileId,
                'save_path' => $savePath,
                'error' => $e->getMessage(),
            ]);

            throw new FileException(
                'Ошибка скачивания файла: ' . $e->getMessage(),
                $savePath
            );
        }
    }

    /**
     * Скачивает файл во временную директорию
     *
     * @param string $fileId Идентификатор файла из Telegram
     * @param string|null $filename Имя файла (если null, будет сгенерировано)
     * @return string Путь к скачанному файлу
     * @throws FileException Если не удалось скачать файл
     */
    public function downloadToTemp(string $fileId, ?string $filename = null): string
    {
        $filename = $filename ?? 'telegram_' . $fileId . '_' . time();
        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;

        return $this->downloadFile($fileId, $tempPath);
    }

    /**
     * Получает прямую ссылку на файл (без скачивания)
     *
     * @param string $fileId Идентификатор файла из Telegram
     * @return string URL для скачивания файла
     * @throws FileException Если не удалось получить ссылку
     */
    public function getFileUrl(string $fileId): string
    {
        $fileInfo = $this->getFileInfo($fileId);
        $filePath = $fileInfo['file_path'];

        return str_replace(
            ['{token}', '{file_path}'],
            [$this->token, $filePath],
            self::FILE_DOWNLOAD_URL
        );
    }

    /**
     * Получает размер файла без скачивания
     *
     * @param string $fileId Идентификатор файла из Telegram
     * @return int Размер файла в байтах
     * @throws FileException Если не удалось получить размер
     */
    public function getFileSize(string $fileId): int
    {
        $fileInfo = $this->getFileInfo($fileId);
        return (int)($fileInfo['file_size'] ?? 0);
    }
}
