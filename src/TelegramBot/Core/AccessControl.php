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
     * Динамическое сообщение об отказе (формируется во время проверки)
     */
    private ?string $dynamicAccessDeniedMessage = null;

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
     * Проверка подписки на каналы
     */
    private ?ChannelSubscriptionChecker $subscriptionChecker = null;

    /**
     * @param string $configPath Путь к конфигурационному файлу
     * @param Logger|null $logger Логгер
     * @param TelegramAPI|null $api API клиент (для проверки подписки на каналы)
     * @throws AccessControlException При ошибке загрузки конфигурации
     */
    public function __construct(
        string $configPath,
        private readonly ?Logger $logger = null,
        private readonly ?TelegramAPI $api = null,
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
            $this->dynamicAccessDeniedMessage = null;
            $this->subscriptionChecker = null;

            if ($this->enabled) {
                $usersFile = $config['users_file'] ?? null;
                $rolesFile = $config['roles_file'] ?? null;

                if (!$usersFile || !$rolesFile) {
                    throw new AccessControlException('В конфигурации не указаны users_file или roles_file');
                }

                $this->users = $this->loadJsonFile($usersFile);
                $this->roles = $this->loadJsonFile($rolesFile);

                // Инициализация проверки подписки на каналы (если указана конфигурация)
                if (isset($config['channel_subscription'])) {
                    $subscriptionConfig = $config['channel_subscription'];

                    if (($subscriptionConfig['enabled'] ?? false) === true && $this->api === null) {
                        throw new AccessControlException('Для проверки подписки на каналы необходимо передать экземпляр TelegramAPI в AccessControl.');
                    }

                    if ($this->api !== null) {
                        $this->subscriptionChecker = new ChannelSubscriptionChecker(
                            $this->api,
                            $subscriptionConfig,
                            $this->logger
                        );
                    }
                }

                $this->logger?->info('Контроль доступа TelegramBot активирован', [
                    'users_count' => count($this->users),
                    'roles_count' => count($this->roles),
                    'subscription_check_enabled' => $this->subscriptionChecker?->isEnabled() ?? false,
                ]);
            } else {
                $this->users = [];
                $this->roles = [];
                $this->subscriptionChecker = null;
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
        // Сбрасываем динамическое сообщение
        $this->dynamicAccessDeniedMessage = null;

        // Если контроль доступа выключен - разрешаем все
        if (!$this->enabled) {
            return true;
        }

        // Проверяем подписку на каналы (если включена проверка)
        if ($this->subscriptionChecker !== null && $this->subscriptionChecker->isEnabled()) {
            $subscribed = $this->subscriptionChecker->checkSubscription($chatId);
            
            if (!$subscribed) {
                // Формируем сообщение об отказе с указанием каналов
                $channels = $this->subscriptionChecker->formatChannelsList();
                $mode = $this->subscriptionChecker->getMode();
                
                $modeText = match ($mode) {
                    ChannelSubscriptionChecker::MODE_ALL => 'все каналы',
                    ChannelSubscriptionChecker::MODE_ANY => 'хотя бы на один из каналов',
                    default => 'канал',
                };
                
                $this->dynamicAccessDeniedMessage = $this->subscriptionChecker->getAccessDeniedMessage();
                $this->dynamicAccessDeniedMessage .= "\n\nНеобходимо подписаться на {$modeText}:\n{$channels}";
                
                $this->logger?->info('Доступ запрещен: не подписан на каналы', [
                    'chat_id' => $chatId,
                    'command' => $command,
                    'mode' => $mode,
                ]);
                
                return false;
            }
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
        return $this->dynamicAccessDeniedMessage ?? $this->accessDeniedMessage;
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

    /**
     * Проверяет, может ли роль работать в режиме профилактики
     *
     * @param int $chatId ID чата пользователя
     * @return bool True если роль может работать в режиме профилактики
     */
    public function canIgnoreReconstructionMode(int $chatId): bool
    {
        if (!$this->enabled) {
            return true;
        }

        $role = $this->getUserRole($chatId);
        $roleInfo = $this->getRoleInfo($role);

        if (!$roleInfo) {
            return false;
        }

        $reconstructionModeIgnore = $roleInfo['reconstructionModeIgnore'] ?? 'no';

        return strtolower($reconstructionModeIgnore) === 'yes';
    }

    /**
     * Проверяет, нужно ли отправлять беззвучное уведомление для пользователя
     *
     * @param int $chatId ID чата пользователя
     * @param \DateTimeInterface|null $time Время для проверки (по умолчанию текущее)
     * @return bool True если нужно отправить беззвучное уведомление
     */
    public function shouldDisableSoundNotification(int $chatId, ?\DateTimeInterface $time = null): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $role = $this->getUserRole($chatId);
        $roleInfo = $this->getRoleInfo($role);

        if (!$roleInfo) {
            return false;
        }

        $disableSoundRange = $roleInfo['disable_sound_notification'] ?? null;

        if ($disableSoundRange === null || empty($disableSoundRange)) {
            return false;
        }

        return $this->isTimeInRange($disableSoundRange, $time);
    }

    /**
     * Проверяет, находится ли время в указанном диапазоне
     *
     * @param string $range Диапазон времени в формате "HH:MM-HH:MM"
     * @param \DateTimeInterface|null $time Время для проверки (по умолчанию текущее)
     * @return bool True если время в диапазоне
     */
    private function isTimeInRange(string $range, ?\DateTimeInterface $time = null): bool
    {
        if ($time === null) {
            $time = new \DateTime();
        }

        // Парсинг диапазона "HH:MM-HH:MM"
        $parts = explode('-', $range);
        
        if (count($parts) !== 2) {
            $this->logger?->warning('Некорректный формат диапазона времени', [
                'range' => $range,
            ]);
            return false;
        }

        try {
            $startParts = explode(':', trim($parts[0]));
            $endParts = explode(':', trim($parts[1]));

            if (count($startParts) !== 2 || count($endParts) !== 2) {
                throw new \Exception('Неверный формат времени');
            }

            $startHour = (int)$startParts[0];
            $startMinute = (int)$startParts[1];
            $endHour = (int)$endParts[0];
            $endMinute = (int)$endParts[1];

            // Создаем объекты времени для сравнения
            $currentTime = (int)$time->format('H') * 60 + (int)$time->format('i');
            $startTime = $startHour * 60 + $startMinute;
            $endTime = $endHour * 60 + $endMinute;

            // Проверка диапазона через полночь (например, 22:00-09:00)
            if ($startTime > $endTime) {
                return $currentTime >= $startTime || $currentTime <= $endTime;
            } else {
                return $currentTime >= $startTime && $currentTime <= $endTime;
            }
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка парсинга диапазона времени', [
                'range' => $range,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Получает параметр reconstructionModeIgnore для роли пользователя
     *
     * @param int $chatId ID чата пользователя
     * @return string "yes" или "no"
     */
    public function getReconstructionModeIgnore(int $chatId): string
    {
        $role = $this->getUserRole($chatId);
        $roleInfo = $this->getRoleInfo($role);

        if (!$roleInfo) {
            return 'no';
        }

        return $roleInfo['reconstructionModeIgnore'] ?? 'no';
    }

    /**
     * Получает параметр disable_sound_notification для роли пользователя
     *
     * @param int $chatId ID чата пользователя
     * @return string|null Диапазон времени или null
     */
    public function getDisableSoundNotification(int $chatId): ?string
    {
        $role = $this->getUserRole($chatId);
        $roleInfo = $this->getRoleInfo($role);

        if (!$roleInfo) {
            return null;
        }

        return $roleInfo['disable_sound_notification'] ?? null;
    }

    /**
     * Получает объект проверки подписки на каналы
     *
     * @return ChannelSubscriptionChecker|null
     */
    public function getSubscriptionChecker(): ?ChannelSubscriptionChecker
    {
        return $this->subscriptionChecker;
    }

    /**
     * Проверяет, включена ли проверка подписки на каналы
     *
     * @return bool True если включена
     */
    public function isSubscriptionCheckEnabled(): bool
    {
        return $this->subscriptionChecker !== null && $this->subscriptionChecker->isEnabled();
    }

    /**
     * Получает детальную информацию о подписках пользователя на каналы
     *
     * @param int $chatId ID чата пользователя
     * @return array<string, bool>|null Массив [channel => is_subscribed] или null если проверка отключена
     */
    public function getUserSubscriptionDetails(int $chatId): ?array
    {
        if ($this->subscriptionChecker === null) {
            return null;
        }

        return $this->subscriptionChecker->getSubscriptionDetails($chatId);
    }

    /**
     * Очищает кеш проверки подписки
     *
     * @param int|null $chatId ID чата пользователя (null - очистить весь кеш)
     */
    public function clearSubscriptionCache(?int $chatId = null): void
    {
        $this->subscriptionChecker?->clearCache($chatId);
    }
}
