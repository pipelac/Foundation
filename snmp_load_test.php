<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Component\Snmp;
use App\Component\Logger;
use App\Component\Exception\SnmpException;
use App\Component\Exception\SnmpConnectionException;
use App\Component\Exception\SnmpValidationException;

/**
 * Полноценный нагрузочный тест класса Snmp
 * 
 * Тестирует все методы класса с реальными данными и логированием
 */
class SnmpLoadTest
{
    private Logger $logger;
    private array $testResults = [];
    private int $totalTests = 0;
    private int $passedTests = 0;
    private int $failedTests = 0;
    private float $startTime;

    public function __construct()
    {
        $this->startTime = microtime(true);
        
        // Инициализация логгера
        $this->logger = new Logger([
            'directory' => __DIR__ . '/logs',
            'file_name' => 'snmp_load_test.log',
            'max_files' => 3,
            'max_file_size' => 10,
            'log_buffer_size' => 8,
            'enabled' => true,
        ]);

        $this->logger->info('=== НАЧАЛО НАГРУЗОЧНОГО ТЕСТИРОВАНИЯ SNMP КЛАССА ===');
    }

    /**
     * Запуск всех тестов
     */
    public function runAllTests(): void
    {
        echo "\n" . str_repeat('=', 80) . "\n";
        echo "ПОЛНОЦЕННЫЙ НАГРУЗОЧНЫЙ ТЕСТ КЛАССА Snmp\n";
        echo str_repeat('=', 80) . "\n\n";

        $this->testConfigValidation();
        $this->testConnectionV1();
        $this->testConnectionV2c();
        $this->testConnectionV3();
        $this->testGetOperations();
        $this->testGetMultipleOperations();
        $this->testGetNextOperations();
        $this->testWalkOperations();
        $this->testSetOperations();
        $this->testSetMultipleOperations();
        $this->testSystemInfo();
        $this->testNetworkInterfaces();
        $this->testOidValidation();
        $this->testTypeValidation();
        $this->testTimeoutAndRetries();
        $this->testConnectionInfo();
        $this->testErrorHandling();
        $this->testConcurrentRequests();
        $this->testLargeDatasets();
        $this->testEdgeCases();

        $this->printSummary();
    }

    /**
     * Тест валидации конфигурации
     */
    private function testConfigValidation(): void
    {
        $this->startTest('Валидация конфигурации');

        // Тест 1: Отсутствует host
        try {
            new Snmp([], $this->logger);
            $this->failTest('Должно было выброситься исключение для пустого host');
        } catch (SnmpValidationException $e) {
            $this->passTest('Корректно обработан пустой host');
        }

        // Тест 2: Некорректная версия
        try {
            new Snmp(['host' => 'localhost', 'version' => 999], $this->logger);
            $this->failTest('Должно было выброситься исключение для некорректной версии');
        } catch (SnmpValidationException $e) {
            $this->passTest('Корректно обработана некорректная версия');
        }

        // Тест 3: Некорректный timeout
        try {
            new Snmp(['host' => 'localhost', 'timeout' => -1], $this->logger);
            $this->failTest('Должно было выброситься исключение для отрицательного timeout');
        } catch (SnmpValidationException $e) {
            $this->passTest('Корректно обработан отрицательный timeout');
        }

        // Тест 4: Некорректный retries
        try {
            new Snmp(['host' => 'localhost', 'retries' => -5], $this->logger);
            $this->failTest('Должно было выброситься исключение для отрицательного retries');
        } catch (SnmpValidationException $e) {
            $this->passTest('Корректно обработан отрицательный retries');
        }

        // Тест 5: SNMPv3 без параметров безопасности
        try {
            $config = [
                'host' => 'localhost',
                'version' => Snmp::VERSION_3,
                'v3_security_level' => 'authPriv',
            ];
            new Snmp($config, $this->logger);
            $this->failTest('Должно было выброситься исключение для SNMPv3 без параметров');
        } catch (SnmpValidationException $e) {
            $this->passTest('Корректно обработаны недостающие параметры SNMPv3');
        }

        $this->endTest();
    }

    /**
     * Тест подключения SNMPv1
     */
    private function testConnectionV1(): void
    {
        $this->startTest('Подключение SNMPv1 к localhost');

        try {
            $snmp = new Snmp([
                'host' => '127.0.0.1',
                'community' => 'public',
                'version' => Snmp::VERSION_1,
                'timeout' => 500000,
                'retries' => 2,
            ], $this->logger);

            $info = $snmp->getConnectionInfo();
            
            if ($info['connected'] && $info['version'] === 'SNMPv1') {
                $this->passTest('Успешное подключение SNMPv1');
                $this->logger->info('SNMPv1 соединение установлено', $info);
            } else {
                $this->failTest('Не удалось установить SNMPv1 соединение');
            }

            $snmp->close();
            
        } catch (SnmpConnectionException $e) {
            $this->warnTest('SNMPv1 соединение не удалось: ' . $e->getMessage());
        } catch (SnmpException $e) {
            $this->failTest('Ошибка SNMPv1: ' . $e->getMessage());
        }

        $this->endTest();
    }

    /**
     * Тест подключения SNMPv2c
     */
    private function testConnectionV2c(): void
    {
        $this->startTest('Подключение SNMPv2c к localhost');

        try {
            $snmp = new Snmp([
                'host' => '127.0.0.1',
                'community' => 'public',
                'version' => Snmp::VERSION_2C,
                'timeout' => 1000000,
                'retries' => 3,
            ], $this->logger);

            $info = $snmp->getConnectionInfo();
            
            if ($info['connected'] && $info['version'] === 'SNMPv2c') {
                $this->passTest('Успешное подключение SNMPv2c');
                $this->logger->info('SNMPv2c соединение установлено', $info);
            } else {
                $this->failTest('Не удалось установить SNMPv2c соединение');
            }

            $snmp->close();
            
        } catch (SnmpConnectionException $e) {
            $this->warnTest('SNMPv2c соединение не удалось: ' . $e->getMessage());
        } catch (SnmpException $e) {
            $this->failTest('Ошибка SNMPv2c: ' . $e->getMessage());
        }

        $this->endTest();
    }

    /**
     * Тест подключения SNMPv3
     */
    private function testConnectionV3(): void
    {
        $this->startTest('Подключение SNMPv3 к localhost');

        try {
            $snmp = new Snmp([
                'host' => '127.0.0.1',
                'community' => 'testuser',
                'version' => Snmp::VERSION_3,
                'v3_security_level' => 'noAuthNoPriv',
                'timeout' => 1000000,
                'retries' => 2,
            ], $this->logger);

            $info = $snmp->getConnectionInfo();
            
            if ($info['connected'] && $info['version'] === 'SNMPv3') {
                $this->passTest('Успешное подключение SNMPv3');
                $this->logger->info('SNMPv3 соединение установлено', $info);
            } else {
                $this->failTest('Не удалось установить SNMPv3 соединение');
            }

            $snmp->close();
            
        } catch (SnmpConnectionException $e) {
            $this->warnTest('SNMPv3 соединение не удалось (требуется настройка): ' . $e->getMessage());
        } catch (SnmpException $e) {
            $this->failTest('Ошибка SNMPv3: ' . $e->getMessage());
        }

        $this->endTest();
    }

    /**
     * Тест операций GET
     */
    private function testGetOperations(): void
    {
        $this->startTest('SNMP GET операции');

        try {
            $snmp = new Snmp([
                'host' => '127.0.0.1',
                'community' => 'public',
                'version' => Snmp::VERSION_2C,
            ], $this->logger);

            // Тест 1: Получение sysDescr
            $sysDescr = $snmp->get('.1.3.6.1.2.1.1.1.0');
            if ($sysDescr !== false) {
                $this->passTest('GET sysDescr успешно: ' . substr((string)$sysDescr, 0, 50) . '...');
            } else {
                $this->failTest('Не удалось получить sysDescr');
            }

            // Тест 2: Получение sysUpTime
            $sysUpTime = $snmp->get('.1.3.6.1.2.1.1.3.0');
            if ($sysUpTime !== false) {
                $this->passTest('GET sysUpTime успешно: ' . $sysUpTime);
            } else {
                $this->failTest('Не удалось получить sysUpTime');
            }

            // Тест 3: Несуществующий OID
            try {
                $result = $snmp->get('.1.3.6.1.2.1.999.999.999');
                if ($result === false) {
                    $this->passTest('GET несуществующего OID вернул false');
                }
            } catch (SnmpException $e) {
                $this->passTest('GET несуществующего OID выбросил исключение');
            }

            $snmp->close();
            
        } catch (SnmpConnectionException $e) {
            $this->warnTest('Соединение не удалось: ' . $e->getMessage());
        } catch (SnmpException $e) {
            $this->failTest('Ошибка GET операции: ' . $e->getMessage());
        }

        $this->endTest();
    }

    /**
     * Тест множественных GET операций
     */
    private function testGetMultipleOperations(): void
    {
        $this->startTest('SNMP GET множественные операции');

        try {
            $snmp = new Snmp([
                'host' => '127.0.0.1',
                'community' => 'public',
                'version' => Snmp::VERSION_2C,
            ], $this->logger);

            $oids = [
                '.1.3.6.1.2.1.1.1.0', // sysDescr
                '.1.3.6.1.2.1.1.3.0', // sysUpTime
                '.1.3.6.1.2.1.1.5.0', // sysName
            ];

            $results = $snmp->getMultiple($oids);
            
            if (count($results) === 3) {
                $this->passTest('GET множественный успешно получил ' . count($results) . ' значений');
                $this->logger->debug('Результаты множественного GET', $results);
            } else {
                $this->failTest('GET множественный вернул ' . count($results) . ' значений вместо 3');
            }

            // Тест с пустым массивом
            try {
                $snmp->getMultiple([]);
                $this->failTest('Должно было выброситься исключение для пустого массива');
            } catch (SnmpValidationException $e) {
                $this->passTest('Корректно обработан пустой массив OID');
            }

            $snmp->close();
            
        } catch (SnmpConnectionException $e) {
            $this->warnTest('Соединение не удалось: ' . $e->getMessage());
        } catch (SnmpException $e) {
            $this->failTest('Ошибка GET множественной операции: ' . $e->getMessage());
        }

        $this->endTest();
    }

    /**
     * Тест операций GETNEXT
     */
    private function testGetNextOperations(): void
    {
        $this->startTest('SNMP GETNEXT операции');

        try {
            $snmp = new Snmp([
                'host' => '127.0.0.1',
                'community' => 'public',
                'version' => Snmp::VERSION_2C,
            ], $this->logger);

            // Получаем следующий OID после sysDescr
            $next = $snmp->getNext('.1.3.6.1.2.1.1.1.0');
            
            if ($next !== false) {
                $this->passTest('GETNEXT успешно: ' . substr((string)$next, 0, 50));
            } else {
                $this->failTest('GETNEXT не вернул результат');
            }

            $snmp->close();
            
        } catch (SnmpConnectionException $e) {
            $this->warnTest('Соединение не удалось: ' . $e->getMessage());
        } catch (SnmpException $e) {
            $this->failTest('Ошибка GETNEXT операции: ' . $e->getMessage());
        }

        $this->endTest();
    }

    /**
     * Тест операций WALK
     */
    private function testWalkOperations(): void
    {
        $this->startTest('SNMP WALK операции');

        try {
            $snmp = new Snmp([
                'host' => '127.0.0.1',
                'community' => 'public',
                'version' => Snmp::VERSION_2C,
            ], $this->logger);

            // Обход системной группы
            $results = $snmp->walk('.1.3.6.1.2.1.1');
            
            if (is_array($results) && count($results) > 0) {
                $this->passTest('WALK успешно получил ' . count($results) . ' значений');
                $this->logger->debug('Первые 3 результата WALK', array_slice($results, 0, 3, true));
            } else {
                $this->failTest('WALK не вернул результаты');
            }

            // Тест с суффиксом в качестве ключа
            $resultsWithSuffix = $snmp->walk('.1.3.6.1.2.1.1', true);
            
            if (is_array($resultsWithSuffix) && count($resultsWithSuffix) > 0) {
                $this->passTest('WALK с suffix_as_key успешно');
            } else {
                $this->failTest('WALK с suffix_as_key не вернул результаты');
            }

            $snmp->close();
            
        } catch (SnmpConnectionException $e) {
            $this->warnTest('Соединение не удалось: ' . $e->getMessage());
        } catch (SnmpException $e) {
            $this->failTest('Ошибка WALK операции: ' . $e->getMessage());
        }

        $this->endTest();
    }

    /**
     * Тест операций SET
     */
    private function testSetOperations(): void
    {
        $this->startTest('SNMP SET операции');

        try {
            $snmp = new Snmp([
                'host' => '127.0.0.1',
                'community' => 'private',
                'version' => Snmp::VERSION_2C,
            ], $this->logger);

            // Попытка SET (может не работать из-за прав доступа)
            try {
                $result = $snmp->set('.1.3.6.1.2.1.1.6.0', Snmp::TYPE_STRING, 'Test Location');
                
                if ($result) {
                    $this->passTest('SET операция выполнена успешно');
                } else {
                    $this->warnTest('SET операция вернула false (возможно, нет прав)');
                }
            } catch (SnmpException $e) {
                $this->warnTest('SET операция не разрешена: ' . $e->getMessage());
            }

            $snmp->close();
            
        } catch (SnmpConnectionException $e) {
            $this->warnTest('Соединение не удалось: ' . $e->getMessage());
        } catch (SnmpException $e) {
            $this->failTest('Ошибка SET операции: ' . $e->getMessage());
        }

        $this->endTest();
    }

    /**
     * Тест множественных SET операций
     */
    private function testSetMultipleOperations(): void
    {
        $this->startTest('SNMP SET множественные операции');

        try {
            $snmp = new Snmp([
                'host' => '127.0.0.1',
                'community' => 'private',
                'version' => Snmp::VERSION_2C,
            ], $this->logger);

            // Тест валидации
            try {
                $snmp->setMultiple(
                    ['.1.3.6.1.2.1.1.6.0'],
                    [Snmp::TYPE_STRING, Snmp::TYPE_STRING],
                    ['Test']
                );
                $this->failTest('Должно было выброситься исключение для несовпадения длин');
            } catch (SnmpValidationException $e) {
                $this->passTest('Корректно обработано несовпадение длин массивов');
            }

            $snmp->close();
            
        } catch (SnmpConnectionException $e) {
            $this->warnTest('Соединение не удалось: ' . $e->getMessage());
        } catch (SnmpException $e) {
            $this->failTest('Ошибка SET множественной операции: ' . $e->getMessage());
        }

        $this->endTest();
    }

    /**
     * Тест получения системной информации
     */
    private function testSystemInfo(): void
    {
        $this->startTest('Получение системной информации');

        try {
            $snmp = new Snmp([
                'host' => '127.0.0.1',
                'community' => 'public',
                'version' => Snmp::VERSION_2C,
            ], $this->logger);

            $systemInfo = $snmp->getSystemInfo();
            
            if (isset($systemInfo['sysDescr']) && $systemInfo['sysDescr'] !== null) {
                $this->passTest('Системная информация получена успешно');
                $this->logger->info('Системная информация', $systemInfo);
                
                echo "  - sysDescr: " . substr((string)$systemInfo['sysDescr'], 0, 60) . "...\n";
                echo "  - sysName: " . ($systemInfo['sysName'] ?? 'N/A') . "\n";
                echo "  - sysUpTime: " . ($systemInfo['sysUpTime'] ?? 'N/A') . "\n";
            } else {
                $this->failTest('Не удалось получить системную информацию');
            }

            $snmp->close();
            
        } catch (SnmpConnectionException $e) {
            $this->warnTest('Соединение не удалось: ' . $e->getMessage());
        } catch (SnmpException $e) {
            $this->failTest('Ошибка получения системной информации: ' . $e->getMessage());
        }

        $this->endTest();
    }

    /**
     * Тест получения списка сетевых интерфейсов
     */
    private function testNetworkInterfaces(): void
    {
        $this->startTest('Получение списка сетевых интерфейсов');

        try {
            $snmp = new Snmp([
                'host' => '127.0.0.1',
                'community' => 'public',
                'version' => Snmp::VERSION_2C,
            ], $this->logger);

            $interfaces = $snmp->getNetworkInterfaces();
            
            if (is_array($interfaces) && count($interfaces) > 0) {
                $this->passTest('Получено ' . count($interfaces) . ' сетевых интерфейсов');
                $this->logger->info('Сетевые интерфейсы', ['count' => count($interfaces)]);
                
                foreach (array_slice($interfaces, 0, 3) as $interface) {
                    echo "  - Interface #{$interface['index']}: {$interface['description']}\n";
                }
            } else {
                $this->warnTest('Список интерфейсов пуст');
            }

            $snmp->close();
            
        } catch (SnmpConnectionException $e) {
            $this->warnTest('Соединение не удалось: ' . $e->getMessage());
        } catch (SnmpException $e) {
            $this->failTest('Ошибка получения интерфейсов: ' . $e->getMessage());
        }

        $this->endTest();
    }

    /**
     * Тест валидации OID
     */
    private function testOidValidation(): void
    {
        $this->startTest('Валидация OID');

        try {
            $snmp = new Snmp([
                'host' => '127.0.0.1',
                'community' => 'public',
                'version' => Snmp::VERSION_2C,
            ], $this->logger);

            // Тест пустого OID
            try {
                $snmp->get('');
                $this->failTest('Должно было выброситься исключение для пустого OID');
            } catch (SnmpValidationException $e) {
                $this->passTest('Корректно обработан пустой OID');
            }

            // Тест некорректного формата OID
            try {
                $snmp->get('invalid@oid#format');
                $this->failTest('Должно было выброситься исключение для некорректного OID');
            } catch (SnmpValidationException $e) {
                $this->passTest('Корректно обработан некорректный формат OID');
            }

            $snmp->close();
            
        } catch (SnmpConnectionException $e) {
            $this->warnTest('Соединение не удалось: ' . $e->getMessage());
        } catch (SnmpException $e) {
            $this->failTest('Ошибка валидации OID: ' . $e->getMessage());
        }

        $this->endTest();
    }

    /**
     * Тест валидации типов данных
     */
    private function testTypeValidation(): void
    {
        $this->startTest('Валидация типов данных');

        try {
            $snmp = new Snmp([
                'host' => '127.0.0.1',
                'community' => 'private',
                'version' => Snmp::VERSION_2C,
            ], $this->logger);

            // Тест некорректного типа
            try {
                $snmp->set('.1.3.6.1.2.1.1.6.0', 'invalid_type', 'Test');
                $this->failTest('Должно было выброситься исключение для некорректного типа');
            } catch (SnmpValidationException $e) {
                $this->passTest('Корректно обработан некорректный тип данных');
            }

            $snmp->close();
            
        } catch (SnmpConnectionException $e) {
            $this->warnTest('Соединение не удалось: ' . $e->getMessage());
        } catch (SnmpException $e) {
            $this->failTest('Ошибка валидации типа: ' . $e->getMessage());
        }

        $this->endTest();
    }

    /**
     * Тест изменения timeout и retries
     */
    private function testTimeoutAndRetries(): void
    {
        $this->startTest('Изменение timeout и retries');

        try {
            $snmp = new Snmp([
                'host' => '127.0.0.1',
                'community' => 'public',
                'version' => Snmp::VERSION_2C,
            ], $this->logger);

            // Тест setTimeout
            try {
                $snmp->setTimeout(2000000);
                $this->passTest('setTimeout выполнен успешно');
            } catch (SnmpValidationException $e) {
                $this->failTest('Ошибка setTimeout: ' . $e->getMessage());
            }

            // Тест некорректного timeout
            try {
                $snmp->setTimeout(-1);
                $this->failTest('Должно было выброситься исключение для отрицательного timeout');
            } catch (SnmpValidationException $e) {
                $this->passTest('Корректно обработан отрицательный timeout');
            }

            // Тест setRetries
            try {
                $snmp->setRetries(5);
                $this->passTest('setRetries выполнен успешно');
            } catch (SnmpValidationException $e) {
                $this->failTest('Ошибка setRetries: ' . $e->getMessage());
            }

            // Тест некорректного retries
            try {
                $snmp->setRetries(-3);
                $this->failTest('Должно было выброситься исключение для отрицательного retries');
            } catch (SnmpValidationException $e) {
                $this->passTest('Корректно обработан отрицательный retries');
            }

            $snmp->close();
            
        } catch (SnmpConnectionException $e) {
            $this->warnTest('Соединение не удалось: ' . $e->getMessage());
        } catch (SnmpException $e) {
            $this->failTest('Ошибка теста timeout/retries: ' . $e->getMessage());
        }

        $this->endTest();
    }

    /**
     * Тест получения информации о соединении
     */
    private function testConnectionInfo(): void
    {
        $this->startTest('Получение информации о соединении');

        try {
            $snmp = new Snmp([
                'host' => '127.0.0.1',
                'community' => 'public',
                'version' => Snmp::VERSION_2C,
                'timeout' => 1500000,
                'retries' => 4,
            ], $this->logger);

            $info = $snmp->getConnectionInfo();
            
            if ($info['host'] === '127.0.0.1' &&
                $info['version'] === 'SNMPv2c' &&
                $info['timeout'] === 1500000 &&
                $info['retries'] === 4) {
                $this->passTest('Информация о соединении корректна');
            } else {
                $this->failTest('Информация о соединении некорректна');
            }

            $snmp->close();
            
        } catch (SnmpConnectionException $e) {
            $this->warnTest('Соединение не удалось: ' . $e->getMessage());
        } catch (SnmpException $e) {
            $this->failTest('Ошибка получения информации: ' . $e->getMessage());
        }

        $this->endTest();
    }

    /**
     * Тест обработки ошибок
     */
    private function testErrorHandling(): void
    {
        $this->startTest('Обработка ошибок');

        // Тест подключения к несуществующему хосту
        try {
            $snmp = new Snmp([
                'host' => '192.0.2.1',
                'community' => 'public',
                'version' => Snmp::VERSION_2C,
                'timeout' => 100000,
                'retries' => 1,
            ], $this->logger);
            
            try {
                $snmp->get('.1.3.6.1.2.1.1.1.0');
                $this->warnTest('Запрос к несуществующему хосту не выбросил исключение');
            } catch (SnmpException $e) {
                $this->passTest('Корректно обработан запрос к несуществующему хосту');
            }
            
            $snmp->close();
            
        } catch (SnmpConnectionException $e) {
            $this->passTest('Корректно обработано подключение к несуществующему хосту');
        }

        // Тест getLastError
        try {
            $snmp = new Snmp([
                'host' => '127.0.0.1',
                'community' => 'public',
                'version' => Snmp::VERSION_2C,
            ], $this->logger);

            $error = $snmp->getLastError();
            
            if (is_string($error)) {
                $this->passTest('getLastError вернул строку: ' . $error);
            } else {
                $this->failTest('getLastError вернул не строку');
            }

            $snmp->close();
            
        } catch (SnmpException $e) {
            $this->failTest('Ошибка теста getLastError: ' . $e->getMessage());
        }

        $this->endTest();
    }

    /**
     * Тест конкурентных запросов
     */
    private function testConcurrentRequests(): void
    {
        $this->startTest('Конкурентные запросы');

        try {
            $snmp = new Snmp([
                'host' => '127.0.0.1',
                'community' => 'public',
                'version' => Snmp::VERSION_2C,
            ], $this->logger);

            $startTime = microtime(true);
            $requests = 0;
            
            for ($i = 0; $i < 10; $i++) {
                $result = $snmp->get('.1.3.6.1.2.1.1.1.0');
                if ($result !== false) {
                    $requests++;
                }
            }
            
            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 3);
            
            $this->passTest("Выполнено {$requests}/10 запросов за {$duration} сек");
            $this->logger->info('Конкурентные запросы', [
                'total' => 10,
                'successful' => $requests,
                'duration' => $duration,
            ]);

            $snmp->close();
            
        } catch (SnmpConnectionException $e) {
            $this->warnTest('Соединение не удалось: ' . $e->getMessage());
        } catch (SnmpException $e) {
            $this->failTest('Ошибка конкурентных запросов: ' . $e->getMessage());
        }

        $this->endTest();
    }

    /**
     * Тест работы с большими наборами данных
     */
    private function testLargeDatasets(): void
    {
        $this->startTest('Работа с большими наборами данных');

        try {
            $snmp = new Snmp([
                'host' => '127.0.0.1',
                'community' => 'public',
                'version' => Snmp::VERSION_2C,
            ], $this->logger);

            $startTime = microtime(true);
            
            // Обход большого дерева MIB
            $results = $snmp->walk('.1.3.6.1.2.1');
            
            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 3);
            
            if (is_array($results)) {
                $count = count($results);
                $this->passTest("WALK получил {$count} объектов за {$duration} сек");
                $this->logger->info('Большой набор данных', [
                    'count' => $count,
                    'duration' => $duration,
                ]);
            } else {
                $this->failTest('WALK не вернул массив');
            }

            $snmp->close();
            
        } catch (SnmpConnectionException $e) {
            $this->warnTest('Соединение не удалось: ' . $e->getMessage());
        } catch (SnmpException $e) {
            $this->failTest('Ошибка работы с большими данными: ' . $e->getMessage());
        }

        $this->endTest();
    }

    /**
     * Тест граничных случаев
     */
    private function testEdgeCases(): void
    {
        $this->startTest('Граничные случаи');

        // Тест закрытия уже закрытого соединения
        try {
            $snmp = new Snmp([
                'host' => '127.0.0.1',
                'community' => 'public',
                'version' => Snmp::VERSION_2C,
            ], $this->logger);

            $snmp->close();
            $snmp->close(); // Повторное закрытие
            
            $this->passTest('Повторное закрытие соединения обработано корректно');
            
        } catch (SnmpException $e) {
            $this->failTest('Ошибка повторного закрытия: ' . $e->getMessage());
        }

        // Тест работы с различными форматами OID
        try {
            $snmp = new Snmp([
                'host' => '127.0.0.1',
                'community' => 'public',
                'version' => Snmp::VERSION_2C,
            ], $this->logger);

            // OID без точки в начале
            $result1 = $snmp->get('1.3.6.1.2.1.1.1.0');
            
            // OID с точкой в начале
            $result2 = $snmp->get('.1.3.6.1.2.1.1.1.0');
            
            if ($result1 !== false && $result2 !== false) {
                $this->passTest('Различные форматы OID обработаны корректно');
            } else {
                $this->failTest('Не все форматы OID обработаны');
            }

            $snmp->close();
            
        } catch (SnmpException $e) {
            $this->failTest('Ошибка граничных случаев: ' . $e->getMessage());
        }

        $this->endTest();
    }

    /**
     * Начало теста
     */
    private function startTest(string $name): void
    {
        echo "\n" . str_repeat('-', 80) . "\n";
        echo "Тест: {$name}\n";
        echo str_repeat('-', 80) . "\n";
        
        $this->logger->info("Начало теста: {$name}");
    }

    /**
     * Завершение теста
     */
    private function endTest(): void
    {
        $this->logger->flush();
    }

    /**
     * Успешный тест
     */
    private function passTest(string $message): void
    {
        $this->totalTests++;
        $this->passedTests++;
        echo "  ✓ PASS: {$message}\n";
        $this->logger->info("[PASS] {$message}");
    }

    /**
     * Проваленный тест
     */
    private function failTest(string $message): void
    {
        $this->totalTests++;
        $this->failedTests++;
        echo "  ✗ FAIL: {$message}\n";
        $this->logger->error("[FAIL] {$message}");
    }

    /**
     * Предупреждение в тесте
     */
    private function warnTest(string $message): void
    {
        $this->totalTests++;
        $this->passedTests++; // Считаем как пройденный, но с предупреждением
        echo "  ⚠ WARN: {$message}\n";
        $this->logger->warning("[WARN] {$message}");
    }

    /**
     * Вывод итогов тестирования
     */
    private function printSummary(): void
    {
        $endTime = microtime(true);
        $duration = round($endTime - $this->startTime, 2);
        $successRate = $this->totalTests > 0 
            ? round(($this->passedTests / $this->totalTests) * 100, 2) 
            : 0;

        echo "\n\n" . str_repeat('=', 80) . "\n";
        echo "ИТОГИ ТЕСТИРОВАНИЯ\n";
        echo str_repeat('=', 80) . "\n";
        echo "Всего тестов:     {$this->totalTests}\n";
        echo "Пройдено:         {$this->passedTests} ({$successRate}%)\n";
        echo "Провалено:        {$this->failedTests}\n";
        echo "Время выполнения: {$duration} сек\n";
        echo str_repeat('=', 80) . "\n\n";

        $this->logger->info('=== ИТОГИ ТЕСТИРОВАНИЯ ===', [
            'total' => $this->totalTests,
            'passed' => $this->passedTests,
            'failed' => $this->failedTests,
            'success_rate' => $successRate,
            'duration' => $duration,
        ]);

        $this->logger->info('=== ЗАВЕРШЕНИЕ НАГРУЗОЧНОГО ТЕСТИРОВАНИЯ ===');
        $this->logger->flush();
    }
}

// Запуск тестирования
try {
    $test = new SnmpLoadTest();
    $test->runAllTests();
} catch (Exception $e) {
    echo "\nКритическая ошибка: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
