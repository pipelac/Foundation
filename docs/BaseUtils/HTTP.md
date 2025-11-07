# Http - Документация

## Описание

`Http` - класс-обёртка для выполнения HTTP-запросов на базе Guzzle с поддержкой ретраев, логирования, потоковой передачи данных и расширенной обработки ошибок.

## Возможности

- ✅ Все HTTP методы (GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS)
- ✅ Автоматические повторные попытки (retry) при сбоях
- ✅ Потоковая передача данных (streaming)
- ✅ Поддержка JSON, форм и multipart данных
- ✅ Настраиваемые таймауты
- ✅ Прокси поддержка
- ✅ SSL/TLS настройки
- ✅ Редиректы
- ✅ Интеграция с Logger
- ✅ Заголовки по умолчанию
- ✅ Base URI для API клиентов

## Требования

- PHP 8.1+
- Расширения: `curl`, `json`
- Guzzle HTTP клиент (устанавливается через Composer)

## Установка

```bash
composer install
```

## Конфигурация

### Базовая конфигурация

```php
$http = new Http([
    'timeout' => 10,
    'connect_timeout' => 5,
    'verify' => true,
]);
```

### Конфигурация для API клиента

```php
$http = new Http([
    'base_uri' => 'https://api.example.com/v1',
    'timeout' => 30,
    'headers' => [
        'Authorization' => 'Bearer your-token',
        'Accept' => 'application/json',
        'User-Agent' => 'MyApp/1.0',
    ],
    'retries' => 3,
]);
```

### Параметры конфигурации

| Параметр | Тип | По умолчанию | Описание |
|----------|-----|--------------|----------|
| `base_uri` | string | - | Базовый URL для всех запросов |
| `timeout` | float | - | Общий таймаут в секундах |
| `connect_timeout` | float | - | Таймаут подключения |
| `verify` | bool | true | Проверка SSL сертификата |
| `proxy` | string\|array | - | Настройки прокси |
| `headers` | array | [] | Заголовки по умолчанию |
| `allow_redirects` | bool\|array | - | Настройки редиректов |
| `retries` | int | - | Количество повторных попыток |
| `options` | array | [] | Дополнительные опции Guzzle |

## Использование

### Инициализация

```php
use App\Component\Http;
use App\Component\Logger;

// С логгером
$logger = new Logger($loggerConfig);
$http = new Http($config, $logger);

// Без логгера
$http = new Http($config);

// Минимальная конфигурация
$http = new Http();
```

### GET запросы

```php
// Простой GET
$response = $http->get('https://api.example.com/users');
$body = (string)$response->getBody();
$statusCode = $response->getStatusCode();

// С параметрами
$response = $http->get('https://api.example.com/users', [
    'query' => [
        'page' => 1,
        'limit' => 10,
        'sort' => 'name',
    ],
]);

// С заголовками
$response = $http->get('https://api.example.com/protected', [
    'headers' => [
        'Authorization' => 'Bearer token123',
        'Accept' => 'application/json',
    ],
]);

// Используя request()
$response = $http->request('GET', 'https://api.example.com/data');
```

### POST запросы

```php
// JSON данные
$response = $http->post('https://api.example.com/users', [
    'json' => [
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ],
]);

// Форма (application/x-www-form-urlencoded)
$response = $http->post('https://api.example.com/login', [
    'form_params' => [
        'username' => 'john',
        'password' => 'secret',
    ],
]);

// Multipart (для загрузки файлов)
$response = $http->post('https://api.example.com/upload', [
    'multipart' => [
        [
            'name' => 'file',
            'contents' => fopen('/path/to/file.jpg', 'r'),
            'filename' => 'photo.jpg',
        ],
        [
            'name' => 'description',
            'contents' => 'My photo',
        ],
    ],
]);

// С заголовками
$response = $http->post('https://api.example.com/data', [
    'json' => ['key' => 'value'],
    'headers' => [
        'Authorization' => 'Bearer token',
        'X-Custom-Header' => 'value',
    ],
]);
```

### PUT и PATCH запросы

```php
// PUT (полное обновление)
$response = $http->put('https://api.example.com/users/123', [
    'json' => [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '+1234567890',
    ],
]);

// PATCH (частичное обновление)
$response = $http->patch('https://api.example.com/users/123', [
    'json' => [
        'email' => 'newemail@example.com',
    ],
]);
```

### DELETE запросы

```php
// Удаление ресурса
$response = $http->delete('https://api.example.com/users/123');

// С подтверждением
$response = $http->delete('https://api.example.com/users/123', [
    'headers' => [
        'X-Confirm' => 'yes',
    ],
]);
```

### Streaming (потоковая передача)

```php
// Потоковое чтение ответа
$http->requestStream(
    'GET',
    'https://api.example.com/large-data',
    function (string $chunk) {
        // Обработка каждого чанка
        echo $chunk;
        // или запись в файл
        file_put_contents('output.txt', $chunk, FILE_APPEND);
    }
);

// SSE (Server-Sent Events)
$http->requestStream(
    'GET',
    'https://api.example.com/events',
    function (string $chunk) {
        // Обработка event stream
        if (str_starts_with($chunk, 'data: ')) {
            $data = substr($chunk, 6);
            echo "Received: {$data}\n";
        }
    },
    [
        'headers' => [
            'Accept' => 'text/event-stream',
        ],
    ]
);
```

## Примеры использования

### REST API клиент

```php
class ApiClient
{
    private Http $http;
    private string $apiKey;
    
    public function __construct(string $apiKey, ?Logger $logger = null)
    {
        $this->apiKey = $apiKey;
        $this->http = new Http([
            'base_uri' => 'https://api.example.com/v1',
            'timeout' => 30,
            'headers' => [
                'Authorization' => "Bearer {$apiKey}",
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'retries' => 3,
        ], $logger);
    }
    
    public function getUsers(int $page = 1, int $limit = 10): array
    {
        $response = $this->http->get('/users', [
            'query' => [
                'page' => $page,
                'limit' => $limit,
            ],
        ]);
        
        return json_decode($response->getBody(), true);
    }
    
    public function getUser(int $id): array
    {
        $response = $this->http->get("/users/{$id}");
        return json_decode($response->getBody(), true);
    }
    
    public function createUser(array $data): array
    {
        $response = $this->http->post('/users', [
            'json' => $data,
        ]);
        
        return json_decode($response->getBody(), true);
    }
    
    public function updateUser(int $id, array $data): array
    {
        $response = $this->http->put("/users/{$id}", [
            'json' => $data,
        ]);
        
        return json_decode($response->getBody(), true);
    }
    
    public function deleteUser(int $id): bool
    {
        $response = $this->http->delete("/users/{$id}");
        return $response->getStatusCode() === 204;
    }
}

// Использование
$api = new ApiClient('your-api-key');

// Получить пользователей
$users = $api->getUsers(page: 1, limit: 20);

// Создать пользователя
$newUser = $api->createUser([
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

// Обновить
$api->updateUser($newUser['id'], [
    'phone' => '+1234567890',
]);

// Удалить
$api->deleteUser($newUser['id']);
```

### Загрузка файлов

```php
class FileDownloader
{
    private Http $http;
    
    public function __construct(?Logger $logger = null)
    {
        $this->http = new Http([
            'timeout' => 120,
            'connect_timeout' => 30,
        ], $logger);
    }
    
    public function download(string $url, string $destination): bool
    {
        try {
            $response = $this->http->get($url);
            
            if ($response->getStatusCode() !== 200) {
                throw new Exception("Download failed with status: {$response->getStatusCode()}");
            }
            
            file_put_contents($destination, $response->getBody());
            
            return true;
            
        } catch (Exception $e) {
            error_log("Download error: " . $e->getMessage());
            return false;
        }
    }
    
    public function downloadStream(string $url, string $destination): bool
    {
        $handle = fopen($destination, 'w');
        
        try {
            $this->http->requestStream('GET', $url, function (string $chunk) use ($handle) {
                fwrite($handle, $chunk);
            });
            
            fclose($handle);
            return true;
            
        } catch (Exception $e) {
            fclose($handle);
            unlink($destination);
            error_log("Download error: " . $e->getMessage());
            return false;
        }
    }
}

// Использование
$downloader = new FileDownloader();

// Обычная загрузка
$downloader->download(
    'https://example.com/large-file.zip',
    '/tmp/file.zip'
);

// Потоковая загрузка (для больших файлов)
$downloader->downloadStream(
    'https://example.com/huge-file.iso',
    '/tmp/file.iso'
);
```

### Webhook клиент

```php
class WebhookClient
{
    private Http $http;
    
    public function __construct(?Logger $logger = null)
    {
        $this->http = new Http([
            'timeout' => 10,
            'retries' => 3,
        ], $logger);
    }
    
    public function send(string $url, array $payload, string $secret = null): bool
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];
        
        // Добавить подпись HMAC если есть секрет
        if ($secret !== null) {
            $signature = hash_hmac('sha256', json_encode($payload), $secret);
            $headers['X-Webhook-Signature'] = $signature;
        }
        
        try {
            $response = $this->http->post($url, [
                'json' => $payload,
                'headers' => $headers,
            ]);
            
            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
            
        } catch (Exception $e) {
            error_log("Webhook error: " . $e->getMessage());
            return false;
        }
    }
}

// Использование
$webhook = new WebhookClient($logger);

$webhook->send('https://example.com/webhook', [
    'event' => 'user.created',
    'data' => [
        'user_id' => 123,
        'email' => 'user@example.com',
    ],
], 'webhook-secret');
```

### Работа с GraphQL

```php
class GraphQLClient
{
    private Http $http;
    
    public function __construct(string $endpoint, ?string $token = null, ?Logger $logger = null)
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];
        
        if ($token !== null) {
            $headers['Authorization'] = "Bearer {$token}";
        }
        
        $this->http = new Http([
            'base_uri' => $endpoint,
            'headers' => $headers,
            'timeout' => 30,
        ], $logger);
    }
    
    public function query(string $query, array $variables = []): array
    {
        $response = $this->http->post('', [
            'json' => [
                'query' => $query,
                'variables' => $variables,
            ],
        ]);
        
        $result = json_decode($response->getBody(), true);
        
        if (isset($result['errors'])) {
            throw new Exception('GraphQL errors: ' . json_encode($result['errors']));
        }
        
        return $result['data'] ?? [];
    }
}

// Использование
$graphql = new GraphQLClient('https://api.example.com/graphql', 'token');

$query = '
    query GetUser($id: ID!) {
        user(id: $id) {
            id
            name
            email
        }
    }
';

$data = $graphql->query($query, ['id' => 123]);
print_r($data['user']);
```

### Прокси и аутентификация

```php
// С прокси
$http = new Http([
    'proxy' => 'http://proxy.example.com:8080',
]);

// С аутентификацией прокси
$http = new Http([
    'proxy' => 'http://user:pass@proxy.example.com:8080',
]);

// Basic Authentication
$http = new Http([
    'headers' => [
        'Authorization' => 'Basic ' . base64_encode('username:password'),
    ],
]);

// Bearer Token
$http = new Http([
    'headers' => [
        'Authorization' => 'Bearer your-token-here',
    ],
]);
```

## API Reference

### Конструктор

```php
public function __construct(array $config = [], ?Logger $logger = null)
```

**Параметры:**
- `$config` (array) - Конфигурация HTTP клиента
- `$logger` (Logger|null) - Опциональный логгер

### request()

```php
public function request(string $method, string $uri, array $options = []): ResponseInterface
```

Выполняет HTTP запрос.

**Параметры:**
- `$method` - HTTP метод
- `$uri` - URL или путь
- `$options` - Опции запроса

### get()

```php
public function get(string $uri, array $options = []): ResponseInterface
```

GET запрос.

### post()

```php
public function post(string $uri, array $options = []): ResponseInterface
```

POST запрос.

### put()

```php
public function put(string $uri, array $options = []): ResponseInterface
```

PUT запрос.

### patch()

```php
public function patch(string $uri, array $options = []): ResponseInterface
```

PATCH запрос.

### delete()

```php
public function delete(string $uri, array $options = []): ResponseInterface
```

DELETE запрос.

### requestStream()

```php
public function requestStream(string $method, string $uri, callable $callback, array $options = []): void
```

Потоковый запрос.

**Callback:** `function(string $chunk): void`

## Обработка ошибок

### Исключения

- `HttpException` - Базовое исключение
- `HttpValidationException` - Ошибка валидации

```php
use App\Component\Exception\HttpException;
use App\Component\Exception\HttpValidationException;

try {
    $response = $http->get('https://api.example.com/data');
    
    if ($response->getStatusCode() >= 400) {
        throw new Exception("API error: {$response->getStatusCode()}");
    }
    
    $data = json_decode($response->getBody(), true);
    
} catch (HttpValidationException $e) {
    echo "Validation error: {$e->getMessage()}\n";
} catch (HttpException $e) {
    echo "HTTP error: {$e->getMessage()}\n";
} catch (Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
```

## Лучшие практики

1. **Используйте base_uri** для API клиентов:
   ```php
   ['base_uri' => 'https://api.example.com/v1']
   ```

2. **Настройте таймауты** соответственно:
   ```php
   ['timeout' => 30, 'connect_timeout' => 5]
   ```

3. **Используйте retry** для надежности:
   ```php
   ['retries' => 3]
   ```

4. **Проверяйте статус коды**:
   ```php
   if ($response->getStatusCode() !== 200) {
       throw new Exception('Request failed');
   }
   ```

5. **Используйте streaming** для больших файлов

6. **Логируйте запросы**:
   ```php
   $http = new Http($config, $logger);
   ```

7. **Валидируйте SSL** в production:
   ```php
   ['verify' => true]
   ```

8. **Используйте заголовки по умолчанию**:
   ```php
   ['headers' => ['User-Agent' => 'MyApp/1.0']]
   ```

## Производительность

- Переиспользуйте Http объекты
- Используйте keep-alive соединения
- Настройте разумные таймауты
- Используйте streaming для больших данных
- Кешируйте результаты где возможно
- Используйте асинхронные запросы для параллелизма

## Безопасность

1. **Проверяйте SSL сертификаты**:
   ```php
   ['verify' => true]
   ```

2. **Не логируйте чувствительные данные** (пароли, токены)

3. **Используйте HTTPS** где возможно

4. **Валидируйте ответы сервера**

5. **Ограничивайте размер ответа**

6. **Используйте таймауты** для защиты от зависаний

## См. также

- [OpenRouter документация](OPENROUTER.md) - использует Http
- [RSS документация](RSS.md) - использует Http
- [Telegram документация](TELEGRAM.md) - использует Http
- [Logger документация](LOGGER.md) - для логирования запросов
- [Guzzle Documentation](https://docs.guzzlephp.org/)
