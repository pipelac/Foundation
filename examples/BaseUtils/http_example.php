<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../autoload.php';

use App\Component\Http;
use App\Component\Logger;

/**
 * Примеры использования улучшенного класса Http
 * Демонстрация всех возможностей класса на production уровне
 */

echo "=== Примеры использования класса Http ===\n\n";

// Пример 1: Базовая конфигурация с логированием
echo "1. Создание HTTP клиента с полной конфигурацией:\n";
$logger = new Logger([
    'directory' => __DIR__ . '/../logs',
    'file_name' => 'http.log',
    'max_files' => 5,
    'max_file_size' => 10, // МБ
]);

$http = new Http([
    'base_uri' => 'https://jsonplaceholder.typicode.com',
    'timeout' => 30.0,
    'connect_timeout' => 5.0,
    'verify' => true,
    'retries' => 3,
    'headers' => [
        'User-Agent' => 'PHP-HTTP-Client/1.0',
        'Accept' => 'application/json',
    ],
], $logger);

echo "✓ HTTP клиент создан\n\n";

// Пример 2: Простой GET запрос через хелпер
echo "2. GET запрос через метод-хелпер:\n";
try {
    $response = $http->get('/posts/1');
    echo "Статус: " . $response->getStatusCode() . "\n";
    $data = json_decode((string)$response->getBody(), true);
    echo "Заголовок поста: " . ($data['title'] ?? 'N/A') . "\n";
    echo "✓ GET запрос выполнен успешно\n\n";
} catch (\InvalidArgumentException $e) {
    echo "❌ Ошибка валидации: " . $e->getMessage() . "\n\n";
} catch (\RuntimeException $e) {
    echo "❌ Ошибка HTTP запроса: " . $e->getMessage() . "\n\n";
}

// Пример 3: POST запрос с JSON данными
echo "3. POST запрос с JSON данными:\n";
try {
    $response = $http->post('/posts', [
        'json' => [
            'title' => 'Тестовый пост',
            'body' => 'Это тестовое содержимое',
            'userId' => 1,
        ],
    ]);
    echo "Статус: " . $response->getStatusCode() . "\n";
    $data = json_decode((string)$response->getBody(), true);
    echo "ID созданного поста: " . ($data['id'] ?? 'N/A') . "\n";
    echo "✓ POST запрос выполнен успешно\n\n";
} catch (\Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n\n";
}

// Пример 4: PUT запрос для обновления данных
echo "4. PUT запрос для обновления:\n";
try {
    $response = $http->put('/posts/1', [
        'json' => [
            'id' => 1,
            'title' => 'Обновленный заголовок',
            'body' => 'Обновленное содержимое',
            'userId' => 1,
        ],
    ]);
    echo "Статус: " . $response->getStatusCode() . "\n";
    echo "✓ PUT запрос выполнен успешно\n\n";
} catch (\Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n\n";
}

// Пример 5: PATCH запрос для частичного обновления
echo "5. PATCH запрос для частичного обновления:\n";
try {
    $response = $http->patch('/posts/1', [
        'json' => [
            'title' => 'Частично обновленный заголовок',
        ],
    ]);
    echo "Статус: " . $response->getStatusCode() . "\n";
    echo "✓ PATCH запрос выполнен успешно\n\n";
} catch (\Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n\n";
}

// Пример 6: DELETE запрос
echo "6. DELETE запрос:\n";
try {
    $response = $http->delete('/posts/1');
    echo "Статус: " . $response->getStatusCode() . "\n";
    echo "✓ DELETE запрос выполнен успешно\n\n";
} catch (\Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n\n";
}

// Пример 7: HEAD запрос для проверки существования ресурса
echo "7. HEAD запрос для проверки ресурса:\n";
try {
    $response = $http->head('/posts/1');
    echo "Статус: " . $response->getStatusCode() . "\n";
    echo "Content-Type: " . $response->getHeaderLine('Content-Type') . "\n";
    echo "✓ HEAD запрос выполнен успешно\n\n";
} catch (\Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n\n";
}

// Пример 8: Использование констант для методов
echo "8. Запрос с использованием констант:\n";
try {
    $response = $http->request(Http::METHOD_GET, '/posts', [
        'query' => [
            'userId' => 1,
            '_limit' => 5,
        ],
    ]);
    echo "Статус: " . $response->getStatusCode() . "\n";
    $posts = json_decode((string)$response->getBody(), true);
    echo "Получено постов: " . count($posts) . "\n";
    echo "✓ Запрос с константами выполнен успешно\n\n";
} catch (\Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n\n";
}

// Пример 9: Обработка ошибок валидации
echo "9. Демонстрация валидации параметров:\n";
try {
    $response = $http->get(''); // Пустой URI
    echo "Этот код не должен выполниться\n";
} catch (\InvalidArgumentException $e) {
    echo "✓ Корректно поймана ошибка валидации: " . $e->getMessage() . "\n\n";
} catch (\Exception $e) {
    echo "❌ Неожиданная ошибка: " . $e->getMessage() . "\n\n";
}

// Пример 10: Потоковая передача данных
echo "10. Потоковая передача данных (Stream):\n";
try {
    $chunks = 0;
    $totalBytes = 0;
    
    $http->requestStream(
        Http::METHOD_GET,
        '/posts',
        function (string $chunk) use (&$chunks, &$totalBytes): void {
            $chunks++;
            $totalBytes += strlen($chunk);
        }
    );
    
    echo "Получено чанков: $chunks\n";
    echo "Всего байт: $totalBytes\n";
    echo "✓ Потоковая передача выполнена успешно\n\n";
} catch (\Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n\n";
}

// Пример 11: Получение базового Guzzle клиента для расширенного использования
echo "11. Получение базового Guzzle клиента:\n";
try {
    $guzzleClient = $http->getClient();
    echo "Тип клиента: " . get_class($guzzleClient) . "\n";
    echo "✓ Клиент успешно получен для расширенного использования\n\n";
} catch (\Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n\n";
}

// Пример 12: Работа с retry логикой
echo "12. Демонстрация retry логики (с несуществующим хостом):\n";
$httpWithRetry = new Http([
    'base_uri' => 'http://non-existent-host-12345.invalid',
    'timeout' => 2.0,
    'connect_timeout' => 1.0,
    'retries' => 3,
], $logger);

try {
    $response = $httpWithRetry->get('/test');
    echo "Статус: " . $response->getStatusCode() . "\n";
} catch (\RuntimeException $e) {
    echo "✓ Ожидаемо не удалось подключиться (retry сработал 3 раза)\n";
    echo "Сообщение: " . $e->getMessage() . "\n\n";
}

// Пример 13: Кастомные заголовки и query параметры
echo "13. Запрос с кастомными заголовками и query параметрами:\n";
try {
    $response = $http->get('/comments', [
        'headers' => [
            'X-Custom-Header' => 'CustomValue',
            'X-Request-ID' => uniqid('req_', true),
        ],
        'query' => [
            'postId' => 1,
            '_limit' => 3,
        ],
    ]);
    
    echo "Статус: " . $response->getStatusCode() . "\n";
    $comments = json_decode((string)$response->getBody(), true);
    echo "Получено комментариев: " . count($comments) . "\n";
    echo "✓ Запрос с кастомными параметрами выполнен успешно\n\n";
} catch (\Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n\n";
}

echo "=== Все примеры выполнены ===\n";
echo "\n📋 Проверьте лог файл: " . __DIR__ . "/../logs/http.log\n";
echo "В нём будут записаны все ошибки и retry попытки.\n";
