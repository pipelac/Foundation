<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Core;

use App\Component\Logger;
use App\Component\TelegramBot\Entities\Update;
use App\Component\TelegramBot\Utils\Parser;

/**
 * Middleware для обработки команд бота
 * 
 * Упрощает роутинг команд и делает код чище.
 * Поддерживает регистрацию обработчиков для команд и их автоматический вызов.
 */
class CommandMiddleware
{
    /**
     * Зарегистрированные обработчики команд
     * 
     * @var array<string, callable>
     */
    private array $handlers = [];

    /**
     * Обработчик по умолчанию для неизвестных команд
     */
    private ?\Closure $defaultHandler = null;

    /**
     * Обработчик для сообщений, которые не являются командами
     */
    private ?\Closure $messageHandler = null;

    /**
     * Префикс команд (по умолчанию '/')
     */
    private string $commandPrefix = '/';

    /**
     * @param Logger|null $logger Логгер
     */
    public function __construct(
        private readonly ?Logger $logger = null
    ) {
    }

    /**
     * Регистрирует обработчик для команды
     *
     * @param string $command Название команды (без префикса)
     * @param callable $handler Обработчик function(Update $update, array $args): mixed
     * @return self
     */
    public function register(string $command, callable $handler): self
    {
        $command = strtolower(trim($command, $this->commandPrefix));
        $this->handlers[$command] = $handler;
        
        $this->logger?->debug('Зарегистрирована команда', ['command' => $command]);
        
        return $this;
    }

    /**
     * Регистрирует несколько команд с одним обработчиком
     *
     * @param array<string> $commands Список команд
     * @param callable $handler Обработчик
     * @return self
     */
    public function registerMultiple(array $commands, callable $handler): self
    {
        foreach ($commands as $command) {
            $this->register($command, $handler);
        }
        
        return $this;
    }

    /**
     * Регистрирует обработчик по умолчанию для неизвестных команд
     *
     * @param callable $handler Обработчик function(Update $update, string $command): mixed
     * @return self
     */
    public function onUnknownCommand(callable $handler): self
    {
        $this->defaultHandler = $handler(...);
        return $this;
    }

    /**
     * Регистрирует обработчик для обычных сообщений (не команд)
     *
     * @param callable $handler Обработчик function(Update $update): mixed
     * @return self
     */
    public function onMessage(callable $handler): self
    {
        $this->messageHandler = $handler(...);
        return $this;
    }

    /**
     * Обрабатывает обновление
     *
     * @param Update $update Обновление от Telegram
     * @return mixed Результат выполнения обработчика
     */
    public function process(Update $update): mixed
    {
        // Обрабатываем только текстовые сообщения
        if (!$update->isMessage() || !$update->message->text) {
            return null;
        }

        $text = trim($update->message->text);
        
        // Проверяем, является ли это командой
        if (!str_starts_with($text, $this->commandPrefix)) {
            // Это обычное сообщение
            if ($this->messageHandler) {
                $this->logger?->debug('Обработка обычного сообщения');
                return ($this->messageHandler)($update);
            }
            return null;
        }

        // Парсим команду
        $parsed = Parser::parseCommand($text);
        $command = strtolower($parsed['command']);
        $args = $parsed['args'];

        $this->logger?->debug('Обработка команды', [
            'command' => $command,
            'args_count' => count($args),
        ]);

        // Ищем зарегистрированный обработчик
        if (isset($this->handlers[$command])) {
            try {
                return $this->handlers[$command]($update, $args);
            } catch (\Exception $e) {
                $this->logger?->error('Ошибка при выполнении обработчика команды', [
                    'command' => $command,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        // Команда не найдена
        if ($this->defaultHandler) {
            $this->logger?->debug('Вызов обработчика неизвестной команды', ['command' => $command]);
            return ($this->defaultHandler)($update, $command);
        }

        $this->logger?->debug('Команда не обработана', ['command' => $command]);
        return null;
    }

    /**
     * Удаляет обработчик команды
     *
     * @param string $command Название команды
     * @return self
     */
    public function unregister(string $command): self
    {
        $command = strtolower(trim($command, $this->commandPrefix));
        unset($this->handlers[$command]);
        
        $this->logger?->debug('Команда удалена', ['command' => $command]);
        
        return $this;
    }

    /**
     * Проверяет, зарегистрирована ли команда
     *
     * @param string $command Название команды
     * @return bool True если команда зарегистрирована
     */
    public function hasCommand(string $command): bool
    {
        $command = strtolower(trim($command, $this->commandPrefix));
        return isset($this->handlers[$command]);
    }

    /**
     * Получает список всех зарегистрированных команд
     *
     * @return array<string> Список команд
     */
    public function getCommands(): array
    {
        return array_keys($this->handlers);
    }

    /**
     * Устанавливает префикс команд
     *
     * @param string $prefix Префикс (например, '/' или '!')
     * @return self
     */
    public function setCommandPrefix(string $prefix): self
    {
        $this->commandPrefix = $prefix;
        return $this;
    }

    /**
     * Очищает все зарегистрированные обработчики
     *
     * @return self
     */
    public function clear(): self
    {
        $this->handlers = [];
        $this->defaultHandler = null;
        $this->messageHandler = null;
        
        $this->logger?->debug('Все обработчики команд очищены');
        
        return $this;
    }

    /**
     * Создаёт обработчик с группировкой команд
     * 
     * Полезно для организации команд по категориям
     *
     * @param string $prefix Префикс группы (например, 'admin_')
     * @param array<string, callable> $commands Массив [команда => обработчик]
     * @return self
     */
    public function group(string $prefix, array $commands): self
    {
        foreach ($commands as $command => $handler) {
            $fullCommand = $prefix . $command;
            $this->register($fullCommand, $handler);
        }
        
        return $this;
    }
}
