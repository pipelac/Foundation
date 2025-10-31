<?php

declare(strict_types=1);

namespace App\Component;

use App\Component\Exception\NetworkUtilException;
use App\Component\Exception\NetworkUtilValidationException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Класс для выполнения системных сетевых утилит через Symfony Process Component
 * с поддержкой логирования, таймаутов и обработки ошибок
 */
class NetworkUtil
{
    /**
     * Таймаут по умолчанию для команд (секунды)
     */
    private const DEFAULT_TIMEOUT = 30;

    /**
     * Таймаут для длительных операций (секунды)
     */
    private const LONG_TIMEOUT = 120;

    /**
     * Таймаут для очень длительных операций вроде tcpdump (секунды)
     */
    private const VERY_LONG_TIMEOUT = 300;

    private ?Logger $logger;
    private int $defaultTimeout;
    private bool $throwOnError;

    /**
     * Конструктор класса NetworkUtil
     *
     * @param array<string, mixed> $config Конфигурация:
     *   - default_timeout: Таймаут по умолчанию в секундах (int, по умолчанию 30)
     *   - throw_on_error: Выбрасывать исключение при ошибке (bool, по умолчанию true)
     * @param Logger|null $logger Инстанс логгера для записи операций
     */
    public function __construct(array $config = [], ?Logger $logger = null)
    {
        $this->logger = $logger;
        $this->defaultTimeout = $config['default_timeout'] ?? self::DEFAULT_TIMEOUT;
        $this->throwOnError = $config['throw_on_error'] ?? true;

        if ($this->defaultTimeout < 1) {
            throw new NetworkUtilValidationException('Таймаут должен быть >= 1 секунды');
        }

        $this->log('INFO', 'NetworkUtil инициализирован', [
            'default_timeout' => $this->defaultTimeout,
            'throw_on_error' => $this->throwOnError,
        ]);
    }

    /**
     * Выполняет ping проверку доступности хоста
     *
     * @param string $host Хост или IP-адрес для проверки
     * @param int $count Количество пакетов для отправки (по умолчанию 4)
     * @param int|null $timeout Таймаут в секундах (null = default timeout)
     * @return array<string, mixed> Результат выполнения:
     *   - success: Успешность выполнения (bool)
     *   - output: Вывод команды (string)
     *   - error: Сообщение об ошибке, если есть (string|null)
     *   - exit_code: Код возврата (int)
     *   - duration: Время выполнения в секундах (float)
     * @throws NetworkUtilValidationException Если параметры некорректны
     * @throws NetworkUtilException Если команда завершилась с ошибкой и throw_on_error=true
     */
    public function ping(string $host, int $count = 4, ?int $timeout = null): array
    {
        $this->validateHost($host);

        if ($count < 1 || $count > 100) {
            throw new NetworkUtilValidationException('Количество пакетов должно быть от 1 до 100');
        }

        $command = ['ping', '-c', (string)$count, $host];

        return $this->executeCommand('ping', $command, $timeout);
    }

    /**
     * Выполняет nmap сканирование портов хоста
     *
     * @param string $host Хост или IP-адрес для сканирования
     * @param string|null $ports Диапазон портов (например, '80,443' или '1-1000')
     * @param array<int, string> $options Дополнительные опции nmap (например, ['-sV', '-O'])
     * @param int|null $timeout Таймаут в секундах (null = long timeout)
     * @return array<string, mixed> Результат выполнения
     * @throws NetworkUtilValidationException Если параметры некорректны
     * @throws NetworkUtilException Если команда завершилась с ошибкой и throw_on_error=true
     */
    public function nmap(string $host, ?string $ports = null, array $options = [], ?int $timeout = null): array
    {
        $this->validateHost($host);

        $command = ['nmap'];

        if ($ports !== null && $ports !== '') {
            $command[] = '-p';
            $command[] = $ports;
        }

        foreach ($options as $option) {
            if (!is_string($option) || trim($option) === '') {
                throw new NetworkUtilValidationException('Опции nmap должны быть непустыми строками');
            }
            $command[] = $option;
        }

        $command[] = $host;

        return $this->executeCommand('nmap', $command, $timeout ?? self::LONG_TIMEOUT);
    }

    /**
     * Выполняет traceroute - трассировку маршрута до хоста
     *
     * @param string $host Хост или IP-адрес для трассировки
     * @param int $maxHops Максимальное количество хопов (по умолчанию 30)
     * @param int|null $timeout Таймаут в секундах (null = default timeout)
     * @return array<string, mixed> Результат выполнения
     * @throws NetworkUtilValidationException Если параметры некорректны
     * @throws NetworkUtilException Если команда завершилась с ошибкой и throw_on_error=true
     */
    public function traceroute(string $host, int $maxHops = 30, ?int $timeout = null): array
    {
        $this->validateHost($host);

        if ($maxHops < 1 || $maxHops > 255) {
            throw new NetworkUtilValidationException('Максимальное количество хопов должно быть от 1 до 255');
        }

        $command = ['traceroute', '-m', (string)$maxHops, $host];

        return $this->executeCommand('traceroute', $command, $timeout);
    }

    /**
     * Выполняет mtr - комбинацию ping и traceroute с непрерывным мониторингом
     *
     * @param string $host Хост или IP-адрес для мониторинга
     * @param int $count Количество циклов проверки (по умолчанию 10)
     * @param bool $report Режим отчёта (по умолчанию true)
     * @param int|null $timeout Таймаут в секундах (null = long timeout)
     * @return array<string, mixed> Результат выполнения
     * @throws NetworkUtilValidationException Если параметры некорректны
     * @throws NetworkUtilException Если команда завершилась с ошибкой и throw_on_error=true
     */
    public function mtr(string $host, int $count = 10, bool $report = true, ?int $timeout = null): array
    {
        $this->validateHost($host);

        if ($count < 1 || $count > 1000) {
            throw new NetworkUtilValidationException('Количество циклов должно быть от 1 до 1000');
        }

        $command = ['mtr'];

        if ($report) {
            $command[] = '--report';
            $command[] = '--report-cycles';
            $command[] = (string)$count;
        } else {
            $command[] = '-c';
            $command[] = (string)$count;
        }

        $command[] = $host;

        return $this->executeCommand('mtr', $command, $timeout ?? self::LONG_TIMEOUT);
    }

    /**
     * Выполняет dig - DNS запрос для получения информации о домене
     *
     * @param string $domain Доменное имя для запроса
     * @param string $recordType Тип записи (A, AAAA, MX, NS, TXT, SOA и т.д.)
     * @param string|null $nameserver DNS сервер для запроса (например, '8.8.8.8')
     * @param int|null $timeout Таймаут в секундах (null = default timeout)
     * @return array<string, mixed> Результат выполнения
     * @throws NetworkUtilValidationException Если параметры некорректны
     * @throws NetworkUtilException Если команда завершилась с ошибкой и throw_on_error=true
     */
    public function dig(string $domain, string $recordType = 'A', ?string $nameserver = null, ?int $timeout = null): array
    {
        $this->validateDomain($domain);

        $allowedTypes = ['A', 'AAAA', 'MX', 'NS', 'TXT', 'SOA', 'CNAME', 'PTR', 'SRV', 'CAA', 'ANY'];
        if (!in_array(strtoupper($recordType), $allowedTypes, true)) {
            throw new NetworkUtilValidationException(
                sprintf('Неподдерживаемый тип записи: %s. Допустимые: %s', $recordType, implode(', ', $allowedTypes))
            );
        }

        $command = ['dig'];

        if ($nameserver !== null && $nameserver !== '') {
            $this->validateHost($nameserver);
            $command[] = '@' . $nameserver;
        }

        $command[] = $domain;
        $command[] = strtoupper($recordType);

        return $this->executeCommand('dig', $command, $timeout);
    }

    /**
     * Выполняет nslookup - DNS запрос для разрешения имён
     *
     * @param string $host Хост или IP-адрес для запроса
     * @param string|null $nameserver DNS сервер для запроса (например, '8.8.8.8')
     * @param int|null $timeout Таймаут в секундах (null = default timeout)
     * @return array<string, mixed> Результат выполнения
     * @throws NetworkUtilValidationException Если параметры некорректны
     * @throws NetworkUtilException Если команда завершилась с ошибкой и throw_on_error=true
     */
    public function nslookup(string $host, ?string $nameserver = null, ?int $timeout = null): array
    {
        $this->validateHost($host);

        $command = ['nslookup', $host];

        if ($nameserver !== null && $nameserver !== '') {
            $this->validateHost($nameserver);
            $command[] = $nameserver;
        }

        return $this->executeCommand('nslookup', $command, $timeout);
    }

    /**
     * Выполняет whois запрос для получения информации о домене или IP-адресе
     *
     * @param string $target Домен или IP-адрес для запроса
     * @param int|null $timeout Таймаут в секундах (null = default timeout)
     * @return array<string, mixed> Результат выполнения
     * @throws NetworkUtilValidationException Если параметры некорректны
     * @throws NetworkUtilException Если команда завершилась с ошибкой и throw_on_error=true
     */
    public function whois(string $target, ?int $timeout = null): array
    {
        if (trim($target) === '') {
            throw new NetworkUtilValidationException('Целевой объект whois не может быть пустым');
        }

        $command = ['whois', $target];

        return $this->executeCommand('whois', $command, $timeout);
    }

    /**
     * Выполняет netstat - вывод статистики сетевых соединений и открытых портов
     *
     * @param array<int, string> $options Опции netstat (например, ['-tuln'], ['-a'])
     * @param int|null $timeout Таймаут в секундах (null = default timeout)
     * @return array<string, mixed> Результат выполнения
     * @throws NetworkUtilValidationException Если параметры некорректны
     * @throws NetworkUtilException Если команда завершилась с ошибкой и throw_on_error=true
     */
    public function netstat(array $options = ['-tuln'], ?int $timeout = null): array
    {
        $command = ['netstat'];

        foreach ($options as $option) {
            if (!is_string($option) || trim($option) === '') {
                throw new NetworkUtilValidationException('Опции netstat должны быть непустыми строками');
            }
            $command[] = $option;
        }

        return $this->executeCommand('netstat', $command, $timeout);
    }

    /**
     * Выполняет curl запрос для проверки HTTP/HTTPS доступности
     *
     * @param string $url URL для проверки
     * @param array<int, string> $options Дополнительные опции curl (например, ['-I', '-L'])
     * @param int|null $timeout Таймаут в секундах (null = default timeout)
     * @return array<string, mixed> Результат выполнения
     * @throws NetworkUtilValidationException Если параметры некорректны
     * @throws NetworkUtilException Если команда завершилась с ошибкой и throw_on_error=true
     */
    public function curl(string $url, array $options = [], ?int $timeout = null): array
    {
        $this->validateUrl($url);

        $command = ['curl', '-s', '-S'];

        foreach ($options as $option) {
            if (!is_string($option) || trim($option) === '') {
                throw new NetworkUtilValidationException('Опции curl должны быть непустыми строками');
            }
            $command[] = $option;
        }

        $command[] = $url;

        return $this->executeCommand('curl', $command, $timeout);
    }

    /**
     * Выполняет wget запрос для проверки доступности и загрузки контента
     *
     * @param string $url URL для проверки или загрузки
     * @param array<int, string> $options Дополнительные опции wget (например, ['--spider', '-q'])
     * @param int|null $timeout Таймаут в секундах (null = default timeout)
     * @return array<string, mixed> Результат выполнения
     * @throws NetworkUtilValidationException Если параметры некорректны
     * @throws NetworkUtilException Если команда завершилась с ошибкой и throw_on_error=true
     */
    public function wget(string $url, array $options = ['--spider', '-q'], ?int $timeout = null): array
    {
        $this->validateUrl($url);

        $command = ['wget'];

        foreach ($options as $option) {
            if (!is_string($option) || trim($option) === '') {
                throw new NetworkUtilValidationException('Опции wget должны быть непустыми строками');
            }
            $command[] = $option;
        }

        $command[] = $url;

        return $this->executeCommand('wget', $command, $timeout);
    }

    /**
     * Выполняет tcpdump - захват сетевого трафика
     *
     * @param string $interface Сетевой интерфейс (например, 'eth0', 'any')
     * @param int $count Количество пакетов для захвата
     * @param string|null $filter Фильтр BPF (например, 'port 80', 'host 192.168.1.1')
     * @param int|null $timeout Таймаут в секундах (null = very long timeout)
     * @return array<string, mixed> Результат выполнения
     * @throws NetworkUtilValidationException Если параметры некорректны
     * @throws NetworkUtilException Если команда завершилась с ошибкой и throw_on_error=true
     */
    public function tcpdump(string $interface, int $count = 10, ?string $filter = null, ?int $timeout = null): array
    {
        if (trim($interface) === '') {
            throw new NetworkUtilValidationException('Интерфейс не может быть пустым');
        }

        if ($count < 1 || $count > 10000) {
            throw new NetworkUtilValidationException('Количество пакетов должно быть от 1 до 10000');
        }

        $command = ['tcpdump', '-i', $interface, '-c', (string)$count];

        if ($filter !== null && $filter !== '') {
            $command[] = $filter;
        }

        return $this->executeCommand('tcpdump', $command, $timeout ?? self::VERY_LONG_TIMEOUT);
    }

    /**
     * Выполняет ss - вывод информации о сокетах (современная замена netstat)
     *
     * @param array<int, string> $options Опции ss (например, ['-tuln'], ['-s'])
     * @param int|null $timeout Таймаут в секундах (null = default timeout)
     * @return array<string, mixed> Результат выполнения
     * @throws NetworkUtilValidationException Если параметры некорректны
     * @throws NetworkUtilException Если команда завершилась с ошибкой и throw_on_error=true
     */
    public function ss(array $options = ['-tuln'], ?int $timeout = null): array
    {
        $command = ['ss'];

        foreach ($options as $option) {
            if (!is_string($option) || trim($option) === '') {
                throw new NetworkUtilValidationException('Опции ss должны быть непустыми строками');
            }
            $command[] = $option;
        }

        return $this->executeCommand('ss', $command, $timeout);
    }

    /**
     * Выполняет ip - вывод информации о сетевых интерфейсах и маршрутизации
     *
     * @param array<int, string> $options Опции ip (например, ['addr', 'show'], ['route', 'show'])
     * @param int|null $timeout Таймаут в секундах (null = default timeout)
     * @return array<string, mixed> Результат выполнения
     * @throws NetworkUtilValidationException Если параметры некорректны
     * @throws NetworkUtilException Если команда завершилась с ошибкой и throw_on_error=true
     */
    public function ip(array $options = ['addr', 'show'], ?int $timeout = null): array
    {
        $command = ['ip'];

        foreach ($options as $option) {
            if (!is_string($option) || trim($option) === '') {
                throw new NetworkUtilValidationException('Опции ip должны быть непустыми строками');
            }
            $command[] = $option;
        }

        return $this->executeCommand('ip', $command, $timeout);
    }

    /**
     * Выполняет ifconfig - вывод информации о сетевых интерфейсах (legacy)
     *
     * @param string|null $interface Конкретный интерфейс (null = все интерфейсы)
     * @param int|null $timeout Таймаут в секундах (null = default timeout)
     * @return array<string, mixed> Результат выполнения
     * @throws NetworkUtilException Если команда завершилась с ошибкой и throw_on_error=true
     */
    public function ifconfig(?string $interface = null, ?int $timeout = null): array
    {
        $command = ['ifconfig'];

        if ($interface !== null && $interface !== '') {
            $command[] = $interface;
        }

        return $this->executeCommand('ifconfig', $command, $timeout);
    }

    /**
     * Выполняет arp - вывод таблицы ARP
     *
     * @param array<int, string> $options Опции arp (например, ['-a'], ['-n'])
     * @param int|null $timeout Таймаут в секундах (null = default timeout)
     * @return array<string, mixed> Результат выполнения
     * @throws NetworkUtilValidationException Если параметры некорректны
     * @throws NetworkUtilException Если команда завершилась с ошибкой и throw_on_error=true
     */
    public function arp(array $options = ['-a'], ?int $timeout = null): array
    {
        $command = ['arp'];

        foreach ($options as $option) {
            if (!is_string($option) || trim($option) === '') {
                throw new NetworkUtilValidationException('Опции arp должны быть непустыми строками');
            }
            $command[] = $option;
        }

        return $this->executeCommand('arp', $command, $timeout);
    }

    /**
     * Выполняет host - простой DNS lookup
     *
     * @param string $hostname Хост для запроса
     * @param string|null $nameserver DNS сервер (null = системный)
     * @param int|null $timeout Таймаут в секундах (null = default timeout)
     * @return array<string, mixed> Результат выполнения
     * @throws NetworkUtilValidationException Если параметры некорректны
     * @throws NetworkUtilException Если команда завершилась с ошибкой и throw_on_error=true
     */
    public function host(string $hostname, ?string $nameserver = null, ?int $timeout = null): array
    {
        $this->validateHost($hostname);

        $command = ['host', $hostname];

        if ($nameserver !== null && $nameserver !== '') {
            $this->validateHost($nameserver);
            $command[] = $nameserver;
        }

        return $this->executeCommand('host', $command, $timeout);
    }

    /**
     * Выполняет произвольную сетевую команду
     *
     * @param string $commandName Имя команды для логирования
     * @param array<int, string> $command Массив с командой и аргументами
     * @param int|null $timeout Таймаут в секундах (null = default timeout)
     * @return array<string, mixed> Результат выполнения
     * @throws NetworkUtilException Если команда завершилась с ошибкой и throw_on_error=true
     */
    public function executeCustomCommand(string $commandName, array $command, ?int $timeout = null): array
    {
        if (empty($command)) {
            throw new NetworkUtilValidationException('Команда не может быть пустой');
        }

        return $this->executeCommand($commandName, $command, $timeout);
    }

    /**
     * Выполняет команду через Symfony Process
     *
     * @param string $commandName Имя команды для логирования
     * @param array<int, string> $command Массив с командой и аргументами
     * @param int|null $timeout Таймаут в секундах (null = default timeout)
     * @return array<string, mixed> Результат выполнения
     * @throws NetworkUtilException Если команда завершилась с ошибкой и throw_on_error=true
     */
    private function executeCommand(string $commandName, array $command, ?int $timeout = null): array
    {
        $timeout = $timeout ?? $this->defaultTimeout;
        $startTime = microtime(true);

        $this->log('INFO', sprintf('Выполнение команды: %s', $commandName), [
            'command' => implode(' ', $command),
            'timeout' => $timeout,
        ]);

        try {
            $process = new Process($command);
            $process->setTimeout((float)$timeout);
            $process->run();

            $duration = microtime(true) - $startTime;
            $exitCode = $process->getExitCode() ?? -1;
            $output = $process->getOutput();
            $errorOutput = $process->getErrorOutput();

            $success = $exitCode === 0;

            $result = [
                'success' => $success,
                'output' => $output,
                'error' => $errorOutput !== '' ? $errorOutput : null,
                'exit_code' => $exitCode,
                'duration' => round($duration, 3),
                'command' => $commandName,
            ];

            if ($success) {
                $this->log('INFO', sprintf('Команда %s успешно выполнена', $commandName), [
                    'duration' => $result['duration'],
                    'exit_code' => $exitCode,
                ]);
            } else {
                $this->log('ERROR', sprintf('Команда %s завершилась с ошибкой', $commandName), [
                    'duration' => $result['duration'],
                    'exit_code' => $exitCode,
                    'error' => $errorOutput,
                ]);

                if ($this->throwOnError) {
                    throw new NetworkUtilException(
                        sprintf('Команда %s завершилась с кодом %d: %s', $commandName, $exitCode, $errorOutput)
                    );
                }
            }

            return $result;
        } catch (ProcessFailedException $e) {
            $duration = microtime(true) - $startTime;

            $this->log('CRITICAL', sprintf('Критическая ошибка выполнения команды: %s', $commandName), [
                'error' => $e->getMessage(),
                'duration' => round($duration, 3),
            ]);

            if ($this->throwOnError) {
                throw new NetworkUtilException(
                    sprintf('Не удалось выполнить команду %s: %s', $commandName, $e->getMessage()),
                    0,
                    $e
                );
            }

            return [
                'success' => false,
                'output' => '',
                'error' => $e->getMessage(),
                'exit_code' => -1,
                'duration' => round($duration, 3),
                'command' => $commandName,
            ];
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;

            $this->log('CRITICAL', sprintf('Неожиданная ошибка при выполнении команды: %s', $commandName), [
                'error' => $e->getMessage(),
                'duration' => round($duration, 3),
            ]);

            if ($this->throwOnError) {
                throw new NetworkUtilException(
                    sprintf('Неожиданная ошибка при выполнении команды %s: %s', $commandName, $e->getMessage()),
                    0,
                    $e
                );
            }

            return [
                'success' => false,
                'output' => '',
                'error' => $e->getMessage(),
                'exit_code' => -1,
                'duration' => round($duration, 3),
                'command' => $commandName,
            ];
        }
    }

    /**
     * Валидация хоста или IP-адреса
     *
     * @param string $host Хост для валидации
     * @throws NetworkUtilValidationException Если хост некорректен
     */
    private function validateHost(string $host): void
    {
        if (trim($host) === '') {
            throw new NetworkUtilValidationException('Хост не может быть пустым');
        }

        // Проверка на опасные символы
        if (preg_match('/[;&|`$]/', $host)) {
            throw new NetworkUtilValidationException('Хост содержит запрещённые символы');
        }
    }

    /**
     * Валидация доменного имени
     *
     * @param string $domain Домен для валидации
     * @throws NetworkUtilValidationException Если домен некорректен
     */
    private function validateDomain(string $domain): void
    {
        if (trim($domain) === '') {
            throw new NetworkUtilValidationException('Домен не может быть пустым');
        }

        // Проверка на опасные символы
        if (preg_match('/[;&|`$]/', $domain)) {
            throw new NetworkUtilValidationException('Домен содержит запрещённые символы');
        }
    }

    /**
     * Валидация URL
     *
     * @param string $url URL для валидации
     * @throws NetworkUtilValidationException Если URL некорректен
     */
    private function validateUrl(string $url): void
    {
        if (trim($url) === '') {
            throw new NetworkUtilValidationException('URL не может быть пустым');
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new NetworkUtilValidationException('Некорректный формат URL');
        }
    }

    /**
     * Логирование операций
     *
     * @param string $level Уровень логирования
     * @param string $message Сообщение
     * @param array<string, mixed> $context Контекст
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->log($level, $message, $context);
        }
    }
}
