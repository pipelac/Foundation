<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Core;

use App\Component\Logger;
use App\Component\TelegramBot\Exceptions\AccessControlException;

/**
 * Система контроля доступа пользователей к командам бота
 * 
 * Управляет правами доступа на основе ролей пользователей.
 * Загружает конфигурацию из JSON файлов и проверяет разрешения.
 * Поддерживает включение/отключение через конфигурацию.
 */
class AccessControl
{
    /**
     * Включен ли контроль доступа
     */
    private bool $enabled;

    /**
     * Роль по умолчанию для неизвестных пользователей
     */
    private string $defaultRole;

    /**
     * Сообщение об отказе в доступе
     */
    private string $accessDeniedMessage;

    /**
     * Список пользователей [chat_id => user_data]
     * @var array<int|string, array<string, mixed>>
     */
    private array $users;

    /**
     * Список ролей [role_name => role_data]
     * @var array<string, array<string, mixed>>
     */
    private array $roles;

    /**
     * @param string $configPath Путь к конфигурационному файлу
     * @param Logger|null $logger Логгер
     * @throws AccessControlException При ошибке загрузки конфигурации
     */
    public function __construct(
        string $configPath,
        private readonly ?Logger $logger = null,
    ) {
        $this->loadConfig($configPath);
    }

    /**
     * Загружает конфигурацию контроля доступа
     *
     * @param string $configPath Путь к файлу конфигурации
     * @throws AccessControlException При ошибке загрузки
     */
    private function loadConfig(string $configPath): void
    {
        try {
            if (!file_exists($configPath)) {
                throw new AccessControlException("Конфигурационный файл не найден: {$configPath}");
            }

            $config = $this->loadJsonFile($configPath);

            $this->enabled = $config['enabled'] ?? false;
            $this->defaultRole = $config['default_role'] ?? 'default';
            $this->accessDeniedMessage = $config['access_denied_message'] ?? 'У вас нет доступа к этой команде.';

            if ($this->enabled) {
                $usersFile = $config['users_file'] ?? null;
                $rolesFile = $config['roles_file'] ?? null;

                if (!$usersFile || !$rolesFile) {
                    throw new AccessControlException('В конфигурации не указаны users_file или roles_file');
                }

                $this->users = $this->loadJsonFile($usersFile);
                $this->roles = $this->loadJsonFile($rolesFile);

                $this->logger?->info('Контроль доступа TelegramBot активирован', [
                    'users_count' => count($this->users),
                    'roles_count' => count($this->roles),
                ]);
            } else {
                $this->users = [];
                $this->roles = [];
                $this->logger?->info('Контроль доступа TelegramBot деактивирован');
            }
        } catch (AccessControlException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new AccessControlException('Ошибка загрузки конфигурации: ' . $e->getMessage());
        }
    }

    /**
     * Загружает JSON файл
     *
     * @param string $filePath Путь к файлу
     * @return array<string, mixed> Данные из файла
     * @throws AccessControlException При ошибке загрузки или парсинга
     */
    private function loadJsonFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new AccessControlException("Файл не найден: {$filePath}");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new AccessControlException("Не удалось прочитать файл: {$filePath}");
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new AccessControlException("Ошибка парсинга JSON в {$filePath}: " . json_last_error_msg());
        }

        if (!is_array($data)) {
            throw new AccessControlException("Некорректный формат данных в {$filePath}");
        }

        return $data;
    }

    /**
     * Проверяет, включен ли контроль доступа
     *
     * @return bool True если включен
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Проверяет доступ пользователя к команде
     *
     * @param int $chatId ID чата пользователя
     * @param string $command Команда (с / или без)
     * @return bool True если доступ разрешен
     */
    public function checkAccess(int $chatId, string $command): bool
    {
        // Если контроль доступа выключен - разрешаем все
        if (!$this->enabled) {
            return true;
        }

        // Нормализуем команду (добавляем / если отсутствует)
        $command = $this->normalizeCommand($command);

        // Получаем роль пользователя
        $role = $this->getUserRole($chatId);

        // Проверяем доступ роли к команде
        $allowed = $this->isCommandAllowedForRole($role, $command);

        $this->logger?->debug('Проверка доступа к команде', [
            'chat_id' => $chatId,
            'command' => $command,
            'role' => $role,
            'allowed' => $allowed,
        ]);

        return $allowed;
    }

    /**
     * Получает роль пользователя по его chat_id
     *
     * @param int $chatId ID чата пользователя
     * @return string Название роли
     */
    public function getUserRole(int $chatId): string
    {
        $chatIdStr = (string)$chatId;

        // Проверяем наличие пользователя
        if (isset($this->users[$chatIdStr])) {
            return $this->users[$chatIdStr]['role'] ?? $this->defaultRole;
        }

        // Возвращаем роль по умолчанию
        return $this->defaultRole;
    }

    /**
     * Получает информацию о пользователе
     *
     * @param int $chatId ID чата пользователя
     * @return array<string, mixed>|null Данные пользователя или null
     */
    public function getUserInfo(int $chatId): ?array
    {
        $chatIdStr = (string)$chatId;

        if (isset($this->users[$chatIdStr])) {
            return $this->users[$chatIdStr];
        }

        // Возвращаем данные роли по умолчанию если есть
        if (isset($this->users['default'])) {
            return $this->users['default'];
        }

        return null;
    }

    /**
     * Получает список разрешенных команд для пользователя
     *
     * @param int $chatId ID чата пользователя
     * @return array<string> Массив разрешенных команд
     */
    public function getAllowedCommands(int $chatId): array
    {
        if (!$this->enabled) {
            return [];
        }

        $role = $this->getUserRole($chatId);

        if (!isset($this->roles[$role])) {
            return [];
        }

        return $this->roles[$role]['commands'] ?? [];
    }

    /**
     * Проверяет, разрешена ли команда для роли
     *
     * @param string $role Название роли
     * @param string $command Команда
     * @return bool True если разрешена
     */
    private function isCommandAllowedForRole(string $role, string $command): bool
    {
        if (!isset($this->roles[$role])) {
            $this->logger?->warning('Роль не найдена', ['role' => $role]);
            return false;
        }

        $allowedCommands = $this->roles[$role]['commands'] ?? [];

        // Нормализуем все команды в списке
        $allowedCommands = array_map(
            fn($cmd) => $this->normalizeCommand($cmd),
            $allowedCommands
        );

        return in_array($command, $allowedCommands, true);
    }

    /**
     * Нормализует команду (добавляет / если отсутствует, убирает пробелы)
     *
     * @param string $command Команда
     * @return string Нормализованная команда
     */
    private function normalizeCommand(string $command): string
    {
        $command = trim($command);

        if (!str_starts_with($command, '/')) {
            $command = '/' . $command;
        }

        return $command;
    }

    /**
     * Возвращает сообщение об отказе в доступе
     *
     * @return string Сообщение
     */
    public function getAccessDeniedMessage(): string
    {
        return $this->accessDeniedMessage;
    }

    /**
     * Проверяет, зарегистрирован ли пользователь в системе
     *
     * @param int $chatId ID чата пользователя
     * @return bool True если пользователь есть в списке
     */
    public function isUserRegistered(int $chatId): bool
    {
        $chatIdStr = (string)$chatId;
        return isset($this->users[$chatIdStr]);
    }

    /**
     * Получает список всех ролей
     *
     * @return array<string> Массив названий ролей
     */
    public function getAllRoles(): array
    {
        return array_keys($this->roles);
    }

    /**
     * Получает информацию о роли
     *
     * @param string $role Название роли
     * @return array<string, mixed>|null Данные роли или null
     */
    public function getRoleInfo(string $role): ?array
    {
        return $this->roles[$role] ?? null;
    }

    /**
     * Перезагружает конфигурацию из файлов
     *
     * @param string $configPath Путь к конфигурационному файлу
     * @throws AccessControlException При ошибке загрузки
     */
    public function reload(string $configPath): void
    {
        $this->logger?->info('Перезагрузка конфигурации контроля доступа');
        $this->loadConfig($configPath);
    }
}
