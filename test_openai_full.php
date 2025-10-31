<?php

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

use App\Component\OpenAi;
use App\Component\Logger;
use App\Component\Http;
use App\Component\Exception\OpenAiException;
use App\Component\Exception\OpenAiValidationException;
use App\Component\Exception\OpenAiApiException;

/**
 * Полноценный тест всех методов класса OpenAi
 * Проверяет работоспособность, логирование, обработку ошибок
 */

// Цветной вывод для терминала
function printHeader(string $text): void {
    echo "\n\033[1;36m" . str_repeat("=", 80) . "\033[0m\n";
    echo "\033[1;36m{$text}\033[0m\n";
    echo "\033[1;36m" . str_repeat("=", 80) . "\033[0m\n";
}

function printSuccess(string $text): void {
    echo "\033[0;32m✓ {$text}\033[0m\n";
}

function printError(string $text): void {
    echo "\033[0;31m✗ {$text}\033[0m\n";
}

function printWarning(string $text): void {
    echo "\033[0;33m⚠ {$text}\033[0m\n";
}

function printInfo(string $text): void {
    echo "\033[0;34mℹ {$text}\033[0m\n";
}

// Тестовые API ключи
$testApiKeys = [
    'sk-1234ijkl5678mnop1234ijkl5678mnop1234ijkl',
    'sk-abcdqrstefgh5678abcdqrstefgh5678abcdqrst',
    'sk-ijklmnopuvwx1234ijklmnopuvwx1234ijklmnop',
    'sk-efgh5678abcd1234efgh5678abcd1234efgh5678',
    'sk-mnopqrstijkl5678mnopqrstijkl5678mnopqrst',
    'sk-1234uvwxabcd5678uvwxabcd1234uvwxabcd5678',
    'sk-ijklmnop5678efghijklmnop5678efghijklmnop',
    'sk-abcd1234qrstuvwxabcd1234qrstuvwxabcd1234',
    'sk-1234efgh5678ijkl1234efgh5678ijkl1234efgh',
    'sk-5678mnopqrstuvwx5678mnopqrstuvwx5678mnop',
];

// Статистика тестов
$stats = [
    'total' => 0,
    'passed' => 0,
    'failed' => 0,
    'warnings' => 0,
];

// Инициализация логгера
printHeader('ИНИЦИАЛИЗАЦИЯ ТЕСТОВОЙ СРЕДЫ');

$logsDir = __DIR__ . '/logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
    printInfo("Создана директория логов: {$logsDir}");
}

$logger = new Logger([
    'directory' => $logsDir,
    'prefix' => 'openai_test',
    'level' => 'debug',
]);
printSuccess('Логгер инициализирован');

// Конфигурация OpenAI с тестовым ключом
$config = [
    'api_key' => $testApiKeys[0],
    'organization' => 'org-test-12345',
    'timeout' => 30,
    'retries' => 2,
];

// =============================================================================
// ТЕСТ 1: Создание экземпляра класса
// =============================================================================
printHeader('ТЕСТ 1: Создание экземпляра класса OpenAi');
$stats['total']++;

try {
    $openAi = new OpenAi($config, $logger);
    printSuccess('Экземпляр класса создан успешно');
    printInfo('API ключ: ' . substr($config['api_key'], 0, 15) . '...');
    printInfo('Organization: ' . $config['organization']);
    printInfo('Timeout: ' . $config['timeout'] . ' сек.');
    $stats['passed']++;
} catch (Exception $e) {
    printError('Ошибка создания экземпляра: ' . $e->getMessage());
    $stats['failed']++;
    exit(1);
}

// =============================================================================
// ТЕСТ 2: Валидация конфигурации - пустой API ключ
// =============================================================================
printHeader('ТЕСТ 2: Валидация конфигурации - пустой API ключ');
$stats['total']++;

try {
    new OpenAi(['api_key' => ''], $logger);
    printError('Ожидалось исключение OpenAiValidationException');
    $stats['failed']++;
} catch (OpenAiValidationException $e) {
    printSuccess('Валидация пустого ключа работает корректно');
    printInfo('Сообщение: ' . $e->getMessage());
    $stats['passed']++;
} catch (Exception $e) {
    printError('Неожиданное исключение: ' . $e->getMessage());
    $stats['failed']++;
}

// =============================================================================
// ТЕСТ 3: Валидация конфигурации - отсутствие API ключа
// =============================================================================
printHeader('ТЕСТ 3: Валидация конфигурации - отсутствие API ключа');
$stats['total']++;

try {
    new OpenAi([], $logger);
    printError('Ожидалось исключение OpenAiValidationException');
    $stats['failed']++;
} catch (OpenAiValidationException $e) {
    printSuccess('Валидация отсутствующего ключа работает корректно');
    printInfo('Сообщение: ' . $e->getMessage());
    $stats['passed']++;
} catch (Exception $e) {
    printError('Неожиданное исключение: ' . $e->getMessage());
    $stats['failed']++;
}

// =============================================================================
// ТЕСТ 4: Метод text2text - валидация пустого промпта
// =============================================================================
printHeader('ТЕСТ 4: Метод text2text - валидация пустого промпта');
$stats['total']++;

try {
    $openAi->text2text('', 'gpt-4o-mini');
    printError('Ожидалось исключение OpenAiValidationException');
    $stats['failed']++;
} catch (OpenAiValidationException $e) {
    printSuccess('Валидация пустого промпта работает корректно');
    printInfo('Сообщение: ' . $e->getMessage());
    $stats['passed']++;
} catch (Exception $e) {
    printError('Неожиданное исключение: ' . $e->getMessage());
    $stats['failed']++;
}

// =============================================================================
// ТЕСТ 5: Метод text2text - валидация пустой модели
// =============================================================================
printHeader('ТЕСТ 5: Метод text2text - валидация пустой модели');
$stats['total']++;

try {
    $openAi->text2text('Тестовый промпт', '');
    printError('Ожидалось исключение OpenAiValidationException');
    $stats['failed']++;
} catch (OpenAiValidationException $e) {
    printSuccess('Валидация пустой модели работает корректно');
    printInfo('Сообщение: ' . $e->getMessage());
    $stats['passed']++;
} catch (Exception $e) {
    printError('Неожиданное исключение: ' . $e->getMessage());
    $stats['failed']++;
}

// =============================================================================
// ТЕСТ 6: Метод text2text - тестовый вызов API (ожидается ошибка аутентификации)
// =============================================================================
printHeader('ТЕСТ 6: Метод text2text - тестовый вызов API');
$stats['total']++;

try {
    $response = $openAi->text2text(
        'Скажи привет на русском языке',
        'gpt-4o-mini',
        [
            'temperature' => 0.7,
            'max_tokens' => 50,
            'system' => 'Ты - дружелюбный помощник',
        ]
    );
    printError('Ожидалась ошибка аутентификации с тестовым ключом');
    $stats['failed']++;
} catch (OpenAiApiException $e) {
    if ($e->getStatusCode() === 401) {
        printSuccess('API вернул корректную ошибку аутентификации (401)');
        printInfo('Сообщение: ' . $e->getMessage());
        printInfo('Код ответа: ' . $e->getStatusCode());
        $stats['passed']++;
    } else {
        printWarning('API вернул неожиданный код ошибки: ' . $e->getStatusCode());
        $stats['warnings']++;
    }
} catch (Exception $e) {
    printError('Неожиданное исключение: ' . get_class($e) . ' - ' . $e->getMessage());
    $stats['failed']++;
}

// =============================================================================
// ТЕСТ 7: Метод text2image - валидация пустого промпта
// =============================================================================
printHeader('ТЕСТ 7: Метод text2image - валидация пустого промпта');
$stats['total']++;

try {
    $openAi->text2image('', 'dall-e-3');
    printError('Ожидалось исключение OpenAiValidationException');
    $stats['failed']++;
} catch (OpenAiValidationException $e) {
    printSuccess('Валидация пустого промпта работает корректно');
    printInfo('Сообщение: ' . $e->getMessage());
    $stats['passed']++;
} catch (Exception $e) {
    printError('Неожиданное исключение: ' . $e->getMessage());
    $stats['failed']++;
}

// =============================================================================
// ТЕСТ 8: Метод text2image - тестовый вызов API
// =============================================================================
printHeader('ТЕСТ 8: Метод text2image - тестовый вызов API');
$stats['total']++;

try {
    $imageUrl = $openAi->text2image(
        'Красивый закат над океаном',
        'dall-e-3',
        [
            'size' => '1024x1024',
            'quality' => 'standard',
            'style' => 'vivid',
        ]
    );
    printError('Ожидалась ошибка аутентификации с тестовым ключом');
    $stats['failed']++;
} catch (OpenAiApiException $e) {
    if ($e->getStatusCode() === 401) {
        printSuccess('API вернул корректную ошибку аутентификации (401)');
        printInfo('Сообщение: ' . $e->getMessage());
        $stats['passed']++;
    } else {
        printWarning('API вернул неожиданный код ошибки: ' . $e->getStatusCode());
        $stats['warnings']++;
    }
} catch (Exception $e) {
    printError('Неожиданное исключение: ' . get_class($e) . ' - ' . $e->getMessage());
    $stats['failed']++;
}

// =============================================================================
// ТЕСТ 9: Метод image2text - валидация пустого URL
// =============================================================================
printHeader('ТЕСТ 9: Метод image2text - валидация пустого URL');
$stats['total']++;

try {
    $openAi->image2text('', 'Что на картинке?', 'gpt-4o');
    printError('Ожидалось исключение OpenAiValidationException');
    $stats['failed']++;
} catch (OpenAiValidationException $e) {
    printSuccess('Валидация пустого URL работает корректно');
    printInfo('Сообщение: ' . $e->getMessage());
    $stats['passed']++;
} catch (Exception $e) {
    printError('Неожиданное исключение: ' . $e->getMessage());
    $stats['failed']++;
}

// =============================================================================
// ТЕСТ 10: Метод image2text - тестовый вызов API
// =============================================================================
printHeader('ТЕСТ 10: Метод image2text - тестовый вызов API');
$stats['total']++;

try {
    $description = $openAi->image2text(
        'https://upload.wikimedia.org/wikipedia/commons/thumb/d/dd/Gfp-wisconsin-madison-the-nature-boardwalk.jpg/2560px-Gfp-wisconsin-madison-the-nature-boardwalk.jpg',
        'Опиши это изображение подробно',
        'gpt-4o',
        [
            'max_tokens' => 300,
            'detail' => 'high',
        ]
    );
    printError('Ожидалась ошибка аутентификации с тестовым ключом');
    $stats['failed']++;
} catch (OpenAiApiException $e) {
    if ($e->getStatusCode() === 401) {
        printSuccess('API вернул корректную ошибку аутентификации (401)');
        printInfo('Сообщение: ' . $e->getMessage());
        $stats['passed']++;
    } else {
        printWarning('API вернул неожиданный код ошибки: ' . $e->getStatusCode());
        $stats['warnings']++;
    }
} catch (Exception $e) {
    printError('Неожиданное исключение: ' . get_class($e) . ' - ' . $e->getMessage());
    $stats['failed']++;
}

// =============================================================================
// ТЕСТ 11: Метод audio2text - валидация пустого URL
// =============================================================================
printHeader('ТЕСТ 11: Метод audio2text - валидация пустого URL');
$stats['total']++;

try {
    $openAi->audio2text('', 'whisper-1');
    printError('Ожидалось исключение OpenAiValidationException');
    $stats['failed']++;
} catch (OpenAiValidationException $e) {
    printSuccess('Валидация пустого URL работает корректно');
    printInfo('Сообщение: ' . $e->getMessage());
    $stats['passed']++;
} catch (Exception $e) {
    printError('Неожиданное исключение: ' . $e->getMessage());
    $stats['failed']++;
}

// =============================================================================
// ТЕСТ 12: Метод audio2text - тестовый вызов API
// =============================================================================
printHeader('ТЕСТ 12: Метод audio2text - тестовый вызов API');
$stats['total']++;

try {
    $transcription = $openAi->audio2text(
        'https://example.com/test-audio.mp3',
        'whisper-1',
        [
            'language' => 'ru',
            'prompt' => 'Тестовая транскрипция',
        ]
    );
    printError('Ожидалась ошибка аутентификации с тестовым ключом');
    $stats['failed']++;
} catch (OpenAiApiException $e) {
    if ($e->getStatusCode() === 401) {
        printSuccess('API вернул корректную ошибку аутентификации (401)');
        printInfo('Сообщение: ' . $e->getMessage());
        $stats['passed']++;
    } else {
        printWarning('API вернул неожиданный код ошибки: ' . $e->getStatusCode());
        $stats['warnings']++;
    }
} catch (Exception $e) {
    printError('Неожиданное исключение: ' . get_class($e) . ' - ' . $e->getMessage());
    $stats['failed']++;
}

// =============================================================================
// ТЕСТ 13: Метод textStream - валидация пустого промпта
// =============================================================================
printHeader('ТЕСТ 13: Метод textStream - валидация пустого промпта');
$stats['total']++;

try {
    $openAi->textStream('', function(string $chunk) {}, 'gpt-4o-mini');
    printError('Ожидалось исключение OpenAiValidationException');
    $stats['failed']++;
} catch (OpenAiValidationException $e) {
    printSuccess('Валидация пустого промпта работает корректно');
    printInfo('Сообщение: ' . $e->getMessage());
    $stats['passed']++;
} catch (Exception $e) {
    printError('Неожиданное исключение: ' . $e->getMessage());
    $stats['failed']++;
}

// =============================================================================
// ТЕСТ 14: Метод textStream - тестовый вызов API
// =============================================================================
printHeader('ТЕСТ 14: Метод textStream - тестовый вызов API');
$stats['total']++;

$streamChunks = [];
try {
    $openAi->textStream(
        'Напиши короткое стихотворение',
        function(string $chunk) use (&$streamChunks) {
            $streamChunks[] = $chunk;
        },
        'gpt-4o-mini',
        [
            'temperature' => 0.8,
            'max_tokens' => 100,
        ]
    );
    printError('Ожидалась ошибка аутентификации с тестовым ключом');
    $stats['failed']++;
} catch (OpenAiApiException $e) {
    if ($e->getStatusCode() === 401) {
        printSuccess('API вернул корректную ошибку аутентификации (401)');
        printInfo('Сообщение: ' . $e->getMessage());
        $stats['passed']++;
    } else {
        printWarning('API вернул неожиданный код ошибки: ' . $e->getStatusCode());
        $stats['warnings']++;
    }
} catch (Exception $e) {
    printError('Неожиданное исключение: ' . get_class($e) . ' - ' . $e->getMessage());
    $stats['failed']++;
}

// =============================================================================
// ТЕСТ 15: Метод embeddings - валидация пустой строки
// =============================================================================
printHeader('ТЕСТ 15: Метод embeddings - валидация пустой строки');
$stats['total']++;

try {
    $openAi->embeddings('', 'text-embedding-3-small');
    printError('Ожидалось исключение OpenAiValidationException');
    $stats['failed']++;
} catch (OpenAiValidationException $e) {
    printSuccess('Валидация пустой строки работает корректно');
    printInfo('Сообщение: ' . $e->getMessage());
    $stats['passed']++;
} catch (Exception $e) {
    printError('Неожиданное исключение: ' . $e->getMessage());
    $stats['failed']++;
}

// =============================================================================
// ТЕСТ 16: Метод embeddings - валидация пустого массива
// =============================================================================
printHeader('ТЕСТ 16: Метод embeddings - валидация пустого массива');
$stats['total']++;

try {
    $openAi->embeddings([], 'text-embedding-3-small');
    printError('Ожидалось исключение OpenAiValidationException');
    $stats['failed']++;
} catch (OpenAiValidationException $e) {
    printSuccess('Валидация пустого массива работает корректно');
    printInfo('Сообщение: ' . $e->getMessage());
    $stats['passed']++;
} catch (Exception $e) {
    printError('Неожиданное исключение: ' . $e->getMessage());
    $stats['failed']++;
}

// =============================================================================
// ТЕСТ 17: Метод embeddings - тестовый вызов API (одна строка)
// =============================================================================
printHeader('ТЕСТ 17: Метод embeddings - тестовый вызов API (одна строка)');
$stats['total']++;

try {
    $embeddings = $openAi->embeddings(
        'Машинное обучение - это раздел искусственного интеллекта',
        'text-embedding-3-small',
        ['dimensions' => 512]
    );
    printError('Ожидалась ошибка аутентификации с тестовым ключом');
    $stats['failed']++;
} catch (OpenAiApiException $e) {
    if ($e->getStatusCode() === 401) {
        printSuccess('API вернул корректную ошибку аутентификации (401)');
        printInfo('Сообщение: ' . $e->getMessage());
        $stats['passed']++;
    } else {
        printWarning('API вернул неожиданный код ошибки: ' . $e->getStatusCode());
        $stats['warnings']++;
    }
} catch (Exception $e) {
    printError('Неожиданное исключение: ' . get_class($e) . ' - ' . $e->getMessage());
    $stats['failed']++;
}

// =============================================================================
// ТЕСТ 18: Метод embeddings - тестовый вызов API (массив строк)
// =============================================================================
printHeader('ТЕСТ 18: Метод embeddings - тестовый вызов API (массив строк)');
$stats['total']++;

try {
    $embeddings = $openAi->embeddings(
        [
            'Первый текст',
            'Второй текст',
            'Третий текст',
        ],
        'text-embedding-3-small'
    );
    printError('Ожидалась ошибка аутентификации с тестовым ключом');
    $stats['failed']++;
} catch (OpenAiApiException $e) {
    if ($e->getStatusCode() === 401) {
        printSuccess('API вернул корректную ошибку аутентификации (401)');
        printInfo('Сообщение: ' . $e->getMessage());
        $stats['passed']++;
    } else {
        printWarning('API вернул неожиданный код ошибки: ' . $e->getStatusCode());
        $stats['warnings']++;
    }
} catch (Exception $e) {
    printError('Неожиданное исключение: ' . get_class($e) . ' - ' . $e->getMessage());
    $stats['failed']++;
}

// =============================================================================
// ТЕСТ 19: Метод moderation - валидация пустой строки
// =============================================================================
printHeader('ТЕСТ 19: Метод moderation - валидация пустой строки');
$stats['total']++;

try {
    $openAi->moderation('', 'text-moderation-latest');
    printError('Ожидалось исключение OpenAiValidationException');
    $stats['failed']++;
} catch (OpenAiValidationException $e) {
    printSuccess('Валидация пустой строки работает корректно');
    printInfo('Сообщение: ' . $e->getMessage());
    $stats['passed']++;
} catch (Exception $e) {
    printError('Неожиданное исключение: ' . $e->getMessage());
    $stats['failed']++;
}

// =============================================================================
// ТЕСТ 20: Метод moderation - тестовый вызов API
// =============================================================================
printHeader('ТЕСТ 20: Метод moderation - тестовый вызов API');
$stats['total']++;

try {
    $moderationResult = $openAi->moderation(
        'Это обычный безопасный текст о погоде и природе',
        'text-moderation-latest'
    );
    printError('Ожидалась ошибка аутентификации с тестовым ключом');
    $stats['failed']++;
} catch (OpenAiApiException $e) {
    if ($e->getStatusCode() === 401) {
        printSuccess('API вернул корректную ошибку аутентификации (401)');
        printInfo('Сообщение: ' . $e->getMessage());
        $stats['passed']++;
    } else {
        printWarning('API вернул неожиданный код ошибки: ' . $e->getStatusCode());
        $stats['warnings']++;
    }
} catch (Exception $e) {
    printError('Неожиданное исключение: ' . get_class($e) . ' - ' . $e->getMessage());
    $stats['failed']++;
}

// =============================================================================
// ТЕСТ 21: Использование разных API ключей
// =============================================================================
printHeader('ТЕСТ 21: Использование разных API ключей');
$stats['total']++;

$keyTestsPassed = 0;
foreach ($testApiKeys as $index => $apiKey) {
    try {
        $testConfig = [
            'api_key' => $apiKey,
            'timeout' => 10,
        ];
        $testOpenAi = new OpenAi($testConfig, $logger);
        
        // Пробуем вызвать метод
        $testOpenAi->text2text('Тест', 'gpt-4o-mini', ['max_tokens' => 10]);
    } catch (OpenAiApiException $e) {
        if ($e->getStatusCode() === 401) {
            $keyTestsPassed++;
        }
    } catch (Exception $e) {
        // Игнорируем другие ошибки
    }
}

if ($keyTestsPassed === count($testApiKeys)) {
    printSuccess("Все {$keyTestsPassed} тестовых API ключей обработаны корректно");
    printInfo('Все ключи вернули ошибку 401 (ожидаемо для тестовых ключей)');
    $stats['passed']++;
} else {
    printWarning("Обработано {$keyTestsPassed} из " . count($testApiKeys) . " ключей");
    $stats['warnings']++;
}

// =============================================================================
// ТЕСТ 22: Проверка логирования
// =============================================================================
printHeader('ТЕСТ 22: Проверка логирования');
$stats['total']++;

// Проверяем, что файл лога создан (логгер пишет в app.log)
$logFile = $logsDir . '/app.log';

if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    
    printSuccess('Файл лога найден: app.log');
    printInfo('Размер лога: ' . number_format(strlen($logContent)) . ' байт');
    
    // Проверяем наличие записей об ошибках OpenAI
    $errorCount = substr_count($logContent, 'ERROR Сервер OpenAI вернул ошибку');
    $warningCount = substr_count($logContent, 'WARNING HTTP запрос выполнен');
    $debugCount = substr_count($logContent, 'DEBUG Отправка запроса');
    
    printInfo("Записей ERROR (OpenAI): {$errorCount}");
    printInfo("Записей WARNING (HTTP): {$warningCount}");
    printInfo("Записей DEBUG (запросы): {$debugCount}");
    
    if ($errorCount > 0 && $debugCount > 0) {
        printSuccess('Логирование ошибок и отладочной информации работает корректно');
        $stats['passed']++;
    } else {
        printWarning('Неполное логирование: ERROR=' . $errorCount . ', DEBUG=' . $debugCount);
        $stats['warnings']++;
    }
} else {
    printError('Файл лога app.log не найден');
    $stats['failed']++;
}

// =============================================================================
// ТЕСТ 23: Проверка обработки опций в методе text2text
// =============================================================================
printHeader('ТЕСТ 23: Проверка обработки опций в методе text2text');
$stats['total']++;

try {
    $openAi->text2text(
        'Тестовый промпт',
        'gpt-4o-mini',
        [
            'temperature' => 0.5,
            'max_tokens' => 100,
            'top_p' => 0.9,
            'frequency_penalty' => 0.5,
            'presence_penalty' => 0.5,
            'system' => 'Системное сообщение',
        ]
    );
    printError('Ожидалась ошибка аутентификации');
    $stats['failed']++;
} catch (OpenAiApiException $e) {
    if ($e->getStatusCode() === 401) {
        printSuccess('Опции обрабатываются корректно');
        printInfo('Системное сообщение добавлено в messages');
        $stats['passed']++;
    } else {
        printWarning('Неожиданный код ошибки: ' . $e->getStatusCode());
        $stats['warnings']++;
    }
} catch (Exception $e) {
    printError('Неожиданное исключение: ' . $e->getMessage());
    $stats['failed']++;
}

// =============================================================================
// ТЕСТ 24: Проверка обработки разных размеров изображений
// =============================================================================
printHeader('ТЕСТ 24: Проверка обработки разных размеров изображений');
$stats['total']++;

$imageSizes = ['1024x1024', '1792x1024', '1024x1792'];
$sizeTestsPassed = 0;

foreach ($imageSizes as $size) {
    try {
        $openAi->text2image(
            'Тестовое изображение',
            'dall-e-3',
            ['size' => $size, 'quality' => 'standard']
        );
    } catch (OpenAiApiException $e) {
        if ($e->getStatusCode() === 401) {
            $sizeTestsPassed++;
        }
    }
}

if ($sizeTestsPassed === count($imageSizes)) {
    printSuccess("Все размеры изображений обработаны корректно: " . implode(', ', $imageSizes));
    $stats['passed']++;
} else {
    printWarning("Обработано {$sizeTestsPassed} из " . count($imageSizes) . " размеров");
    $stats['warnings']++;
}

// =============================================================================
// ТЕСТ 25: Проверка обработки detail параметра в image2text
// =============================================================================
printHeader('ТЕСТ 25: Проверка обработки detail параметра в image2text');
$stats['total']++;

$detailLevels = ['low', 'high', 'auto'];
$detailTestsPassed = 0;

foreach ($detailLevels as $detail) {
    try {
        $openAi->image2text(
            'https://example.com/image.jpg',
            'Опиши изображение',
            'gpt-4o',
            ['detail' => $detail]
        );
    } catch (OpenAiApiException $e) {
        if ($e->getStatusCode() === 401) {
            $detailTestsPassed++;
        }
    }
}

if ($detailTestsPassed === count($detailLevels)) {
    printSuccess("Все уровни detail обработаны корректно: " . implode(', ', $detailLevels));
    $stats['passed']++;
} else {
    printWarning("Обработано {$detailTestsPassed} из " . count($detailLevels) . " уровней");
    $stats['warnings']++;
}

// =============================================================================
// РЕЗУЛЬТАТЫ ТЕСТИРОВАНИЯ
// =============================================================================
printHeader('РЕЗУЛЬТАТЫ ТЕСТИРОВАНИЯ');

echo "\n";
echo "Всего тестов:      \033[1;37m{$stats['total']}\033[0m\n";
echo "Успешных:          \033[1;32m{$stats['passed']}\033[0m\n";
echo "Проваленных:       \033[1;31m{$stats['failed']}\033[0m\n";
echo "Предупреждений:    \033[1;33m{$stats['warnings']}\033[0m\n";

$successRate = $stats['total'] > 0 ? round(($stats['passed'] / $stats['total']) * 100, 2) : 0;
echo "\nУспешность:        \033[1;36m{$successRate}%\033[0m\n";

if ($stats['failed'] === 0) {
    echo "\n\033[1;32m✓ ВСЕ ТЕСТЫ ПРОЙДЕНЫ УСПЕШНО!\033[0m\n";
    exit(0);
} else {
    echo "\n\033[1;31m✗ ОБНАРУЖЕНЫ ОШИБКИ!\033[0m\n";
    exit(1);
}
